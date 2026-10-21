<?php

namespace App\Filament\Resources\ToothDefinitions;

use App\Filament\Resources\ToothDefinitions\Pages\EditToothDefinition;
use App\Filament\Resources\ToothDefinitions\Pages\ListToothDefinitions;
use App\Filament\Resources\ToothDefinitions\Schemas\ToothDefinitionForm;
use App\Filament\Resources\ToothDefinitions\Tables\ToothDefinitionsTable;
use App\Models\ToothDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ToothDefinitionResource extends Resource
{
    protected static ?string $model = ToothDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = 'lineawesome-tooth-solid';

    protected static ?string $recordTitleAttribute = 'fdi_code';

    protected static ?string $navigationLabel = 'Definiciones dentales';

    protected static ?string $modelLabel = 'definición dental';

    protected static ?string $pluralModelLabel = 'definiciones dentales';

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "Pieza {$record->fdi_code}";
    }

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['fdi_code', 'universal_number', 'universal_letter', 'tooth_type'];
    }

    /** @return array<string, string|null> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Tipo' => $record->tooth_type,
            'Dentición' => $record->dentition === 'child' ? 'Niño' : 'Adulto',
        ]);
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record ? "Pieza {$record->fdi_code}" : null;
    }

    /** Son las 52 piezas fijas que usan el tablero y los odontogramas: no se crean ni se borran. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ToothDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToothDefinitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToothDefinitions::route('/'),
            'edit' => EditToothDefinition::route('/{record}/edit'),
        ];
    }
}
