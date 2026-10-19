<?php

namespace App\Filament\Resources\ToothConditions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ToothConditionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->searchable()
            ->paginated([5, 10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('label')
                    ->label('Nombre')
                    ->searchable(),
                ColorColumn::make('color')
                    ->label('Color')
                    ->searchable(),
                TextColumn::make('target')
                    ->label('Aplicación')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'face' => 'Cara',
                        'tooth' => 'Diente',
                        'both' => 'Ambos',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('category')
                    ->label('Categoría')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sano' => 'Sano',
                        'patologia' => 'Patología',
                        'restauracion' => 'Restauración',
                        'protesis' => 'Prótesis',
                        'quirurgico' => 'Quirúrgico',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('sort_order')
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
