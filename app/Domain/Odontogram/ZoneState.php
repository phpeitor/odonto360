<?php

declare(strict_types=1);

namespace App\Domain\Odontogram;

use App\Models\Odontogram;
use App\Models\OdontogramToothFace;
use App\Models\OdontogramToothFinding;
use App\Models\ToothCondition;

/**
 * Lectura y escritura del estado persistido de una zona del odontograma:
 * - Una cara (`v`, `o`, `p`, `m`, `d`): un hallazgo por cara.
 * - Un hallazgo de pieza completa: una pieza puede tener varios (NTS 188, 5.16), uno por condición.
 *
 * Con `$face === null`, `conditionId()` y `set()` operan sobre la antigua columna `whole_condition_id`;
 * solo la usan los reconstructores de datos históricos (TreatmentLogBackfill, OdontogramConsolidator).
 */
final class ZoneState
{
    public static function conditionId(Odontogram $odontogram, int $fdiCode, ?string $face): ?int
    {
        $tooth = $odontogram->teeth()->where('fdi_code', $fdiCode)->first();

        if ($tooth === null) {
            return null;
        }

        if ($face === null) {
            return $tooth->whole_condition_id;
        }

        return $tooth->faces()->where('face', $face)->value('condition_id');
    }

    /** Aplica una condición a la zona; con `null` la deja sin condición. */
    public static function set(
        Odontogram $odontogram,
        int $fdiCode,
        ?string $face,
        ?ToothCondition $condition,
        ?string $abbreviation = null,
        ?string $state = null,
    ): void {
        $tooth = $odontogram->teeth()->firstOrCreate(['fdi_code' => $fdiCode]);

        if ($face === null) {
            $tooth->update(['whole_condition_id' => $condition?->id]);

            return;
        }

        if ($condition === null) {
            $tooth->faces()->where('face', $face)->delete();

            return;
        }

        OdontogramToothFace::updateOrCreate(
            ['odontogram_tooth_id' => $tooth->id, 'face' => $face],
            ['condition_id' => $condition->id, 'abbreviation' => $abbreviation, 'state' => $state ?? $condition->defaultState()]
        );
    }

    public static function hasFinding(Odontogram $odontogram, int $fdiCode, int $conditionId): bool
    {
        return OdontogramToothFinding::query()
            ->where('condition_id', $conditionId)
            ->whereHas('tooth', fn ($query) => $query->where('odontogram_id', $odontogram->id)->where('fdi_code', $fdiCode))
            ->exists();
    }

    /** Registra (o actualiza) un hallazgo de pieza completa. */
    public static function addFinding(
        Odontogram $odontogram,
        int $fdiCode,
        ToothCondition $condition,
        ?string $abbreviation,
        ?string $state,
    ): void {
        $tooth = $odontogram->teeth()->firstOrCreate(['fdi_code' => $fdiCode]);

        OdontogramToothFinding::updateOrCreate(
            ['odontogram_tooth_id' => $tooth->id, 'condition_id' => $condition->id],
            ['abbreviation' => $abbreviation, 'state' => $state ?? $condition->defaultState()]
        );
    }

    public static function removeFinding(Odontogram $odontogram, int $fdiCode, int $conditionId): void
    {
        $tooth = $odontogram->teeth()->where('fdi_code', $fdiCode)->first();

        $tooth?->findings()->where('condition_id', $conditionId)->delete();
    }
}
