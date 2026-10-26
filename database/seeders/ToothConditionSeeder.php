<?php

namespace Database\Seeders;

use App\Models\ToothCondition;
use Illuminate\Database\Seeder;

/** Los 38 hallazgos de la nomenclatura de la NTS N° 188-MINSA/DGIESP-2022 (numeral 6.1). */
class ToothConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // [código, nombre, aplica a, categoría, estados, siglas, dibujo, orden]
        $rows = [
            ['sano',                  'Sano / Borrar',              'both',  'sano',         'bueno', null,                              null,          0],

            // Patología
            ['caries',                'Lesión de caries',           'face',  'patologia',    'malo',  ['MB', 'CE', 'CD', 'CDP'],         'fill',        10],
            ['fractura',              'Fractura',                   'tooth', 'patologia',    'malo',  null,                              'fractura',    20],
            ['desgastada',            'Superficie desgastada',      'face',  'patologia',    'malo',  ['DES'],                           'tint',        30],
            ['dde',                   'Defecto del esmalte (DDE)',  'tooth', 'patologia',    'malo',  ['O', 'PE'],                       null,          40],
            ['remanente_radicular',   'Remanente radicular',        'tooth', 'patologia',    'malo',  ['RR'],                            null,          50],
            ['movilidad',             'Movilidad patológica',       'tooth', 'patologia',    'malo',  ['M1', 'M2', 'M3'],                null,          60],

            // Restauraciones y tratamientos
            ['obturacion',            'Restauración definitiva',    'face',  'restauracion', 'ambos', ['AM', 'R', 'IV', 'IM', 'IE', 'C'], 'fill',       100],
            ['restauracion_temporal', 'Restauración temporal',      'face',  'restauracion', 'malo',  null,                              'outline',    110],
            ['sellante',              'Sellante',                   'face',  'restauracion', 'ambos', ['S'],                             'tint',       120],
            ['corona',                'Corona',                     'tooth', 'restauracion', 'ambos', ['CM', 'CF', 'CMC', 'CV', 'CLM'],  'corona',     130],
            ['corona_temporal',       'Corona temporal',            'tooth', 'restauracion', 'malo',  ['CT'],                            'corona',     140],
            ['espigo_munon',          'Espigo-muñón',               'tooth', 'restauracion', 'ambos', null,                              'espigo',     150],
            ['endodoncia',            'Tratamiento de conductos',   'tooth', 'restauracion', 'ambos', ['TC', 'PC'],                      'conducto',   160],
            ['pulpotomia',            'Pulpotomía',                 'tooth', 'restauracion', 'ambos', ['PP'],                            'pulpotomia', 170],
            ['implante',              'Implante dental',            'tooth', 'restauracion', 'ambos', ['IMP'],                           null,         180],

            // Anomalías y posición
            ['ausente',               'Pieza ausente',              'tooth', 'anomalia',     'bueno', ['DNE', 'DEX', 'DAO'],             'aspa',       200],
            ['erupcion',              'Pieza en erupción',          'tooth', 'anomalia',     'bueno', null,                              'erupcion',   210],
            ['extruida',              'Pieza extruida',             'tooth', 'anomalia',     'bueno', null,                              'extruida',   220],
            ['intruida',              'Pieza intruida',             'tooth', 'anomalia',     'bueno', null,                              'intruida',   230],
            ['giroversion',           'Giroversión',                'tooth', 'anomalia',     'bueno', null,                              'giroversion', 240],
            ['clavija',               'Pieza en clavija',           'tooth', 'anomalia',     'bueno', null,                              'clavija',    250],
            ['geminacion',            'Geminación',                 'tooth', 'anomalia',     'bueno', null,                              'geminacion', 260],
            ['impactacion',           'Impactación',                'tooth', 'anomalia',     'bueno', ['I'],                             null,         270],
            ['ectopica',              'Pieza ectópica',             'tooth', 'anomalia',     'bueno', ['E'],                             null,         280],
            ['posicion_anormal',      'Posición anormal',           'tooth', 'anomalia',     'bueno', ['M', 'D', 'V', 'P', 'L'],         null,         290],
            ['macrodoncia',           'Macrodoncia',                'tooth', 'anomalia',     'bueno', ['MAC'],                           null,         300],
            ['microdoncia',           'Microdoncia',                'tooth', 'anomalia',     'bueno', ['MIC'],                           null,         310],
            ['ffp',                   'Fosas y fisuras profundas',  'tooth', 'anomalia',     'bueno', ['FFP'],                           null,         320],

            // Varias piezas: se marcan con clic en la pieza inicial y en la final (o en una pieza de la arcada)
            ['ortodoncia_fija',       'Aparato ortodóntico fijo',      'span', 'varias',     'ambos', null,                              'ortodoncia_fija',      400],
            ['ortodoncia_removible',  'Aparato ortodóntico removible', 'span', 'varias',     'ambos', null,                              'ortodoncia_removible', 410],
            ['protesis_fija',         'Prótesis parcial fija',         'span', 'varias',     'ambos', null,                              'protesis_fija',        420],
            ['protesis_removible',    'Prótesis parcial removible',    'span', 'varias',     'ambos', null,                              'protesis_removible',   430],
            ['protesis_completa',     'Prótesis completa',             'span', 'varias',     'ambos', null,                              'protesis_completa',    440],
            ['edentulo',              'Edéntulo total',                'span', 'varias',     'bueno', null,                              'edentulo',             450],
            ['diastema',              'Diastema',                      'span', 'varias',     'bueno', null,                              'diastema',             460],
            ['fusion',                'Fusión',                        'span', 'varias',     'bueno', null,                              'fusion',               470],
            ['supernumeraria',        'Pieza supernumeraria',          'span', 'varias',     'bueno', null,                              'supernumeraria',       480],
            ['transposicion',         'Transposición dentaria',        'span', 'varias',     'bueno', null,                              'transposicion',        490],
        ];

        // El color (azul/rojo) lo asigna ToothCondition al guardar, según los estados permitidos.
        foreach ($rows as [$code, $label, $target, $category, $allowedStates, $abbreviations, $glyph, $sort]) {
            ToothCondition::updateOrCreate(
                ['code' => $code],
                [
                    'label' => $label,
                    'target' => $target,
                    'category' => $category,
                    'allowed_states' => $allowedStates,
                    'abbreviations' => $abbreviations,
                    'glyph' => $glyph,
                    'sort_order' => $sort,
                ]
            );
        }
    }
}
