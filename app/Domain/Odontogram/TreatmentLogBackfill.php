<?php

declare(strict_types=1);

namespace App\Domain\Odontogram;

use App\Models\Odontogram;
use App\Models\OdontogramTreatmentLog;
use App\Models\ToothCondition;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruye `action` y `previous_condition_id` en los registros de la bitácora anteriores al control
 * de reversas, reproduciendo la historia de cada zona en orden:
 *
 * - Si la condición del registro es igual al estado de la zona en ese momento, no pudo ser un «aplicar»:
 *   es una observación (lleva texto) o un borrado (no lleva texto).
 * - Si es distinta, es un «aplicar» y el estado previo es el que tenía la zona.
 *
 * Solo se guarda una zona si, al terminar la reproducción, coincide con su estado real; si faltan
 * registros (p. ej. se borraron a mano) esa zona se deja como estaba.
 *
 * Los registros que ya estaban anulados (con el comportamiento anterior, que no tocaba el odontograma)
 * se revierten ahora si aún son el último cambio vigente de su zona.
 */
final class TreatmentLogBackfill
{
    /** @return array{classified: int, reverted: int} */
    public function run(): array
    {
        $summary = ['classified' => 0, 'reverted' => 0];

        Odontogram::query()->each(function (Odontogram $odontogram) use (&$summary): void {
            DB::transaction(function () use ($odontogram, &$summary): void {
                $result = $this->process($odontogram);
                $summary['classified'] += $result['classified'];
                $summary['reverted'] += $result['reverted'];
            });
        });

        return $summary;
    }

    /** @return array{classified: int, reverted: int} */
    private function process(Odontogram $odontogram): array
    {
        $sanoId = ToothCondition::where('code', ToothCondition::CODE_SANO)->value('id');
        $entries = $odontogram->treatmentLog()->orderBy('id')->get();

        /** @var array<string, int|null> $state */
        $state = [];
        /** @var array<string, array<int, array{action: string, previous: int|null}>> $derived */
        $derived = [];

        foreach ($entries as $entry) {
            $key = $this->zoneKey($entry);
            $current = $state[$key] ?? null;

            if ($entry->action !== null) {
                $state[$key] = match ($entry->action) {
                    'apply' => $entry->condition_id,
                    'clear' => null,
                    default => $current,
                };

                continue;
            }

            if ($entry->condition_id === null) {
                continue;
            }

            if ($current === $entry->condition_id) {
                $isObservation = trim((string) $entry->observation) !== '';
                $derived[$key][$entry->id] = ['action' => $isObservation ? 'observe' : 'clear', 'previous' => $current];
                $state[$key] = $isObservation ? $current : null;

                continue;
            }

            $derived[$key][$entry->id] = ['action' => 'apply', 'previous' => $current];
            $state[$key] = $entry->condition_id;
        }

        $classified = 0;
        $savedIds = [];

        foreach ($derived as $key => $updates) {
            [$fdiCode, $face] = $this->parseKey($key);

            // «sano» no se guarda en el estado: una zona vacía y una zona «sano» son lo mismo.
            $actual = ZoneState::conditionId($odontogram, $fdiCode, $face);
            $replayed = $state[$key] ?? null;

            if ($replayed !== $actual && ! ($replayed === $sanoId && $actual === null)) {
                continue;
            }

            foreach ($updates as $id => $data) {
                OdontogramTreatmentLog::whereKey($id)->update([
                    'action' => $data['action'],
                    'previous_condition_id' => $data['previous'],
                ]);
                $savedIds[] = $id;
                $classified++;
            }
        }

        return ['classified' => $classified, 'reverted' => $this->revertVoided($odontogram, $savedIds)];
    }

    /** @param list<int> $classifiedIds */
    private function revertVoided(Odontogram $odontogram, array $classifiedIds): int
    {
        $reverted = 0;

        $voided = $odontogram->treatmentLog()
            ->whereIn('id', $classifiedIds)
            ->whereNotNull('voided_at')
            ->whereIn('action', ['apply', 'clear'])
            ->orderByDesc('id')
            ->get();

        foreach ($voided as $entry) {
            $hasNewer = $odontogram->treatmentLog()
                ->where('fdi_code', $entry->fdi_code)
                ->when($entry->face === null, fn ($q) => $q->whereNull('face'), fn ($q) => $q->where('face', $entry->face))
                ->whereIn('action', ['apply', 'clear'])
                ->whereNull('voided_at')
                ->where('id', '>', $entry->id)
                ->exists();

            $current = ZoneState::conditionId($odontogram, $entry->fdi_code, $entry->face);
            $expected = $entry->action === 'apply' ? $entry->condition_id : null;

            if ($hasNewer || $current !== $expected) {
                continue;
            }

            ZoneState::set(
                $odontogram,
                $entry->fdi_code,
                $entry->face,
                $entry->previous_condition_id ? ToothCondition::find($entry->previous_condition_id) : null,
            );
            $reverted++;
        }

        return $reverted;
    }

    private function zoneKey(OdontogramTreatmentLog $entry): string
    {
        return $entry->fdi_code.'|'.($entry->face ?? '');
    }

    /** @return array{0: int, 1: string|null} */
    private function parseKey(string $key): array
    {
        [$fdi, $face] = explode('|', $key);

        return [(int) $fdi, $face === '' ? null : $face];
    }
}
