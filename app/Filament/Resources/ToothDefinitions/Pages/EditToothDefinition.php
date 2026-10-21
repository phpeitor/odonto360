<?php

namespace App\Filament\Resources\ToothDefinitions\Pages;

use App\Filament\Resources\ToothDefinitions\ToothDefinitionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditToothDefinition extends EditRecord
{
    protected static string $resource = ToothDefinitionResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
