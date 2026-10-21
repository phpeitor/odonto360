<?php

use App\Domain\Odontogram\TreatmentLogBackfill;
use App\Domain\Odontogram\ZoneState;
use App\Models\Odontogram;
use App\Models\OdontogramTreatmentLog;
use App\Models\Patient;
use App\Models\ToothCondition;
use Database\Seeders\ToothConditionSeeder;
use Database\Seeders\ToothDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ToothConditionSeeder::class, ToothDefinitionSeeder::class]);
    $this->odontogram = Patient::factory()->create()->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $this->caries = ToothCondition::where('code', 'caries')->first();
});

/** Inserta un registro «antiguo»: sin acción ni estado previo. */
function legacyEntry(Odontogram $odontogram, int $fdi, ?string $face, ToothCondition $condition, ?string $observation = null, bool $voided = false): OdontogramTreatmentLog
{
    return OdontogramTreatmentLog::create([
        'odontogram_id' => $odontogram->id,
        'fdi_code' => $fdi,
        'face' => $face,
        'condition_id' => $condition->id,
        'observation' => $observation,
        'registered_at' => now(),
        'voided_at' => $voided ? now() : null,
    ]);
}

it('classifies legacy entries as apply, clear and observe by replaying each zone', function (): void {
    ZoneState::set($this->odontogram, 11, 'v', null);
    $apply = legacyEntry($this->odontogram, 11, 'v', $this->caries);
    $observe = legacyEntry($this->odontogram, 11, 'v', $this->caries, 'control en 15 días');
    $clear = legacyEntry($this->odontogram, 11, 'v', $this->caries);
    $reapply = legacyEntry($this->odontogram, 11, 'v', $this->caries);
    ZoneState::set($this->odontogram, 11, 'v', $this->caries);

    $summary = (new TreatmentLogBackfill)->run();

    expect($summary['classified'])->toBe(4)
        ->and($apply->fresh()->only(['action', 'previous_condition_id']))->toBe(['action' => 'apply', 'previous_condition_id' => null])
        ->and($observe->fresh()->action)->toBe('observe')
        ->and($clear->fresh()->only(['action', 'previous_condition_id']))->toBe(['action' => 'clear', 'previous_condition_id' => $this->caries->id])
        ->and($reapply->fresh()->only(['action', 'previous_condition_id']))->toBe(['action' => 'apply', 'previous_condition_id' => null]);
});

it('reverts the zone of a legacy entry that was voided under the old behaviour', function (): void {
    legacyEntry($this->odontogram, 12, 'o', $this->caries, voided: true);
    ZoneState::set($this->odontogram, 12, 'o', $this->caries);

    $summary = (new TreatmentLogBackfill)->run();

    expect($summary['reverted'])->toBe(1)
        ->and(ZoneState::conditionId($this->odontogram, 12, 'o'))->toBeNull();
});

it('does not revert a voided entry when a newer change is still valid', function (): void {
    $obturacion = ToothCondition::where('code', 'obturacion')->first();
    legacyEntry($this->odontogram, 12, 'o', $this->caries, voided: true);
    legacyEntry($this->odontogram, 12, 'o', $this->caries);
    legacyEntry($this->odontogram, 12, 'o', $this->caries);
    legacyEntry($this->odontogram, 12, 'o', $obturacion);
    ZoneState::set($this->odontogram, 12, 'o', $obturacion);

    (new TreatmentLogBackfill)->run();

    expect(ZoneState::conditionId($this->odontogram, 12, 'o'))->toBe($obturacion->id);
});

it('leaves a zone untouched when the history does not explain its current state', function (): void {
    // Quedó solo el registro de «borrar» (el «aplicar» se eliminó a mano): la zona está vacía,
    // pero reproducida sola la historia la dejaría con caries.
    $orphan = legacyEntry($this->odontogram, 21, 'v', $this->caries);

    $summary = (new TreatmentLogBackfill)->run();

    expect($summary['classified'])->toBe(0)
        ->and($orphan->fresh()->action)->toBeNull()
        ->and(ZoneState::conditionId($this->odontogram, 21, 'v'))->toBeNull();
});

it('is idempotent', function (): void {
    legacyEntry($this->odontogram, 12, 'o', $this->caries, voided: true);
    ZoneState::set($this->odontogram, 12, 'o', $this->caries);

    (new TreatmentLogBackfill)->run();
    $second = (new TreatmentLogBackfill)->run();

    expect($second)->toBe(['classified' => 0, 'reverted' => 0]);
});
