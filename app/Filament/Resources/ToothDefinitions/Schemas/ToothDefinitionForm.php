<?php

namespace App\Filament\Resources\ToothDefinitions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ToothDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identificación de la pieza')
                    ->description('Datos fijos de la pieza: el tablero y los odontogramas dependen de ellos, por eso no se editan.')
                    ->icon('heroicon-o-lock-closed')
                    ->columns(3)
                    ->schema([
                        TextInput::make('fdi_code')
                            ->label('Código FDI')
                            ->helperText('Cuadrante + posición. Ej.: 11 = incisivo central superior derecho.')
                            ->disabled(),
                        Select::make('dentition')
                            ->label('Dentición')
                            ->options(['adult' => 'Adulto (permanente)', 'child' => 'Infantil (temporal)'])
                            ->disabled(),
                        Select::make('arch')
                            ->label('Arcada')
                            ->options(['upper' => 'Superior', 'lower' => 'Inferior'])
                            ->disabled(),
                        Select::make('quadrant')
                            ->label('Cuadrante')
                            ->options([
                                1 => '1 - Superior derecho',
                                2 => '2 - Superior izquierdo',
                                3 => '3 - Inferior izquierdo',
                                4 => '4 - Inferior derecho',
                                5 => '5 - Superior derecho infantil',
                                6 => '6 - Superior izquierdo infantil',
                                7 => '7 - Inferior izquierdo infantil',
                                8 => '8 - Inferior derecho infantil',
                            ])
                            ->disabled(),
                        TextInput::make('position')
                            ->label('Posición')
                            ->disabled(),
                        TextInput::make('universal_number')
                            ->label('Número Universal')
                            ->visible(fn (Get $get): bool => $get('dentition') === 'adult')
                            ->disabled(),
                        TextInput::make('universal_letter')
                            ->label('Letra Universal')
                            ->visible(fn (Get $get): bool => $get('dentition') === 'child')
                            ->disabled(),
                    ]),
                Section::make('Anatomía y visualización')
                    ->description('Controlan cómo se dibuja la pieza y en qué orden aparece en el arco del odontograma.')
                    ->columns(3)
                    ->schema([
                        Select::make('tooth_type')
                            ->label('Tipo de diente')
                            ->options(fn (Get $get): array => $get('dentition') === 'child'
                                ? ['incisivo' => 'Incisivo', 'canino' => 'Canino', 'molar' => 'Molar']
                                : ['incisivo' => 'Incisivo', 'canino' => 'Canino', 'premolar' => 'Premolar', 'molar' => 'Molar'])
                            ->required(),
                        Select::make('root_count')
                            ->label('Cantidad de raíces')
                            ->options([1 => '1 raíz', 2 => '2 raíces', 3 => '3 raíces'])
                            ->helperText('Se utiliza para dibujar la anatomía de la pieza.')
                            ->required(),
                        TextInput::make('display_order')
                            ->label('Orden de visualización')
                            ->helperText('Posición de izquierda a derecha dentro de la arcada (empieza en 0).')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(15),
                    ]),
            ]);
    }
}
