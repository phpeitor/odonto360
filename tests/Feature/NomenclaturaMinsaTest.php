<?php

use App\Domain\Odontogram\DentitionLayout;
use App\Domain\Odontogram\OdontogramPdfExport;
use App\Domain\Odontogram\SpanLayout;
use App\Domain\Odontogram\ToothSvgBuilder;
use App\Domain\Odontogram\TreatmentVoider;
use App\Livewire\OdontogramBoard;
use App\Models\OdontogramToothFinding;
use App\Models\Patient;
use App\Models\ToothCondition;
use App\Models\ToothDefinition;
use App\Models\User;
use Database\Seeders\ToothConditionSeeder;
use Database\Seeders\ToothDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ToothConditionSeeder::class, ToothDefinitionSeeder::class]);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->patient = Patient::factory()->create(['birth_date' => '1991-02-03']);
});

it('siembra los 38 hallazgos de la norma, solo en azul o rojo', function (): void {
    $findings = ToothCondition::where('code', '!=', ToothCondition::CODE_SANO)->get();

    expect($findings)->toHaveCount(38)
        ->and($findings->where('target', 'span'))->toHaveCount(10)
        ->and($findings->pluck('color')->unique()->sort()->values()->all())
        ->toBe([ToothCondition::COLOR_GOOD, ToothCondition::COLOR_BAD])
        ->and(ToothCondition::where('code', 'caries')->first()->abbreviationOptions())->toBe(['MB', 'CE', 'CD', 'CDP'])
        ->and(ToothCondition::where('code', 'extraccion')->exists())->toBeFalse();
});

it('preselecciona la primera sigla y el estado por defecto, y rechaza valores no permitidos', function (): void {
    Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'corona')
        ->assertSet('activeAbbreviation', 'CM')
        ->assertSet('activeState', 'bueno')
        ->call('pickAbbreviation', 'XX')
        ->assertSet('activeAbbreviation', 'CM')
        ->call('pickState', 'malo')
        ->assertSet('activeState', 'malo')
        // La caries solo se registra en mal estado (rojo).
        ->call('pickCondition', 'caries')
        ->assertSet('activeState', 'malo')
        ->call('pickState', 'bueno')
        ->assertSet('activeState', 'malo');
});

it('registra varios hallazgos en la misma pieza con su sigla y estado', function (): void {
    Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'corona')
        ->call('pickAbbreviation', 'CMC')
        ->call('pickState', 'malo')
        ->call('selectZone', 16, 'numero')
        ->call('pickCondition', 'endodoncia')
        ->call('selectZone', 16, 'o')
        ->call('pickCondition', 'movilidad')
        ->call('pickAbbreviation', 'M2')
        ->call('selectZone', 16, 'numero')
        ->call('save');

    $tooth = $this->patient->odontogram->teeth()->where('fdi_code', 16)->first();
    $findings = $tooth->findings()->with('condition')->get()->keyBy('condition.code');

    expect($findings)->toHaveCount(3)
        ->and($findings['corona']->only(['abbreviation', 'state']))->toBe(['abbreviation' => 'CMC', 'state' => 'malo'])
        ->and($findings['endodoncia']->only(['abbreviation', 'state']))->toBe(['abbreviation' => 'TC', 'state' => 'bueno'])
        ->and($findings['movilidad']->only(['abbreviation', 'state']))->toBe(['abbreviation' => 'M2', 'state' => 'malo'])
        ->and($tooth->faces()->count())->toBe(0);
});

it('quita un solo hallazgo de la pieza desde el listado', function (): void {
    $component = Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'corona')
        ->call('selectZone', 21, 'numero')
        ->call('pickCondition', 'pulpotomia')
        ->call('selectZone', 21, 'numero')
        ->call('save');

    $component->call('removeFinding', 21, null, 'corona')->call('save');

    $codes = OdontogramToothFinding::with('condition')->get()->pluck('condition.code')->all();

    expect($codes)->toBe(['pulpotomia']);
});

it('guarda la sigla y el estado de las caras', function (): void {
    Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'obturacion')
        ->call('pickAbbreviation', 'AM')
        ->call('pickState', 'malo')
        ->call('selectZone', 36, 'o')
        ->call('save');

    $face = $this->patient->odontogram->teeth()->where('fdi_code', 36)->first()->faces()->first();

    expect($face->only(['face', 'abbreviation', 'state']))->toBe(['face' => 'o', 'abbreviation' => 'AM', 'state' => 'malo']);
});

it('al anular, quita el hallazgo aplicado o restaura el borrado con su sigla', function (): void {
    $component = Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'ausente')
        ->call('pickAbbreviation', 'DEX')
        ->call('selectZone', 46, 'numero')
        ->call('save')
        ->call('pickCondition', 'sano')
        ->call('selectZone', 46, 'numero')
        ->call('save');

    $odontogram = $this->patient->odontogram;
    [$apply, $clear] = $odontogram->treatmentLog()->orderBy('id')->get()->all();

    expect(OdontogramToothFinding::count())->toBe(0);

    (new TreatmentVoider)->void($clear, $this->user);

    $restored = OdontogramToothFinding::first();
    expect($restored->only(['abbreviation', 'state']))->toBe(['abbreviation' => 'DEX', 'state' => 'bueno']);

    (new TreatmentVoider)->void($apply->fresh(), $this->user);

    expect(OdontogramToothFinding::count())->toBe(0);
});

it('guarda especificaciones y observaciones del odontograma', function (): void {
    Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->set('specifications', 'Corona 16 de metal dorado')
        ->set('notes', 'Paciente refiere bruxismo')
        ->call('save');

    expect($this->patient->odontogram->only(['specifications', 'notes']))
        ->toBe(['specifications' => 'Corona 16 de metal dorado', 'notes' => 'Paciente refiere bruxismo']);
});

it('dibuja el recuadro de siglas con el color de cada estado', function (): void {
    $svg = ToothSvgBuilder::render(
        def: ToothDefinition::where('fdi_code', 16)->first(),
        shownNumber: '16',
        faces: ['o' => ['code' => 'caries', 'abbreviation' => 'CD', 'state' => 'malo', 'glyph' => 'fill']],
        findings: ['corona' => ['abbreviation' => 'CMC', 'state' => 'bueno', 'glyph' => 'corona']],
    );

    expect($svg)
        ->toContain('viewBox="0 0 100 '.ToothSvgBuilder::VIEWBOX_HEIGHT.'"')
        ->toMatch('/fill="'.ToothCondition::COLOR_BAD.'">CD<\/text>/')
        ->toMatch('/fill="'.ToothCondition::COLOR_GOOD.'">CMC<\/text>/')
        ->toContain('stroke="'.ToothCondition::COLOR_GOOD.'" stroke-width="5"');
});

it('marca una prótesis parcial fija de la pieza inicial a la final, en el orden de la fila', function (): void {
    $component = Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'protesis_fija')
        ->call('pickState', 'malo')
        ->call('selectZone', 23, 'numero')
        ->assertSet('spanStartFdi', 23)
        ->call('selectZone', 13, 'v')
        ->assertSet('spanStartFdi', null)
        ->call('save');

    $span = $this->patient->odontogram->spans()->first();
    $entry = $this->patient->odontogram->treatmentLog()->first();

    expect($span->only(['start_fdi_code', 'end_fdi_code', 'state']))->toBe(['start_fdi_code' => 13, 'end_fdi_code' => 23, 'state' => 'malo'])
        ->and($entry->only(['fdi_code', 'end_fdi_code', 'action']))->toBe(['fdi_code' => 13, 'end_fdi_code' => 23, 'action' => 'apply'])
        ->and($entry->teethLabel())->toBe('13–23');

    $component->assertSee('13–23');
});

it('exige piezas contiguas para el diastema y la misma fila para las prótesis', function (): void {
    Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'diastema')
        ->call('selectZone', 11, 'numero')
        ->call('selectZone', 22, 'numero')
        ->assertSet('pending', [])
        ->call('selectZone', 11, 'numero')
        ->call('selectZone', 21, 'numero')
        ->assertCount('pending', 1)
        ->call('pickCondition', 'protesis_removible')
        ->call('selectZone', 16, 'numero')
        ->call('selectZone', 46, 'numero')
        ->assertCount('pending', 1);
});

it('con un clic marca toda la fila como edéntulo total', function (): void {
    Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'edentulo')
        ->call('selectZone', 36, 'numero')
        ->call('save');

    expect($this->patient->odontogram->spans()->first()->only(['start_fdi_code', 'end_fdi_code']))
        ->toBe(['start_fdi_code' => 48, 'end_fdi_code' => 38]);
});

it('quita un hallazgo de varias piezas y al anular el borrado lo restaura', function (): void {
    $component = Livewire::test(OdontogramBoard::class, ['patient' => $this->patient])
        ->call('pickCondition', 'ortodoncia_fija')
        ->call('selectZone', 16, 'numero')
        ->call('selectZone', 26, 'numero')
        ->call('save');

    $component->call('removeSpan', 'ortodoncia_fija', 16, 26)->call('save');

    expect($this->patient->odontogram->spans()->count())->toBe(0);

    $clear = $this->patient->odontogram->treatmentLog()->where('action', 'clear')->first();
    (new TreatmentVoider)->void($clear, $this->user);

    expect($this->patient->odontogram->spans()->first()->only(['start_fdi_code', 'end_fdi_code', 'state']))
        ->toBe(['start_fdi_code' => 16, 'end_fdi_code' => 26, 'state' => 'bueno']);
});

it('reparte el hallazgo en tramos de inicio, medio y fin por pieza', function (): void {
    $segments = SpanLayout::segments(DentitionLayout::rows('adult'), [
        ['glyph' => 'protesis_fija', 'state' => 'bueno', 'start' => 13, 'end' => 11],
        ['glyph' => 'diastema', 'state' => 'bueno', 'start' => 11, 'end' => 21],
        // Piezas en filas distintas: no se dibuja.
        ['glyph' => 'fusion', 'state' => 'bueno', 'start' => 11, 'end' => 41],
    ]);

    expect(array_column($segments[13], 'position'))->toBe(['start'])
        ->and(array_column($segments[12], 'position'))->toBe(['middle'])
        ->and(array_column($segments[11], 'position'))->toBe(['end', 'start'])
        ->and(array_column($segments[21], 'glyph'))->toBe(['diastema'])
        ->and($segments)->not->toHaveKey(41);
});

it('incluye los hallazgos de varias piezas en el PDF', function (): void {
    $odontogram = $this->patient->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $withoutSpans = (new OdontogramPdfExport)->generate($odontogram->load('patient'));

    $odontogram->spans()->create([
        'condition_id' => ToothCondition::where('code', 'protesis_completa')->value('id'),
        'start_fdi_code' => 18,
        'end_fdi_code' => 28,
        'state' => 'bueno',
    ]);

    expect(strlen((new OdontogramPdfExport)->generate($odontogram->fresh()->load('patient'))))->toBeGreaterThan(strlen($withoutSpans));
});

it('muestra el número de pieza con colores definidos en el tema', function (): void {
    $svg = ToothSvgBuilder::render(def: ToothDefinition::where('fdi_code', 16)->first(), shownNumber: '16');
    $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

    // El número usa --ink y --surface-alt: deben tener un color real (Filament no define --color-base-*).
    expect($svg)->toContain('fill="var(--ink)" ')
        ->toMatch('/>16<\/text>/')
        ->and($theme)->toMatch('/--ink:\s*#[0-9a-f]{6};/i')
        ->toMatch('/--surface-alt:\s*#[0-9a-f]{6};/i')
        ->toMatch('/--border:\s*#[0-9a-f]{6};/i')
        ->not->toContain('var(--color-base');
});
