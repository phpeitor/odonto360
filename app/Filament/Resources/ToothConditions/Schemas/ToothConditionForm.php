<?php

namespace App\Filament\Resources\ToothConditions\Schemas;

use App\Models\ToothCondition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ToothConditionForm
{
    /** Dibujos que sabe trazar ToothSvgBuilder (los hallazgos solo de sigla no llevan dibujo). */
    public const GLYPHS = [
        'fill' => 'Cara pintada',
        'tint' => 'Cara sombreada',
        'outline' => 'Contorno de la cara',
        'corona' => 'Cuadrado alrededor de la corona',
        'espigo' => 'Espigo-muñón',
        'conducto' => 'Línea vertical en la raíz',
        'pulpotomia' => 'Pulpa coronal pintada',
        'fractura' => 'Línea de fractura',
        'aspa' => 'Aspa',
        'erupcion' => 'Flecha en zigzag',
        'extruida' => 'Flecha hacia afuera',
        'intruida' => 'Flecha hacia oclusal',
        'giroversion' => 'Flecha curva',
        'clavija' => 'Triángulo',
        'geminacion' => 'Círculo en el número',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Código')
                    ->required(),
                TextInput::make('label')
                    ->label('Nombre')
                    ->required(),
                Select::make('target')
                    ->label('Aplicación')
                    ->options(['face' => 'Cara', 'tooth' => 'Diente', 'both' => 'Ambos'])
                    ->required(),
                Select::make('category')
                    ->label('Categoría')
                    ->options(ToothCondition::CATEGORY_LABELS)
                    ->default('patologia')
                    ->required(),
                Select::make('allowed_states')
                    ->label('Estados')
                    ->helperText('Azul = buen estado, rojo = mal estado (NTS N° 188-MINSA/DGIESP-2022).')
                    ->options(['ambos' => 'Buen o mal estado', 'bueno' => 'Solo buen estado (azul)', 'malo' => 'Solo mal estado (rojo)'])
                    ->default('ambos')
                    ->required(),
                TagsInput::make('abbreviations')
                    ->label('Siglas')
                    ->helperText('Siglas que se anotan en el recuadro de la pieza, en mayúsculas.'),
                Select::make('glyph')
                    ->label('Dibujo')
                    ->options(self::GLYPHS)
                    ->placeholder('Solo sigla'),
                TextInput::make('sort_order')
                    ->label('Orden')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
