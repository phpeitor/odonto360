<?php

namespace App\Filament\Resources\ToothDefinitions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ToothDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Configuración de la pieza')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Identificación')
                            ->badge('1')
                            ->schema([
                                Section::make('Datos principales')
                                    ->description('Defina la dentición y la ubicación de la pieza dentro de la boca.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('fdi_code')
                                            ->label('Código FDI')
                                            ->helperText('Código de dos dígitos. Ejemplo: 11 es el incisivo central superior derecho.')
                                            ->required()
                                            ->numeric()
                                            ->minLength(2)
                                            ->maxLength(2),
                                        Select::make('dentition')
                                            ->label('Dentición')
                                            ->options([
                                                'adult' => 'Adulto (permanente)',
                                                'child' => 'Infantil (temporal)',
                                            ])
                                            ->default('adult')
                                            ->live()
                                            ->required(),
                                        Select::make('arch')
                                            ->label('Arcada')
                                            ->options([
                                                'upper' => 'Superior',
                                                'lower' => 'Inferior',
                                            ])
                                            ->required(),
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
                                            ->required(),
                                        Select::make('position')
                                            ->label('Posición')
                                            ->options(fn (Get $get): array => $get('dentition') === 'child'
                                                ? [
                                                    1 => '1 - Incisivo central',
                                                    2 => '2 - Incisivo lateral',
                                                    3 => '3 - Canino',
                                                    4 => '4 - Primer molar',
                                                    5 => '5 - Segundo molar',
                                                ]
                                                : [
                                                    1 => '1 - Incisivo central',
                                                    2 => '2 - Incisivo lateral',
                                                    3 => '3 - Canino',
                                                    4 => '4 - Primer premolar',
                                                    5 => '5 - Segundo premolar',
                                                    6 => '6 - Primer molar',
                                                    7 => '7 - Segundo molar',
                                                    8 => '8 - Tercer molar',
                                                ])
                                            ->required(),
                                    ]),
                            ]),
                        Tab::make('Anatomía')
                            ->badge('2')
                            ->schema([
                                Section::make('Características anatómicas')
                                    ->description('Estos datos controlan la representación visual de la pieza en el odontograma.')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('tooth_type')
                                            ->label('Tipo de diente')
                                            ->options(fn (Get $get): array => $get('dentition') === 'child'
                                                ? [
                                                    'incisivo' => 'Incisivo',
                                                    'canino' => 'Canino',
                                                    'molar' => 'Molar',
                                                ]
                                                : [
                                                    'incisivo' => 'Incisivo',
                                                    'canino' => 'Canino',
                                                    'premolar' => 'Premolar',
                                                    'molar' => 'Molar',
                                                ])
                                            ->required(),
                                        Select::make('root_count')
                                            ->label('Cantidad de raíces')
                                            ->options([
                                                1 => '1 raíz',
                                                2 => '2 raíces',
                                                3 => '3 raíces',
                                            ])
                                            ->helperText('Se utiliza para dibujar la anatomía de la pieza.')
                                            ->required(),
                                    ]),
                            ]),
                        Tab::make('Numeración')
                            ->badge('3')
                            ->schema([
                                Section::make('Correspondencia Universal')
                                    ->description('Adultos utilizan números del 1 al 32. La dentición infantil utiliza letras de la A a la T.')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('universal_number')
                                            ->label('Número Universal')
                                            ->options(array_combine(range(1, 32), range(1, 32)))
                                            ->placeholder('No aplica a dentición infantil')
                                            ->visible(fn (Get $get): bool => $get('dentition') === 'adult')
                                            ->default(null),
                                        Select::make('universal_letter')
                                            ->label('Letra Universal')
                                            ->options(array_combine(range('A', 'T'), range('A', 'T')))
                                            ->placeholder('No aplica a dentición adulta')
                                            ->visible(fn (Get $get): bool => $get('dentition') === 'child')
                                            ->default(null),
                                        TextInput::make('display_order')
                                            ->label('Orden de visualización')
                                            ->helperText('Posición visual dentro de la arcada.')
                                            ->required()
                                            ->numeric(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
