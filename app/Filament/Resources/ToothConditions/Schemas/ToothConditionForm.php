<?php

namespace App\Filament\Resources\ToothConditions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ToothConditionForm
{
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
                TextInput::make('color')
                    ->label('Color')
                    ->required(),
                Select::make('target')
                    ->label('Aplicación')
                    ->options(['face' => 'Cara', 'tooth' => 'Diente', 'both' => 'Ambos'])
                    ->required(),
                Select::make('category')
                    ->label('Categoría')
                    ->options([
                        'sano' => 'Sano',
                        'patologia' => 'Patología',
                        'restauracion' => 'Restauración',
                        'protesis' => 'Prótesis',
                        'quirurgico' => 'Quirúrgico',
                    ])
                    ->default('patologia')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Orden')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
