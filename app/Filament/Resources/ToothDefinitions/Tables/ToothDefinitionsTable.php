<?php

namespace App\Filament\Resources\ToothDefinitions\Tables;

use App\Models\ToothDefinition;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ToothDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->searchable()
            ->defaultSort('fdi_code')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('fdi_code')
                    ->label('Código FDI')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tooth_type')
                    ->label('Tipo de diente')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->badge()
                    ->searchable(),
                TextColumn::make('arch')
                    ->label('Arcada')
                    ->formatStateUsing(fn (string $state): string => $state === 'upper' ? 'Superior' : 'Inferior')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'upper' ? 'info' : 'gray'),
                TextColumn::make('quadrant')
                    ->label('Cuadrante')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Posición')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('root_count')
                    ->label('Raíces')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('universal')
                    ->label('Universal')
                    ->state(fn (ToothDefinition $record): string|int|null => $record->universal_number ?? $record->universal_letter),
                TextColumn::make('display_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('arch')
                    ->label('Arcada')
                    ->options(['upper' => 'Superior', 'lower' => 'Inferior']),
                SelectFilter::make('tooth_type')
                    ->label('Tipo de diente')
                    ->options([
                        'incisivo' => 'Incisivo',
                        'canino' => 'Canino',
                        'premolar' => 'Premolar',
                        'molar' => 'Molar',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
