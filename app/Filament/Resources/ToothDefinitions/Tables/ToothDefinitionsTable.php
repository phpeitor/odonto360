<?php

namespace App\Filament\Resources\ToothDefinitions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ToothDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->searchable()
            ->paginated([5, 10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('fdi_code')
                    ->label('Código FDI')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dentition')
                    ->label('Dentición')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'adult' => 'Adulto',
                        'child' => 'Infantil',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('arch')
                    ->label('Arcada')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'upper' => 'Superior',
                        'lower' => 'Inferior',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('quadrant')
                    ->label('Cuadrante')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Posición')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tooth_type')
                    ->label('Tipo de diente')
                    ->searchable(),
                TextColumn::make('root_count')
                    ->label('Raíces')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('universal_number')
                    ->label('Número universal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('universal_letter')
                    ->label('Letra universal')
                    ->searchable(),
                TextColumn::make('display_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
