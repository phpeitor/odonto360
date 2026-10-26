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

                $this->revert($entry);
            }

            $entry->forceFill(['voided_at' => now(), 'voided_by' => $user->id])->save();

            return $reverts;
        });
    }

    /**
     * Devuelve la zona a su estado anterior. En un borrado, la condición borrada es la anterior y
     * el registro guarda su sigla y estado; una aplicación siempre parte de una zona sin hallazgo.
     */
    private function revert(OdontogramTreatmentLog $entry): void
    {
        $previous = $entry->previous_condition_id ? ToothCondition::find($entry->previous_condition_id) : null;
        $restoresEntry = $previous !== null && $previous->id === $entry->condition_id;

        if ($entry->face !== null) {
            ZoneState::set(
                $entry->odontogram,
                $entry->fdi_code,
                $entry->face,
                $previous,
                $restoresEntry ? $entry->abbreviation : null,
                $restoresEntry ? $entry->state : null,
            );

            return;
        }

        if ($entry->end_fdi_code !== null) {
            if ($entry->action === 'apply') {
                ZoneState::removeSpan($entry->odontogram, $entry->condition_id, $entry->fdi_code, $entry->end_fdi_code);
            } elseif ($previous !== null) {
                ZoneState::addSpan($entry->odontogram, $previous, $entry->fdi_code, $entry->end_fdi_code, $entry->state);
            }

            return;
        }

        if ($entry->action === 'apply') {
            ZoneState::removeFinding($entry->odontogram, $entry->fdi_code, $entry->condition_id);
        } elseif ($previous !== null) {
            ZoneState::addFinding($entry->odontogram, $entry->fdi_code, $previous, $entry->abbreviation, $entry->state);
        }
    }

    /** En la pieza completa cada condición es una zona propia, y cada tramo de piezas también. */
    private function hasNewerChange(OdontogramTreatmentLog $entry): bool
    {
        return OdontogramTreatmentLog::query()
            ->where('odontogram_id', $entry->odontogram_id)
            ->where('fdi_code', $entry->fdi_code)
            ->when(
                $entry->face === null,
                fn ($query) => $query->whereNull('face')
                    ->where('condition_id', $entry->condition_id)
                    ->when(
                        $entry->end_fdi_code === null,
                        fn ($query) => $query->whereNull('end_fdi_code'),
                        fn ($query) => $query->where('end_fdi_code', $entry->end_fdi_code),
                    ),
                fn ($query) => $query->where('face', $entry->face),
            )
            ->whereIn('action', ['apply', 'clear'])
            ->whereNull('voided_at')
            ->where('id', '>', $entry->id)
            ->exists();
    }
}
