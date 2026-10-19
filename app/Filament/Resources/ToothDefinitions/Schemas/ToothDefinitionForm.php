<?php

namespace App\Filament\Resources\ToothDefinitions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ToothDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('fdi_code')
                    ->label('Código FDI')
                    ->required()
                    ->numeric(),
                Select::make('dentition')
                    ->label('Dentición')
                    ->options(['adult' => 'Adulto', 'child' => 'Infantil'])
                    ->required(),
                Select::make('arch')
                    ->label('Arcada')
                    ->options(['upper' => 'Superior', 'lower' => 'Inferior'])
                    ->required(),
                TextInput::make('quadrant')
                    ->label('Cuadrante')
                    ->required()
                    ->numeric(),
                TextInput::make('position')
                    ->label('Posición')
                    ->required()
                    ->numeric(),
                TextInput::make('tooth_type')
                    ->label('Tipo de diente')
                    ->required(),
                TextInput::make('root_count')
                    ->label('Cantidad de raíces')
                    ->required()
                    ->numeric(),
                TextInput::make('universal_number')
                    ->label('Número universal')
                    ->numeric()
                    ->default(null),
                TextInput::make('universal_letter')
                    ->label('Letra universal')
                    ->default(null),
                TextInput::make('display_order')
                    ->label('Orden de visualización')
                    ->required()
                    ->numeric(),
            ]);
    }
}
