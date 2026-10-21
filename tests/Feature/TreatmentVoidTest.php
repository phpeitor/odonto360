<?php

use App\Domain\Odontogram\Exceptions\CannotVoidTreatment;
use App\Domain\Odontogram\TreatmentVoider;
use App\Domain\Odontogram\ZoneState;
use App\Livewire\DentalTreatment;
use App\Livewire\OdontogramBoard;
use App\Models\OdontogramTreatmentLog;
use App\Models\Patient;
use App\Models\ToothCondition;
use App\Models\User;
use Database\Seeders\ToothConditionSeeder;
use Database\Seeders\ToothDefinitionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ToothConditionSeeder::class, ToothDefinitionSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->user = User::factory()->create(['name' => 'Dra. Rosa Quispe']);
    $this->actingAs($this->user);
    $this->patient = Patient::factory()->create(['birth_date' => '1991-02-03']);
    $this->caries = ToothCondition::where('code', 'caries')->first();
});

/** Marca una zona con la condición y guarda, como lo haría el usuario en el tablero. */
function markAndSave(Patient $patient, string $condition, int $fdi, ?string $face): void
{
    Livewire::test(OdontogramBoard::class, ['patient' => $patient])
        ->set('activeCondition', $condition)
        ->call('selectZone', $fdi, $face ?? 'numero')
        ->call('save');
}

function zoneCondition(Patient $patient, int $fdi, ?string $face): ?int
{
    return ZoneState::conditionId($patient->odontogram()->first(), $fdi, $face);
}

it('records the action and the previous state of every change', function (): void {
    markAndSave($this->patient, 'caries', 11, 'v');
    markAndSave($this->patient, 'sano', 11, 'v');

    [$apply, $clear] = $this->patient->odontogram()->first()->treatmentLog()->orderBy('id')->get()->all();

    expect($apply->action)->toBe('apply')
        ->and($apply->previous_condition_id)->toBeNull()
        ->and($clear->action)->toBe('clear')
        ->and($clear->previous_condition_id)->toBe($this->caries->id);
});

it('voids a treatment, restoring the zone and keeping the history row', function (): void {
    markAndSave($this->patient, 'caries', 11, 'v');
    $entry = OdontogramTreatmentLog::first();

    expect(zoneCondition($this->patient, 11, 'v'))->toBe($this->caries->id);

    $reverted = app(TreatmentVoider::class)->void($entry, $this->user);

    expect($reverted)->toBeTrue()
        ->and(zoneCondition($this->patient, 11, 'v'))->toBeNull()
        ->and(OdontogramTreatmentLog::count())->toBe(1)
        ->and($entry->fresh()->isVoided())->toBeTrue()
        ->and($entry->fresh()->voided_by)->toBe($this->user->id);
});

it('only voids the latest change of a zone, from newest to oldest', function (): void {
    markAndSave($this->patient, 'caries', 11, 'v');
    markAndSave($this->patient, 'sano', 11, 'v');
    [$apply, $clear] = OdontogramTreatmentLog::orderBy('id')->get()->all();

    expect(fn () => app(TreatmentVoider::class)->void($apply, $this->user))
        ->toThrow(CannotVoidTreatment::class);

    app(TreatmentVoider::class)->void($clear, $this->user);
    expect(zoneCondition($this->patient, 11, 'v'))->toBe($this->caries->id);

    app(TreatmentVoider::class)->void($apply->fresh(), $this->user);
    expect(zoneCondition($this->patient, 11, 'v'))->toBeNull();

    expect(fn () => app(TreatmentVoider::class)->void($apply->fresh(), $this->user))
        ->toThrow(CannotVoidTreatment::class);
});

it('voids a whole-tooth treatment independently from its faces', function (): void {
    markAndSave($this->patient, 'caries', 11, 'v');
    markAndSave($this->patient, 'corona', 11, null);
    $corona = OdontogramTreatmentLog::orderBy('id')->get()->last();

    app(TreatmentVoider::class)->void($corona, $this->user);

    expect(zoneCondition($this->patient, 11, null))->toBeNull()
        ->and(zoneCondition($this->patient, 11, 'v'))->toBe($this->caries->id);
});

it('only marks as voided a legacy entry that has no recorded action', function (): void {
    markAndSave($this->patient, 'caries', 11, 'v');
    $entry = OdontogramTreatmentLog::first();
    $entry->update(['action' => null, 'previous_condition_id' => null]);

    $reverted = app(TreatmentVoider::class)->void($entry, $this->user);

    expect($reverted)->toBeFalse()
        ->and($entry->fresh()->isVoided())->toBeTrue()
        ->and(zoneCondition($this->patient, 11, 'v'))->toBe($this->caries->id);
});

it('voids from the patient treatments table with a notification', function (): void {
    markAndSave($this->patient, 'caries', 11, 'v');
    $entry = OdontogramTreatmentLog::first();

    Livewire::test(DentalTreatment::class, ['patient' => $this->patient])
        ->assertTableActionVisible('void', $entry)
        ->callTableAction('void', $entry)
        ->assertNotified('Tratamiento anulado')
        ->assertTableActionHidden('void', $entry->fresh());

    expect(zoneCondition($this->patient, 11, 'v'))->toBeNull();
});

it('shows the voided entries in the board history and no longer offers delete', function (): void {
    markAndSave($this->patient, 'caries', 11, 'v');
    app(TreatmentVoider::class)->void(OdontogramTreatmentLog::first(), $this->user);

    Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->assertSee('Anulado por Dra. Rosa Quispe');

    Livewire::test(DentalTreatment::class, ['patient' => $this->patient])
        ->assertTableActionDoesNotExist('delete');
});
