<?php

namespace App\Livewire;

use App\Domain\Odontogram\DentitionLayout;
use App\Domain\Odontogram\ToothNumbering;
use App\Domain\Odontogram\ToothSvgBuilder;
use App\Domain\Odontogram\ZoneState;
use App\Models\Odontogram;
use App\Models\OdontogramTooth;
use App\Models\OdontogramToothFace;
use App\Models\OdontogramToothFinding;
use App\Models\Patient;
use App\Models\ToothCondition;
use App\Models\ToothDefinition;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OdontogramBoard extends Component
{
    private const NUMBERING_SYSTEMS = ['fdi', 'universal'];

    public Patient $patient;

    public ?Odontogram $odontogram = null;

    public string $dentition = 'adult';

    public string $numberingSystem = 'fdi';

    public ?string $activeCondition = null;

    /** Sigla y estado (bueno/malo) con los que se registra la condición activa. */
    public ?string $activeAbbreviation = null;

    public ?string $activeState = null;

    /** Observaciones del odontograma (NTS 188, 5.15). */
    public ?string $notes = null;

    /** Especificaciones: hallazgos o características que no entran en los recuadros (NTS 188, 5.14). */
    public ?string $specifications = null;

    public ?string $examinedAt = null;

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
        $this->dentition = $patient->defaultDentition();

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
        $this->specifications = $this->odontogram->specifications;
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

    /** Paleta agrupada por categoría (patología, restauraciones, anomalías…). */
    #[Computed]
    public function conditionGroups(): Collection
    {
        return $this->conditions->groupBy('category');
    }

    #[Computed]
    public function activeConditionModel(): ?ToothCondition
    {
        return $this->activeCondition ? ($this->conditionsByCode[$this->activeCondition] ?? null) : null;
    }

    /**
     * Filas de piezas por arcada (en la mixta, dos filas por arcada).
     *
     * @return array{upper: list<Collection<int, ToothDefinition>>, lower: list<Collection<int, ToothDefinition>>}
     */
    #[Computed]
    public function archRows(): array
    {
        return DentitionLayout::rows($this->dentition);
    }

    #[Computed]
    public function teethByCode(): Collection
    {
        return $this->odontogram
            ->teeth()
            ->with(['faces.condition', 'findings.condition'])
            ->get()
            ->keyBy('fdi_code');
    }

    /**
     * Hallazgos actuales (BD + pendientes) para el listado bajo el odontograma.
     *
     * @return Collection<int, array{fdi_code: int, face: ?string, face_label: string, code: string, label: string, abbreviation: ?string, state: string, color: string}>
     */
    #[Computed]
    public function findings(): Collection
    {
        $items = [];

        foreach ($this->stagedToothStates()->sortKeys() as $fdiCode => $state) {
            $zones = [];

            foreach ($state['findings'] as $code => $finding) {
                $zones[] = [null, $code, $finding];
            }

            foreach ($state['faces'] as $face => $finding) {
                $zones[] = [$face, $finding['code'], $finding];
            }

            foreach ($zones as [$face, $code, $finding]) {
                $condition = $this->conditionsByCode[$code] ?? null;

                if (! $condition || $code === ToothCondition::CODE_SANO) {
                    continue;
                }

                $items[] = [
                    'fdi_code' => $fdiCode,
                    'face' => $face,
                    'face_label' => $this->faceLabel($face),
                    'code' => $code,
                    'label' => $condition->label,
                    'abbreviation' => $finding['abbreviation'],
                    'state' => $finding['state'],
                    'color' => ToothCondition::colorForState($finding['state']),
                ];
            }
        }

        return collect($items);
    }

    #[Computed]
    public function history(): Collection
    {
        $entries = $this->odontogram
            ->treatmentLog()
            ->with(['condition', 'user', 'voidedBy'])
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->get();

        foreach ($this->pending as $action) {
            $condition = $action['condition_code'] !== null
                ? ($this->conditionsByCode[$action['condition_code']] ?? null)
                : null;

            $entries->prepend((object) [
                'fdi_code' => $action['fdi_code'],
                'face' => $action['face'],
                'action' => $action['action'],
                'voided_at' => null,
                'condition' => $condition,
                'abbreviation' => $action['abbreviation'],
                'state' => $action['state'],
                'observation' => $action['observation'],
                'user' => Auth::user(),
                'registered_at' => now(),
                'pending' => true,
            ]);
        }

        return $entries;
    }

    /** Selecciona una condición de la paleta, con su primera sigla y su estado por defecto. */
    public function pickCondition(string $code): void
    {
        $condition = $this->conditionsByCode[$code] ?? null;

        if ($condition === null || $this->activeCondition === $code) {
            $this->activeCondition = null;
            $this->activeAbbreviation = null;
            $this->activeState = null;

            return;
        }

        $this->activeCondition = $code;
        $this->activeAbbreviation = $condition->abbreviationOptions()[0] ?? null;
        $this->activeState = $condition->defaultState();
    }

    public function pickAbbreviation(string $abbreviation): void
    {
        if ($this->activeConditionModel?->allowsAbbreviation($abbreviation)) {
            $this->activeAbbreviation = $abbreviation;
        }
    }

    /** Buen estado (azul) o mal estado (rojo), solo si la condición admite ambos. */
    public function pickState(string $state): void
    {
        if ($this->activeConditionModel?->allowsState($state)) {
            $this->activeState = $state;
        }
    }

    /**
     * Marca una cara o la pieza completa con la condición activa.
     * $face === 'numero' => pieza completa (equivalente al click en el número).
     *
     * El marcado se acumula en `pending` y solo se persiste al pulsar "Guardar":
     * - Cara sin hallazgo o pieza sin esa condición: encola la aplicación.
     * - Zona ya registrada: no se re-marca; se abre el modal para agregar una observación.
     * - Condición 'sano': encola el borrado de la cara, o de todos los hallazgos de la pieza
     *   si se hace clic en el número.
     */
    public function selectZone(int $fdiCode, string $face): void
    {
        $condition = $this->activeConditionModel;

        if (! $condition) {
            return;
        }

        $zoneFace = $face === 'numero' ? null : $face;

        // Las condiciones de pieza completa (corona, ausente, movilidad…) aplican
        // aunque el clic se haga sobre el cuerpo del diente en vez del número.
        if ($zoneFace !== null && $condition->appliesToTooth() && ! $condition->appliesToFace()) {
            $zoneFace = null;
        }

        $state = $this->stagedToothStates()->get($fdiCode, ['faces' => [], 'findings' => []]);

        if ($condition->code === ToothCondition::CODE_SANO) {
            if ($zoneFace !== null) {
                $this->stageClear($fdiCode, $zoneFace, $state['faces'][$zoneFace] ?? null);
            } else {
                foreach ($state['findings'] as $code => $finding) {
                    $this->stageClear($fdiCode, null, ['code' => $code] + $finding);
                }
            }
        } elseif ($zoneFace !== null && isset($state['faces'][$zoneFace])) {
            $this->openObservation($fdiCode, $zoneFace, $state['faces'][$zoneFace]['code']);
        } elseif ($zoneFace === null && isset($state['findings'][$condition->code])) {
            $this->openObservation($fdiCode, null, $condition->code);
        } elseif ($this->conditionAppliesToZone($condition, $zoneFace)) {
            $this->stage(
                'apply',
                $fdiCode,
                $zoneFace,
                $condition->code,
                $condition->allowsAbbreviation($this->activeAbbreviation) ? $this->activeAbbreviation : ($condition->abbreviationOptions()[0] ?? null),
                $condition->allowsState($this->activeState) ? $this->activeState : $condition->defaultState(),
            );
        }

        unset($this->findings, $this->history);
    }

    /** Quita un hallazgo desde el listado de hallazgos (encola su borrado). */
    public function removeFinding(int $fdiCode, ?string $face, string $conditionCode): void
    {
        $state = $this->stagedToothStates()->get($fdiCode);

        $finding = $face === null
            ? (isset($state['findings'][$conditionCode]) ? ['code' => $conditionCode] + $state['findings'][$conditionCode] : null)
            : ($state['faces'][$face] ?? null);

        if ($finding !== null && $finding['code'] === $conditionCode) {
            $this->stageClear($fdiCode, $face, $finding);
            unset($this->findings, $this->history);
        }
    }

    /** @param array{code: string, abbreviation: ?string, state: string}|null $finding */
    private function stageClear(int $fdiCode, ?string $face, ?array $finding): void
    {
        if ($finding !== null) {
            $this->stage('clear', $fdiCode, $face, $finding['code'], $finding['abbreviation'], $finding['state']);
        }
    }

    /** Añade una acción a la cola de cambios pendientes (aún sin persistir). */
    private function stage(
        string $action,
        int $fdiCode,
        ?string $face,
        ?string $conditionCode,
        ?string $abbreviation = null,
        ?string $state = null,
        ?string $observation = null,
    ): void {
        $this->pending[] = [
            'fdi_code' => $fdiCode,
            'face' => $face,
            'action' => $action,
            'condition_code' => $conditionCode,
            'abbreviation' => $abbreviation,
            'state' => $state,
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
     * - faces: un hallazgo por cara.
     * - findings: hallazgos de pieza completa, por código de condición.
     *
     * @return Collection<int, array{faces: array<string, array{code: string, abbreviation: ?string, state: string}>, findings: array<string, array{abbreviation: ?string, state: string}>}>
     */
    private function stagedToothStates(): Collection
    {
        $states = $this->teethByCode->mapWithKeys(function (OdontogramTooth $tooth) {
            return [$tooth->fdi_code => [
                'faces' => $tooth->faces->mapWithKeys(fn (OdontogramToothFace $face) => [$face->face => [
                    'code' => $face->condition->code,
                    'abbreviation' => $face->abbreviation,
                    'state' => $face->state ?? $face->condition->defaultState(),
                ]])->all(),
                'findings' => $tooth->findings->mapWithKeys(fn (OdontogramToothFinding $finding) => [$finding->condition->code => [
                    'abbreviation' => $finding->abbreviation,
                    'state' => $finding->state,
                ]])->all(),
            ]];
        });

        foreach ($this->pending as $action) {
            $state = $states->get($action['fdi_code'], ['faces' => [], 'findings' => []]);
            $face = $action['face'];
            $code = $action['condition_code'];

            if ($action['action'] === 'apply') {
                $finding = ['abbreviation' => $action['abbreviation'], 'state' => $action['state']];

                if ($face === null) {
                    $state['findings'][$code] = $finding;
                } else {
                    $state['faces'][$face] = ['code' => $code] + $finding;
                }
            } elseif ($action['action'] === 'clear') {
                if ($face === null) {
                    unset($state['findings'][$code]);
                } else {
                    unset($state['faces'][$face]);
                }
            }

            $states->put($action['fdi_code'], $state);
        }

        return $states;
    }

    /** Devuelve false si la condición no aplica a esa zona (cara vs pieza completa). */
    private function conditionAppliesToZone(ToothCondition $condition, ?string $face): bool
    {
        return $face === null ? $condition->appliesToTooth() : $condition->appliesToFace();
    }

    /** Persiste una acción pendiente sobre caras/hallazgos de pieza y la registra en la bitácora. */
    private function applyPendingAction(array $action): void
    {
        $condition = $action['condition_code'] !== null
            ? ToothCondition::where('code', $action['condition_code'])->first()
            : null;

        $fdiCode = $action['fdi_code'];
        $face = $action['face'];

        if ($face !== null) {
            $previousConditionId = ZoneState::conditionId($this->odontogram, $fdiCode, $face);

            if ($action['action'] === 'apply') {
                ZoneState::set($this->odontogram, $fdiCode, $face, $condition, $action['abbreviation'], $action['state']);
            } elseif ($action['action'] === 'clear') {
                ZoneState::set($this->odontogram, $fdiCode, $face, null);
            }
        } else {
            // En la pieza completa cada condición es su propia zona: antes de aplicarla no existía.
            $previousConditionId = $condition && ZoneState::hasFinding($this->odontogram, $fdiCode, $condition->id)
                ? $condition->id
                : null;

            if ($action['action'] === 'apply' && $condition) {
                ZoneState::addFinding($this->odontogram, $fdiCode, $condition, $action['abbreviation'], $action['state']);
            } elseif ($action['action'] === 'clear' && $condition) {
                ZoneState::removeFinding($this->odontogram, $fdiCode, $condition->id);
            }
        }

        $this->odontogram->treatmentLog()->create([
            'fdi_code' => $fdiCode,
            'face' => $face,
            'action' => $action['action'],
            'condition_id' => $condition?->id,
            'abbreviation' => $action['abbreviation'] ?? null,
            'state' => $action['state'] ?? null,
            'previous_condition_id' => $previousConditionId,
            'observation' => $action['observation'],
            'user_id' => Auth::id(),
            'registered_at' => now(),
        ]);
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
            observation: trim($this->observationText),
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
        // El valor llega del cliente: solo se aceptan denticiones conocidas y permitidas por la edad
        // (mayor de edad: solo adulta; menor de 6 años: solo infantil; mixta de 6 a 17 años).
        if (! in_array($value, DentitionLayout::DENTITIONS, true) || ! $this->patient->allowsDentition($value)) {
            return;
        }

        $this->dentition = $value;
        $this->odontogram->update(['dentition' => $value]);
        unset($this->archRows, $this->teethByCode, $this->findings);
    }

    public function setNumberingSystem(string $value): void
    {
        if (! in_array($value, self::NUMBERING_SYSTEMS, true)) {
            return;
        }

        $this->numberingSystem = $value;
        $this->odontogram->update(['numbering_system' => $value]);
    }

    /** Genera el SVG de una pieza (usado desde la vista, por cada diente del arco). */
    public function toothSvg(ToothDefinition $definition): string
    {
        $state = $this->stagedToothStates()->get($definition->fdi_code, ['faces' => [], 'findings' => []]);
        $glyph = fn (string $code): ?string => $this->conditionsByCode->get($code)?->glyph;

        return ToothSvgBuilder::render(
            def: $definition,
            shownNumber: ToothNumbering::display($definition->fdi_code, $definition->dentition, $this->numberingSystem),
            faces: array_map(fn (array $finding): array => $finding + ['glyph' => $glyph($finding['code'])], $state['faces']),
            findings: collect($state['findings'])->map(fn (array $finding, string $code): array => $finding + ['glyph' => $glyph($code)])->all(),
            hasNote: $this->toothHasNote($definition->fdi_code),
        );
    }

    /** Persiste de forma atómica todos los cambios pendientes y registra la bitácora. */
    public function save(): void
    {
        $changes = count($this->pending);

        if ($changes > 0) {
            DB::transaction(function (): void {
                foreach ($this->pending as $action) {
                    $this->applyPendingAction($action);
                }
            });
        }

        $this->pending = [];
        $this->odontogram->update([
            'notes' => $this->notes,
            'specifications' => $this->specifications,
            'examined_at' => $this->examinedAt,
        ]);
        $this->odontogram->touch();
        $this->dispatch('odontogram-saved');

        Notification::make()
            ->title('Odontograma guardado')
            ->body($changes > 0 ? "Se registraron {$changes} cambio(s) en el historial." : 'Especificaciones, observaciones y fecha de examen actualizadas.')
            ->success()
            ->duration(4000)
            ->send();

        unset($this->teethByCode, $this->findings, $this->history);
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

        Notification::make()
            ->title("Nota de la pieza {$this->toothNoteFdiCode} guardada")
            ->success()
            ->duration(3000)
            ->send();

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
