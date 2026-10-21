<?php

namespace App\Filament\Resources\ToothDefinitions\Pages;

use App\Filament\Resources\ToothDefinitions\ToothDefinitionResource;
use App\Models\ToothDefinition;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListToothDefinitions extends ListRecords
{
    protected static string $resource = ToothDefinitionResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $counts = ToothDefinition::query()
            ->selectRaw('dentition, count(*) as total')
            ->groupBy('dentition')
            ->pluck('total', 'dentition');

        return [
            'adult' => Tab::make('Adultos')
                ->icon('lineawesome-tooth-solid')
                ->badge($counts->get('adult', 0))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('dentition', 'adult')),
            'child' => Tab::make('Infantil')
                ->icon('phosphor-tooth-thin')
                ->badge($counts->get('child', 0))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('dentition', 'child')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'adult';
    }
}
