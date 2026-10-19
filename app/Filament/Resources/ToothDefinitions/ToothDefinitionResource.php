<?php

namespace App\Filament\Resources\ToothDefinitions;

use App\Filament\Resources\ToothDefinitions\Pages\CreateToothDefinition;
use App\Filament\Resources\ToothDefinitions\Pages\EditToothDefinition;
use App\Filament\Resources\ToothDefinitions\Pages\ListToothDefinitions;
use App\Filament\Resources\ToothDefinitions\Schemas\ToothDefinitionForm;
use App\Filament\Resources\ToothDefinitions\Tables\ToothDefinitionsTable;
use App\Models\ToothDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ToothDefinitionResource extends Resource
{
    protected static ?string $model = ToothDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = 'lineawesome-tooth-solid';

    protected static ?string $recordTitleAttribute = 'fdi_code';

    protected static ?string $navigationLabel = 'Definiciones dentales';

    protected static ?string $modelLabel = 'definición dental';

    protected static ?string $pluralModelLabel = 'definiciones dentales';

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
            'create' => CreateToothDefinition::route('/create'),
            'edit' => EditToothDefinition::route('/{record}/edit'),
        ];
    }
}
