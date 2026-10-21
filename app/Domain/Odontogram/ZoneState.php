<?php

declare(strict_types=1);

namespace App\Domain\Odontogram;

use App\Models\Odontogram;
use App\Models\OdontogramToothFace;
use App\Models\ToothCondition;

/**
 * Lectura y escritura del estado persistido de una zona del odontograma:
 * una cara (`v`, `o`, `p`, `m`, `d`) o la pieza completa (`$face === null`).
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
    public static function set(Odontogram $odontogram, int $fdiCode, ?string $face, ?ToothCondition $condition): void
    {
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
            ['condition_id' => $condition->id]
        );
    }
}
