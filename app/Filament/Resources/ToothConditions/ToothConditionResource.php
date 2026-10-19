<?php

namespace App\Filament\Resources\ToothConditions;

use App\Filament\Resources\ToothConditions\Pages\CreateToothCondition;
use App\Filament\Resources\ToothConditions\Pages\EditToothCondition;
use App\Filament\Resources\ToothConditions\Pages\ListToothConditions;
use App\Filament\Resources\ToothConditions\Schemas\ToothConditionForm;
use App\Filament\Resources\ToothConditions\Tables\ToothConditionsTable;
use App\Models\ToothCondition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ToothConditionResource extends Resource
{
    protected static ?string $model = ToothCondition::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-tooth-thin';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationLabel = 'Condiciones dentales';

    protected static ?string $modelLabel = 'condición dental';

    protected static ?string $pluralModelLabel = 'condiciones dentales';

    public static function form(Schema $schema): Schema
    {
        return ToothConditionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToothConditionsTable::configure($table);
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
            'index' => ListToothConditions::route('/'),
            'create' => CreateToothCondition::route('/create'),
            'edit' => EditToothCondition::route('/{record}/edit'),
        ];
    }
}
