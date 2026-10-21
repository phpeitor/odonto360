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
