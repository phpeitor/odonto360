<?php

use App\Enum\PatientSex;
use App\Filament\Pages\OdontogramBoard;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use App\Models\ToothDefinition;
use App\Models\User;
use Database\Seeders\ToothConditionSeeder;
use Database\Seeders\ToothDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the selected patient data in the form', function (): void {
    $patient = Patient::create([
        'first_name' => 'JHONY ANTONIO',
        'last_name' => 'Pilco Mamani',
        'document_number' => '12345678',
        'birth_date' => '1990-05-20',
        'sex' => PatientSex::Masculino,
        'phone' => '951034056',
        'email' => 'patient@example.com',
    ]);

    Livewire::test(OdontogramBoard::class)
        ->assertFormSet([
            'patientId' => $patient->id,
            'document_number' => '12345678',
            'birth_date' => '1990-05-20',
            'sex' => PatientSex::Masculino,
            'phone' => '951034056',
            'email' => 'patient@example.com',
        ]);
});

it('refreshes the form data when the patient changes', function (): void {
    $first = Patient::create([
        'first_name' => 'Wanda',
        'last_name' => 'Conner',
        'document_number' => '60356943',
        'birth_date' => '1985-01-15',
        'sex' => PatientSex::Femenino,
        'phone' => '+1 (718) 695-8556',
        'email' => 'xapoci@mailinator.com',
    ]);

    $second = Patient::create([
        'first_name' => 'Bruno',
        'last_name' => 'Diaz',
        'document_number' => '87654321',
        'birth_date' => '2000-07-30',
        'sex' => PatientSex::Masculino,
        'phone' => '987654321',
        'email' => 'bruno@example.com',
    ]);

    Livewire::test(OdontogramBoard::class)
        ->set('patientId', $first->id)
        ->assertFormSet([
            'document_number' => '60356943',
            'sex' => PatientSex::Femenino,
            'email' => 'xapoci@mailinator.com',
        ])
        ->set('patientId', $second->id)
        ->assertFormSet([
            'document_number' => '87654321',
            'sex' => PatientSex::Masculino,
            'email' => 'bruno@example.com',
        ]);
});

it('treats a patient as adult from 18 years old', function (): void {
    $this->travelTo('2026-10-21');

    $adult = Patient::factory()->make(['birth_date' => '2008-10-21']);
    $minor = Patient::factory()->make(['birth_date' => '2008-10-22']);
    $unknown = Patient::factory()->make(['birth_date' => null]);

    expect($adult->isAdult())->toBeTrue()
        ->and($minor->isAdult())->toBeFalse()
        ->and($unknown->isAdult())->toBeFalse();
});

it('does not allow switching an adult patient to child dentition', function (): void {
    $patient = Patient::factory()->create(['birth_date' => '1991-02-03']);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $patient])
        ->call('setDentition', 'child')
        ->assertSet('dentition', 'adult')
        ->assertSee('Paciente mayor de edad: solo dentición adulta');
});

it('allows switching a minor patient to child dentition', function (): void {
    $patient = Patient::factory()->create(['birth_date' => now()->subYears(8)->toDateString()]);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $patient])
        ->call('setDentition', 'child')
        ->assertSet('dentition', 'child');
});

it('finds patients in the global search by name, document and phone', function (): void {
    Filament\Facades\Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());

    Patient::factory()->create([
        'first_name' => 'Alejandro',
        'last_name' => 'Montalván',
        'document_number' => '46798772',
        'phone' => '942890820',
    ]);

    foreach (['alej', 'montalv', '46798772', '942890820'] as $term) {
        $results = PatientResource::getGlobalSearchResults($term);

        expect($results)->toHaveCount(1)
            ->and($results->first()->title)->toBe('Alejandro Montalván');
    }

    expect(PatientResource::getGlobalSearchResults('zzzz'))->toHaveCount(0);
});

it('ignores unknown dentition and numbering values', function (): void {
    $patient = Patient::factory()->create(['birth_date' => now()->subYears(8)->toDateString()]);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $patient])
        ->call('setDentition', 'hacked')
        ->assertSet('dentition', 'mixed')
        ->call('setNumberingSystem', 'hacked')
        ->assertSet('numberingSystem', 'fdi')
        ->call('setNumberingSystem', 'universal')
        ->assertSet('numberingSystem', 'universal');

    expect($patient->odontogram()->first()->only(['dentition', 'numbering_system']))
        ->toBe(['dentition' => 'mixed', 'numbering_system' => 'universal']);
});

it('blocks the adult dentition for patients under 6 and starts them on child', function (): void {
    $this->travelTo('2026-10-21');
    $toddler = Patient::factory()->create(['birth_date' => '2021-10-22']);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $toddler])
        ->assertSet('dentition', 'child')
        ->call('setDentition', 'adult')
        ->assertSet('dentition', 'child')
        ->assertSee('Paciente menor de 6 años: solo dentición infantil');

    $sixYearsOld = Patient::factory()->create(['birth_date' => '2020-10-21']);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $sixYearsOld])
        ->assertSet('dentition', 'mixed')
        ->call('setDentition', 'child')
        ->assertSet('dentition', 'child')
        ->call('setDentition', 'adult')
        ->assertSet('dentition', 'adult');
});

it('allows the mixed dentition from 6 to 17 years and opens it by default up to 12', function (): void {
    $this->travelTo('2026-10-21');

    $ages = [
        'cinco años' => ['2021-10-22', false, 'child'],
        'seis años' => ['2020-10-21', true, 'mixed'],
        'doce años' => ['2014-10-21', true, 'mixed'],
        'trece años' => ['2013-10-21', true, 'adult'],
        'diecisiete años' => ['2008-10-22', true, 'adult'],
        'mayor de edad' => ['2008-10-21', false, 'adult'],
    ];

    foreach ($ages as $label => [$birthDate, $allowsMixed, $default]) {
        $patient = Patient::factory()->make(['birth_date' => $birthDate]);

        expect($patient->allowsDentition('mixed'))->toBe($allowsMixed, $label)
            ->and($patient->defaultDentition())->toBe($default, $label);
    }

    expect(Patient::factory()->make(['birth_date' => null])->defaultDentition())->toBe('adult');
});

it('draws the mixed dentition in four rows with the temporary teeth next to the midline', function (): void {
    $this->seed([ToothConditionSeeder::class, ToothDefinitionSeeder::class]);
    $patient = Patient::factory()->create(['birth_date' => now()->subYears(8)->toDateString()]);

    $board = Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $patient])
        ->assertSet('dentition', 'mixed')
        ->assertSee('Mixta · 52');

    $rows = collect($board->instance()->archRows)
        ->map(fn (array $archRows): array => array_map(
            fn ($row): array => [$row->count(), $row->first()->fdi_code],
            $archRows,
        ))
        ->all();

    expect($rows)->toBe([
        'upper' => [[16, 18], [10, 55]],
        'lower' => [[10, 85], [16, 48]],
    ]);

    $board->call('setNumberingSystem', 'universal');

    expect($board->instance()->toothSvg(ToothDefinition::where('fdi_code', 55)->first()))
        ->toContain('>A<')
        ->and($board->instance()->toothSvg(ToothDefinition::where('fdi_code', 18)->first()))
        ->toContain('>1<');
});

it('does not allow the mixed dentition for adults or patients under 6', function (): void {
    $adult = Patient::factory()->create(['birth_date' => '1991-02-03']);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $adult])
        ->call('setDentition', 'mixed')
        ->assertSet('dentition', 'adult');

    $toddler = Patient::factory()->create(['birth_date' => now()->subYears(3)->toDateString()]);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $toddler])
        ->call('setDentition', 'mixed')
        ->assertSet('dentition', 'child');
});

it('records which user registered each treatment', function (): void {
    $this->seed([ToothConditionSeeder::class, ToothDefinitionSeeder::class]);
    $user = User::factory()->create(['name' => 'Dra. Rosa Quispe']);
    $this->actingAs($user);

    $patient = Patient::factory()->create(['birth_date' => '1991-02-03']);

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $patient])
        ->set('activeCondition', 'caries')
        ->call('selectZone', 11, 'v')
        ->assertSee('Dra. Rosa Quispe')
        ->call('save')
        ->assertSee('Dra. Rosa Quispe');

    $entry = $patient->odontogram()->first()->treatmentLog()->first();

    expect($entry->user_id)->toBe($user->id)
        ->and($entry->user->name)->toBe('Dra. Rosa Quispe');
});

it('notifies with a toast when the odontogram is saved', function (): void {
    $this->seed([ToothConditionSeeder::class, ToothDefinitionSeeder::class]);
    $this->actingAs(User::factory()->create());
    $patient = Patient::factory()->create();

    Livewire::test(App\Livewire\OdontogramBoard::class, ['patient' => $patient])
        ->set('activeCondition', 'caries')
        ->call('selectZone', 11, 'v')
        ->call('save')
        ->assertNotified('Odontograma guardado')
        ->assertDontSee('Guardado ✓')
        ->call('saveToothNote')
        ->call('openToothNote', 11)
        ->set('toothNoteText', 'Revisar en 15 días')
        ->call('saveToothNote')
        ->assertNotified('Nota de la pieza 11 guardada');
});
