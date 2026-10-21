<?php

declare(strict_types=1);

namespace App\Domain\Odontogram;

use App\Domain\Odontogram\Exceptions\CannotVoidTreatment;
use App\Models\OdontogramTreatmentLog;
use App\Models\ToothCondition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Anula un registro de la bitácora sin borrarlo: lo marca como anulado (quién y cuándo) y devuelve
 * la zona a su estado anterior. Solo se puede anular el último cambio vigente de cada zona, para no
 * pisar cambios posteriores.
 */
final class TreatmentVoider
{
    /**
     * @return bool `true` si además se revirtió el estado de la zona; `false` si el registro es anterior
     *              al control de reversas (o es solo una observación) y únicamente se marcó como anulado.
     *
     * @throws CannotVoidTreatment
     */
    public function void(OdontogramTreatmentLog $entry, User $user): bool
    {
        return DB::transaction(function () use ($entry, $user): bool {
            $entry = OdontogramTreatmentLog::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->isVoided()) {
                throw new CannotVoidTreatment('Este registro ya fue anulado.');
            }

            $reverts = in_array($entry->action, ['apply', 'clear'], true);

            if ($reverts) {
                if ($this->hasNewerChange($entry)) {
                    throw new CannotVoidTreatment('Hay cambios posteriores en esta zona. Anúlalos primero, del más reciente al más antiguo.');
                }

                ZoneState::set(
                    $entry->odontogram,
                    $entry->fdi_code,
                    $entry->face,
                    $entry->previous_condition_id ? ToothCondition::find($entry->previous_condition_id) : null,
                );
            }

            $entry->forceFill(['voided_at' => now(), 'voided_by' => $user->id])->save();

            return $reverts;
        });
    }

    private function hasNewerChange(OdontogramTreatmentLog $entry): bool
    {
        return OdontogramTreatmentLog::query()
            ->where('odontogram_id', $entry->odontogram_id)
            ->where('fdi_code', $entry->fdi_code)
            ->when(
                $entry->face === null,
                fn ($query) => $query->whereNull('face'),
                fn ($query) => $query->where('face', $entry->face),
            )
            ->whereIn('action', ['apply', 'clear'])
            ->whereNull('voided_at')
            ->where('id', '>', $entry->id)
            ->exists();
    }
}
