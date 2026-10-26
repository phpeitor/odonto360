<?php

use App\Domain\Odontogram\OdontogramConsolidator;
use App\Livewire\OdontogramBoard;
use App\Models\Odontogram;
use App\Models\OdontogramTooth;
use App\Models\OdontogramToothFace;
use App\Models\Patient;
use App\Models\ToothCondition;
use Database\Seeders\ToothDefinitionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // odontogram_teeth.fdi_code referencia a tooth_definitions.
    $this->seed(ToothDefinitionSeeder::class);
});

function makeCondition(string $code, string $label, string $target, string $allowedStates = 'ambos', ?array $abbreviations = null): ToothCondition
{
    return ToothCondition::create([
        'code' => $code,
        'label' => $label,
        'target' => $target,
        'category' => 'patologia',
        'allowed_states' => $allowedStates,
        'abbreviations' => $abbreviations,
        'sort_order' => 10,
    ]);
}

it('reutiliza el único odontograma del paciente al montar el tablero dos veces', function (): void {
    $patient = Patient::create(['first_name' => 'Ana', 'last_name' => 'Lopez']);

    Livewire::test(OdontogramBoard::class, ['patient' => $patient]);
    Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    expect(Odontogram::where('patient_id', $patient->id)->count())->toBe(1);
});

it('consolida los odontogramas duplicados conservando el historial', function (): void {
    Schema::table('odontograms', function (Blueprint $table) {
        $table->dropUnique('odontograms_patient_id_unique');
    });

    $patient = Patient::create(['first_name' => 'Bruno', 'last_name' => 'Diaz']);
    $caries = makeCondition('caries', 'Caries', 'face');
    $corona = makeCondition('corona', 'Corona', 'tooth');

    $first = Odontogram::create(['patient_id' => $patient->id, 'dentition' => 'adult', 'numbering_system' => 'fdi']);
    $firstTooth = OdontogramTooth::create(['odontogram_id' => $first->id, 'fdi_code' => 11, 'whole_condition_id' => $corona->id]);
    OdontogramToothFace::create(['odontogram_tooth_id' => $firstTooth->id, 'face' => 'v', 'condition_id' => $caries->id]);

    $second = Odontogram::create(['patient_id' => $patient->id, 'dentition' => 'adult', 'numbering_system' => 'fdi']);
    $secondTooth = OdontogramTooth::create(['odontogram_id' => $second->id, 'fdi_code' => 21]);
    OdontogramToothFace::create(['odontogram_tooth_id' => $secondTooth->id, 'face' => 'o', 'condition_id' => $caries->id]);

    (new OdontogramConsolidator)->consolidate();

    expect(Odontogram::where('patient_id', $patient->id)->count())->toBe(1);

    $survivor = $patient->odontogram;

    // El historial conserva los tratamientos del odontograma fusionado
    // (corona + caries de la pieza 11); el del superviviente es estado actual.
    expect($survivor->treatmentLog()->count())->toBe(2);

    // El estado actual fusiona ambas piezas.
    expect($survivor->teeth()->where('fdi_code', 11)->first()->whole_condition_id)->toBe($corona->id);
    expect($survivor->teeth()->where('fdi_code', 21)->first())->not->toBeNull();
});

it('no re-marca una zona ya tratada y registra una observación', function (): void {
    $patient = Patient::create(['first_name' => 'Carla', 'last_name' => 'Ruiz']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $caries = makeCondition('caries', 'Caries', 'face');
    makeCondition('obturacion', 'Obturación', 'face');

    $tooth = OdontogramTooth::create(['odontogram_id' => $odontogram->id, 'fdi_code' => 11]);
    $face = OdontogramToothFace::create(['odontogram_tooth_id' => $tooth->id, 'face' => 'v', 'condition_id' => $caries->id]);

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    $component->set('activeCondition', 'obturacion')
        ->call('selectZone', 11, 'v');

    // El estado no cambia y aún no hay entrada en la bitácora.
    expect($face->fresh()->condition_id)->toBe($caries->id);
    expect($odontogram->treatmentLog()->count())->toBe(0);

    // Se abre el modal de observación con la zona detectada.
    $component->assertSet('showObservationModal', true)
        ->assertSet('observationFdiCode', 11)
        ->assertSet('observationFace', 'v')
        ->assertSet('observationConditionCode', 'caries');

    $component->set('observationText', 'Se re-trató la pieza por dolor')
        ->call('submitObservation')
        ->call('save');

    expect($odontogram->treatmentLog()->count())->toBe(1);

    $entry = $odontogram->treatmentLog()->first();
    expect($entry->face)->toBe('v')
        ->and($entry->observation)->toBe('Se re-trató la pieza por dolor')
        ->and($entry->condition_id)->toBe($caries->id);

    // El estado sigue sin cambios.
    expect($face->fresh()->condition_id)->toBe($caries->id);
});

it('registra en la bitácora un tratamiento nuevo sobre una zona sin tratar', function (): void {
    $patient = Patient::create(['first_name' => 'Daniel', 'last_name' => 'Torres']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $caries = makeCondition('caries', 'Caries', 'face');

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    $component->set('activeCondition', 'caries')
        ->call('selectZone', 11, 'v');

    // Aún no se persiste nada: el marcado queda pendiente hasta pulsar "Guardar".
    expect($odontogram->teeth()->count())->toBe(0);
    expect($component->get('pending'))->toHaveCount(1);

    $component->call('save');

    $tooth = $odontogram->teeth()->where('fdi_code', 11)->first();

    expect($tooth)->not->toBeNull();
    expect($tooth->faces()->where('face', 'v')->first()->condition_id)->toBe($caries->id);
    expect($odontogram->treatmentLog()->count())->toBe(1);
    expect($odontogram->treatmentLog()->first()->condition_id)->toBe($caries->id);
});

it('borra una zona tratada con sano y lo registra en la bitácora', function (): void {
    $patient = Patient::create(['first_name' => 'Elena', 'last_name' => 'Vega']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $corona = makeCondition('corona', 'Corona', 'tooth');
    makeCondition('sano', 'Sano / Borrar', 'both');

    $tooth = OdontogramTooth::create(['odontogram_id' => $odontogram->id, 'fdi_code' => 11]);
    $tooth->findings()->create(['condition_id' => $corona->id, 'abbreviation' => 'CMC', 'state' => 'bueno']);

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    $component->call('pickCondition', 'sano')
        ->call('selectZone', 11, 'numero');

    // El borrado queda pendiente hasta pulsar "Guardar".
    expect($tooth->findings()->count())->toBe(1);
    expect($odontogram->treatmentLog()->count())->toBe(0);

    $component->call('save');

    expect($tooth->findings()->count())->toBe(0);
    expect($odontogram->treatmentLog()->count())->toBe(1);

    // El registro del borrado conserva la sigla y el estado de lo que se quitó.
    $entry = $odontogram->treatmentLog()->first();
    expect($entry->action)->toBe('clear')
        ->and($entry->condition_id)->toBe($corona->id)
        ->and($entry->abbreviation)->toBe('CMC')
        ->and($entry->state)->toBe('bueno');
});

it('aplica una condición de pieza completa al clicar el cuerpo del diente', function (): void {
    $patient = Patient::create(['first_name' => 'Fabián', 'last_name' => 'Mendoza']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $ausente = makeCondition('ausente', 'Pieza ausente', 'tooth', 'bueno', ['DNE', 'DEX', 'DAO']);

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    $component->call('pickCondition', 'ausente')
        ->call('pickAbbreviation', 'DEX')
        ->call('selectZone', 11, 'v')
        ->call('save');

    $tooth = $odontogram->teeth()->where('fdi_code', 11)->first();

    expect($tooth)->not->toBeNull();
    expect($tooth->findings()->where('condition_id', $ausente->id)->first())
        ->abbreviation->toBe('DEX')
        ->state->toBe('bueno');
    expect($tooth->faces()->count())->toBe(0);
    expect($odontogram->treatmentLog()->count())->toBe(1);
    expect($odontogram->treatmentLog()->first()->face)->toBeNull()
        ->and($odontogram->treatmentLog()->first()->condition_id)->toBe($ausente->id);
});

it('abre el modal al re-clicar el cuerpo de una pieza ya tratada', function (): void {
    $patient = Patient::create(['first_name' => 'Gabriela', 'last_name' => 'Núñez']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $ausente = makeCondition('ausente', 'Pieza ausente', 'tooth', 'bueno', ['DNE', 'DEX', 'DAO']);
    $tooth = OdontogramTooth::create(['odontogram_id' => $odontogram->id, 'fdi_code' => 11]);
    $tooth->findings()->create(['condition_id' => $ausente->id, 'abbreviation' => 'DAO', 'state' => 'bueno']);

    Livewire::test(OdontogramBoard::class, ['patient' => $patient])
        ->call('pickCondition', 'ausente')
        ->call('selectZone', 11, 'v')
        ->assertSet('showObservationModal', true)
        ->assertSet('observationConditionCode', 'ausente');

    expect($odontogram->treatmentLog()->count())->toBe(0);
    expect($tooth->findings()->first()->abbreviation)->toBe('DAO');
});

it('deshace la última acción pendiente', function (): void {
    $patient = Patient::create(['first_name' => 'Hugo', 'last_name' => 'Salas']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $caries = makeCondition('caries', 'Caries', 'face');
    makeCondition('obturacion', 'Obturación', 'face');

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    $component->set('activeCondition', 'caries')
        ->call('selectZone', 11, 'v')
        ->assertSet('pending', [[
            'fdi_code' => 11,
            'face' => 'v',
            'action' => 'apply',
            'condition_code' => 'caries',
            'abbreviation' => null,
            'state' => 'bueno',
            'observation' => null,
        ]]);

    $component->set('activeCondition', 'obturacion')
        ->call('selectZone', 12, 'o')
        ->assertSet('pending', fn ($p) => count($p) === 2);

    $component->call('undoPending')
        ->assertSet('pending', fn ($p) => count($p) === 1);

    $component->call('undoPending')
        ->assertSet('pending', fn ($p) => count($p) === 0);

    // Undo on empty list does nothing.
    $component->call('undoPending')
        ->assertSet('pending', fn ($p) => count($p) === 0);
});

it('descarta todos los cambios pendientes', function (): void {
    $patient = Patient::create(['first_name' => 'Irene', 'last_name' => 'Ramos']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $caries = makeCondition('caries', 'Caries', 'face');

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    $component->set('activeCondition', 'caries')
        ->call('selectZone', 11, 'v')
        ->call('selectZone', 12, 'o')
        ->assertSet('pending', fn ($p) => count($p) === 2);

    $component->call('discardPending')
        ->assertSet('pending', fn ($p) => count($p) === 0);

    // Nothing persisted.
    expect($odontogram->teeth()->count())->toBe(0);
});

it('guarda notas del odontograma y fecha de examen', function (): void {
    $patient = Patient::create(['first_name' => 'Jorge', 'last_name' => 'Luna']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);

    Livewire::test(OdontogramBoard::class, ['patient' => $patient])
        ->set('notes', 'Paciente con sensibilidad en pieza 16')
        ->set('examinedAt', '2025-03-15')
        ->call('save');

    $odontogram->refresh();
    expect($odontogram->notes)->toBe('Paciente con sensibilidad en pieza 16');
    expect($odontogram->examined_at->format('Y-m-d'))->toBe('2025-03-15');
});

it('abre y guarda nota por diente', function (): void {
    $patient = Patient::create(['first_name' => 'Karen', 'last_name' => 'Reyes']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);

    $component->call('openToothNote', 11)
        ->assertSet('showToothNoteModal', true)
        ->assertSet('toothNoteFdiCode', 11)
        ->assertSet('toothNoteText', '');

    $component->set('toothNoteText', 'Fractura marginal detectada')
        ->call('saveToothNote')
        ->assertSet('showToothNoteModal', false);

    $tooth = $odontogram->teeth()->where('fdi_code', 11)->first();
    expect($tooth)->not->toBeNull();
    expect($tooth->notes)->toBe('Fractura marginal detectada');
});

it('cancela edición de nota por diente sin guardar', function (): void {
    $patient = Patient::create(['first_name' => 'Luis', 'last_name' => 'Mora']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);

    Livewire::test(OdontogramBoard::class, ['patient' => $patient])
        ->call('openToothNote', 11)
        ->set('toothNoteText', 'Nota que no se guarda')
        ->call('cancelToothNote')
        ->assertSet('showToothNoteModal', false)
        ->assertSet('toothNoteText', '');

    expect($odontogram->teeth()->where('fdi_code', 11)->count())->toBe(0);
});

it('genera PDF sin errores', function (): void {
    $patient = Patient::create(['first_name' => 'Mario', 'last_name' => 'Castillo']);
    $odontogram = $patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $caries = makeCondition('caries', 'Caries', 'face');
    $corona = makeCondition('corona', 'Corona', 'tooth');

    Livewire::test(OdontogramBoard::class, ['patient' => $patient])
        ->set('activeCondition', 'caries')
        ->call('selectZone', 11, 'v')
        ->set('activeCondition', 'corona')
        ->call('selectZone', 21, 'numero')
        ->set('notes', 'Paciente con caries en pieza 11')
        ->set('examinedAt', '2025-06-01')
        ->call('save');

    $component = Livewire::test(OdontogramBoard::class, ['patient' => $patient]);
    $component->call('exportPdf');

    $component->assertDispatched('open-pdf');
});
