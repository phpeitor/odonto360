<?php

use App\Domain\Odontogram\OdontogramPdfExport;
use App\Models\Patient;
use App\Models\ToothCondition;
use Database\Seeders\ToothConditionSeeder;
use Database\Seeders\ToothDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ToothConditionSeeder::class, ToothDefinitionSeeder::class]);
});

it('generates a one-page PDF for an odontogram without history', function (): void {
    $odontogram = Patient::factory()->create()->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);

    $pdf = (new OdontogramPdfExport)->generate($odontogram);

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('#/Type\s*/Page\b#', $pdf))->toBe(1);
});

it('generates a PDF for a mixed dentition odontogram', function (): void {
    $odontogram = Patient::factory()->create()->odontogram()->create(['dentition' => 'mixed', 'numbering_system' => 'universal']);

    $pdf = (new OdontogramPdfExport)->generate($odontogram);

    // Las 4 filas y la leyenda caben en la primera hoja; sin historial, como mucho pasa a la segunda.
    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('#/Type\s*/Page\b#', $pdf))->toBeLessThanOrEqual(2);
});

it('flows long history and notes onto extra pages instead of overlapping', function (): void {
    $odontogram = Patient::factory()->create()->odontogram()->create([
        'dentition' => 'child',
        'numbering_system' => 'universal',
        'notes' => str_repeat('Nota clínica extensa con ñ y acentos para forzar el ajuste de línea. ', 60),
    ]);
    $condition = ToothCondition::where('code', 'caries')->first();

    foreach (range(1, 60) as $i) {
        $odontogram->treatmentLog()->create([
            'fdi_code' => 51,
            'face' => 'v',
            'condition_id' => $condition->id,
            'observation' => str_repeat("Observación {$i} ", 12),
            'registered_at' => now()->subDays($i),
        ]);
    }

    $pdf = (new OdontogramPdfExport)->generate($odontogram->load('patient'));

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('#/Type\s*/Page\b#', $pdf))->toBeGreaterThan(2);
});

it('includes the per-tooth notes in the PDF', function (): void {
    $odontogram = Patient::factory()->create()->odontogram()->create(['dentition' => 'adult', 'numbering_system' => 'fdi']);
    $odontogram->teeth()->create(['fdi_code' => 11, 'notes' => 'Sensibilidad al frío; controlar en 15 días.']);
    $odontogram->teeth()->create(['fdi_code' => 36, 'notes' => '   ']);

    $withNotes = (new OdontogramPdfExport)->generate($odontogram->load('patient'));
    $odontogram->teeth()->update(['notes' => null]);
    $withoutNotes = (new OdontogramPdfExport)->generate($odontogram->fresh()->load('patient'));

    expect(strlen($withNotes))->toBeGreaterThan(strlen($withoutNotes));
});
