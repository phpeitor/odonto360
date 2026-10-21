<?php

namespace App\Filament\Resources\ToothDefinitions\Pages;

use App\Filament\Resources\ToothDefinitions\ToothDefinitionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateToothDefinition extends CreateRecord
{
    protected static string $resource = ToothDefinitionResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
