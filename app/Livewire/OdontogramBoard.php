<?php

namespace App\Livewire;

use App\Domain\Odontogram\ToothNumbering;
use App\Domain\Odontogram\ToothSvgBuilder;
use App\Models\Odontogram;
use App\Models\OdontogramTooth;
use App\Models\OdontogramToothFace;
use App\Models\Patient;
use App\Models\ToothCondition;
use App\Models\ToothDefinition;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OdontogramBoard extends Component
{
    public Patient $patient;

    public ?Odontogram $odontogram = null;

    public string $dentition = 'adult';

    public string $numberingSystem = 'fdi';

    public ?string $activeCondition = null;

    public string $saveMessage = '';

    public ?string $notes = null;

    public ?string $examinedAt = null;

    protected $listeners = ['clearSaveMessage'];

    /** Cambios pendientes de marcar en el tablero que aún no se han persistido (ver `save`). */
    public array $pending = [];

    public bool $showObservationModal = false;

    public ?int $observationFdiCode = null;

    public ?string $observationFace = null;

    public ?string $observationConditionCode = null;

    public string $observationText = '';

    public bool $showToothNoteModal = false;

    public ?int $toothNoteFdiCode = null;

    public string $toothNoteText = '';

    public function mount(Patient $patient): void
    {
        $this->patient = $patient;

        // Cada paciente tiene un único odontograma sobre el que se registra cada tratamiento.
        try {
            $this->odontogram = $patient->odontogram()->firstOrCreate([
                'dentition' => $this->dentition,
                'numbering_system' => $this->numberingSystem,
            ]);
        } catch (QueryException) {
            // Race condition: otro request creó el odontograma primero.
            $this->odontogram = $patient->odontogram()->firstOrFail();
        }

        $this->dentition = $this->odontogram->dentition;
        $this->numberingSystem = $this->odontogram->numbering_system;
        $this->notes = $this->odontogram->notes;
        $this->examinedAt = $this->odontogram->examined_at?->format('Y-m-d') ?? now()->format('Y-m-d');
    }

    #[Computed]
    public function conditions(): Collection
    {
        return ToothCondition::orderBy('sort_order')->get();
    }

    #[Computed]
    public function conditionsByCode(): Collection
    {
        return $this->conditions->keyBy('code');
    }

    #[Computed]
    public function definitions(): Collection
    {
        return ToothDefinition::query()
            ->where('dentition', $this->dentition)
            ->orderBy('display_order')
            ->get()
            ->groupBy('arch'); // ['upper' => Collection, 'lower' => Collection]
    }

    #[Computed]
    public function teethByCode(): Collection
    {
        return $this->odontogram
            ->teeth()
            ->with(['wholeCondition', 'faces.condition'])
            ->get()
            ->keyBy('fdi_code');
    }

    #[Computed]
    public function findings(): Collection
    {
        $items = [];

        foreach ($this->stagedToothStates() as $fdiCode => $state) {
            if ($state['whole'] !== null && $state['whole'] !== ToothCondition::CODE_SANO) {
                $condition = $this->conditionsByCode[$state['whole']] ?? null;

                if ($condition) {
                    $items[] = [
                        'fdi_code' => $fdiCode,
                        'label' => $condition->label,
                        'color' => $condition->color,
                        'face_label' => 'Pieza completa',
                    ];
                }
            }

            foreach ($state['faces'] as $face => $code) {
                if ($code === ToothCondition::CODE_SANO) {
                    continue;
                }

                $condition = $this->conditionsByCode[$code] ?? null;

                if ($condition) {
                    $items[] = [
                        'fdi_code' => $fdiCode,
                        'label' => $condition->label,
                        'color' => $condition->color,
                        'face_label' => $face,
                    ];
                }
            }
        }

        return collect($items);
    }

    #[Computed]
    public function history(): Collection
    {
        $entries = $this->odontogram
            ->treatmentLog()
            ->with('condition')
            ->orderByDesc('registered_at')
            ->get();

        foreach ($this->pending as $action) {
            $condition = $action['condition_code'] !== null
                ? ($this->conditionsByCode[$action['condition_code']] ?? null)
                : null;

            $entries->prepend((object) [
                'fdi_code' => $action['fdi_code'],
                'face' => $action['face'],
                'condition' => $condition,
                'observation' => $action['observation'],
                'registered_at' => now(),
                'pending' => true,
            ]);
        }

        return $entries;
    }

    /** Selecciona una condición de la paleta (equivalente a `active` en el Alpine original). */
    public function pickCondition(string $code): void
    {
        $this->activeCondition = $this->activeCondition === $code ? null : $code;
    }

    /**
     * Marca una cara o la pieza completa con la condición activa.
     * $face === 'numero' => pieza completa (equivalente al click en el número).
     *
     * El marcado se acumula en `pending` y solo se persiste al pulsar "Guardar":
     * - Zona sin tratar: encola la aplicación de la condición.
     * - Zona ya tratada: no se re-marca; se abre el modal para agregar una observación.
     * - Condición 'sano': encola el borrado de la zona.
     */
    public function selectZone(int $fdiCode, string $face): void
    {
        if (! $this->activeCondition) {
            return;
        }

        $condition = ToothCondition::where('code', $this->activeCondition)->firstOrFail();
        $zoneFace = $face === 'numero' ? null : $face;

        // Las condiciones de pieza completa (extracción, corona, endodoncia…) aplican
        // aunque el clic se haga sobre el cuerpo del diente en vez del número.
        if ($zoneFace !== null && $condition->appliesToTooth() && ! $condition->appliesToFace()) {
            $zoneFace = null;
        }

        $currentConditionCode = $this->stagedZoneCondition($fdiCode, $zoneFace);

        if ($condition->code === ToothCondition::CODE_SANO) {
            if ($currentConditionCode !== null) {
                $this->stage('clear', $fdiCode, $zoneFace, $currentConditionCode, null);
            }
        } elseif ($currentConditionCode !== null) {
            $this->openObservation($fdiCode, $zoneFace, $currentConditionCode);
        } elseif ($this->conditionAppliesToZone($condition, $zoneFace)) {
            $this->stage('apply', $fdiCode, $zoneFace, $condition->code, null);
        }

        unset($this->findings, $this->history);
    }

    /** Añade una acción a la cola de cambios pendientes (aún sin persistir). */
    private function stage(string $action, int $fdiCode, ?string $face, ?string $conditionCode, ?string $observation): void
    {
        $this->pending[] = [
            'fdi_code' => $fdiCode,
            'face' => $face,
            'action' => $action,
            'condition_code' => $conditionCode,
            'observation' => $observation,
        ];
    }

    /** Elimina la última acción pendiente (deshacer). */
    public function undoPending(): void
    {
        if (count($this->pending) > 0) {
            array_pop($this->pending);
            unset($this->findings, $this->history);
        }
    }

    /** Descarta todos los cambios pendientes. */
    public function discardPending(): void
    {
        $this->pending = [];
        unset($this->findings, $this->history);
    }

    /**
     * Estado actual (BD + cambios pendientes) de cada pieza, como mapa por código FDI:
     * ['whole' => código|null, 'faces' => [cara => código]].
     *
     * @return Collection<int, array{whole: string|null, faces: array<string, string>}>
     */
    private function stagedToothStates(): Collection
    {
        $states = $this->teethByCode->mapWithKeys(function (OdontogramTooth $tooth) {
            return [$tooth->fdi_code => [
                'whole' => $tooth->wholeCondition?->code,
                'faces' => $tooth->faces->mapWithKeys(
                    fn (OdontogramToothFace $face) => [$face->face => $face->condition?->code]
                )->all(),
            ]];
        });

        foreach ($this->pending as $action) {
            if (! $states->has($action['fdi_code'])) {
                $states->put($action['fdi_code'], ['whole' => null, 'faces' => []]);
            }

            $state = $states->get($action['fdi_code']);
            $face = $action['face'];

            if ($action['action'] === 'apply') {
                if ($face === null) {
                    $state['whole'] = $action['condition_code'];
                } else {
                    $state['faces'][$face] = $action['condition_code'];
                }
            } elseif ($action['action'] === 'clear') {
                if ($face === null) {
                    $state['whole'] = null;
                } else {
                    unset($state['faces'][$face]);
                }
            }

            $states->put($action['fdi_code'], $state);
        }

        return $states;
    }

    /** Código de condición actual (BD + pendiente) de una zona; null si está sin tratar. */
    private function stagedZoneCondition(int $fdiCode, ?string $face): ?string
    {
        $state = $this->stagedToothStates()->get($fdiCode);

        if (! $state) {
            return null;
        }

        return $face === null ? $state['whole'] : ($state['faces'][$face] ?? null);
    }

    /** Devuelve false si la condición no aplica a esa zona (cara vs pieza completa). */
    private function conditionAppliesToZone(ToothCondition $condition, ?string $face): bool
    {
        return $face === null ? $condition->appliesToTooth() : $condition->appliesToFace();
    }

    /** Persiste una acción pendiente sobre dientes/caras y la registra en la bitácora. */
    private function applyPendingAction(array $action): void
    {
        $condition = $action['condition_code'] !== null
            ? ToothCondition::where('code', $action['condition_code'])->first()
            : null;

        if ($action['action'] === 'apply') {
            $this->applyToZone($action['fdi_code'], $action['face'], $condition);
        } elseif ($action['action'] === 'clear') {
            $this->clearZone($action['fdi_code'], $action['face']);
        }

        $this->odontogram->treatmentLog()->create([
            'fdi_code' => $action['fdi_code'],
            'face' => $action['face'],
            'condition_id' => $condition?->id,
            'observation' => $action['observation'],
            'registered_at' => now(),
        ]);
    }

    private function applyToZone(int $fdiCode, ?string $face, ToothCondition $condition): void
    {
        $tooth = $this->odontogram->teeth()->firstOrCreate(['fdi_code' => $fdiCode]);

        if ($face === null) {
            $tooth->update(['whole_condition_id' => $condition->id]);

            return;
        }

        OdontogramToothFace::updateOrCreate(
            ['odontogram_tooth_id' => $tooth->id, 'face' => $face],
            ['condition_id' => $condition->id]
        );
    }

    private function clearZone(int $fdiCode, ?string $face): void
    {
        $tooth = $this->odontogram->teeth()->firstOrCreate(['fdi_code' => $fdiCode]);

        if ($face === null) {
            $tooth->update(['whole_condition_id' => null]);

            return;
        }

        $tooth->faces()->where('face', $face)->delete();
    }

    public function openObservation(int $fdiCode, ?string $face, ?string $conditionCode): void
    {
        $this->observationFdiCode = $fdiCode;
        $this->observationFace = $face;
        $this->observationConditionCode = $conditionCode;
        $this->observationText = '';
        $this->showObservationModal = true;
    }

    public function submitObservation(): void
    {
        if (trim($this->observationText) === '') {
            return;
        }

        $this->stage(
            'observe',
            $this->observationFdiCode,
            $this->observationFace,
            $this->observationConditionCode,
            trim($this->observationText)
        );

        $this->resetObservationModal();
        unset($this->history);
    }

    public function cancelObservation(): void
    {
        $this->resetObservationModal();
    }

    private function resetObservationModal(): void
    {
        $this->showObservationModal = false;
        $this->observationFdiCode = null;
        $this->observationFace = null;
        $this->observationConditionCode = null;
        $this->observationText = '';
    }

    /** Etiqueta legible de la zona en el modal de observación. */
    public function observationZoneLabel(): string
    {
        return $this->observationFace
            ? (ToothCondition::FACE_LABELS[$this->observationFace] ?? $this->observationFace)
            : 'Pieza completa';
    }

    /** Etiqueta legible de una cara para la bitácora. */
    public function faceLabel(?string $face): string
    {
        return $face ? (ToothCondition::FACE_LABELS[$face] ?? $face) : 'Pieza completa';
    }

    public function setDentition(string $value): void
    {
        // Un paciente mayor de edad solo puede tener dentición adulta.
        if ($value === 'child' && $this->patient->isAdult()) {
            return;
        }

        $this->dentition = $value;
        $this->odontogram->update(['dentition' => $value]);
        unset($this->definitions, $this->teethByCode, $this->findings);
    }

    public function setNumberingSystem(string $value): void
    {
        $this->numberingSystem = $value;
        $this->odontogram->update(['numbering_system' => $value]);
    }

    /** Genera el SVG de una pieza (usado desde la vista, por cada diente del arco). */
    public function toothSvg(ToothDefinition $definition): string
    {
        $state = $this->stagedToothStates()->get($definition->fdi_code);

        return ToothSvgBuilder::render(
            def: $definition,
            shownNumber: ToothNumbering::display($definition->fdi_code, $this->dentition, $this->numberingSystem),
            faceMap: ($state['faces'] ?? []) + OdontogramTooth::DEFAULT_FACE_MAP,
            wholeCode: $state['whole'] ?? null,
            conditionColors: $this->conditionsByCode->map->color->all(),
            hasNote: $this->toothHasNote($definition->fdi_code),
        );
    }

    /** Persiste de forma atómica todos los cambios pendientes y registra la bitácora. */
    public function save(): void
    {
        if (count($this->pending) > 0) {
            DB::transaction(function (): void {
                foreach ($this->pending as $action) {
                    $this->applyPendingAction($action);
                }
            });
        }

        $this->pending = [];
        $this->odontogram->update([
            'notes' => $this->notes,
            'examined_at' => $this->examinedAt,
        ]);
        $this->odontogram->touch();
        $this->saveMessage = 'Guardado ✓';
        $this->dispatch('odontogram-saved');
        $this->dispatch('schedule-clear-save-message');

        unset($this->teethByCode, $this->findings, $this->history);
    }

    public function clearSaveMessage(): void
    {
        $this->saveMessage = '';
    }

    public function openToothNote(int $fdiCode): void
    {
        $tooth = $this->odontogram->teeth()->where('fdi_code', $fdiCode)->first();
        $this->toothNoteFdiCode = $fdiCode;
        $this->toothNoteText = $tooth?->notes ?? '';
        $this->showToothNoteModal = true;
    }

    public function saveToothNote(): void
    {
        if ($this->toothNoteFdiCode === null) {
            return;
        }

        $tooth = $this->odontogram->teeth()->firstOrCreate(['fdi_code' => $this->toothNoteFdiCode]);
        $tooth->update(['notes' => trim($this->toothNoteText) ?: null]);

        $this->showToothNoteModal = false;
        $this->toothNoteFdiCode = null;
        $this->toothNoteText = '';
        unset($this->teethByCode);
    }

    public function cancelToothNote(): void
    {
        $this->showToothNoteModal = false;
        $this->toothNoteFdiCode = null;
        $this->toothNoteText = '';
    }

    /** Check if a tooth has notes (for the visual indicator). */
    public function toothHasNote(int $fdiCode): bool
    {
        $tooth = $this->teethByCode->get($fdiCode);

        return $tooth && trim((string) $tooth->notes) !== '';
    }

    public function exportPdf(): void
    {
        $this->dispatch('open-pdf', url: route('odontogram.pdf', $this->odontogram));
    }

    public function render(): View
    {
        return view('livewire.odontogram-board');
    }
}
