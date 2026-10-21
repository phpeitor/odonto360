<?php

use App\Filament\Resources\ToothDefinitions\Pages\EditToothDefinition;
use App\Filament\Resources\ToothDefinitions\Pages\ListToothDefinitions;
use App\Filament\Resources\ToothDefinitions\ToothDefinitionResource;
use App\Models\ToothDefinition;
use App\Models\User;
use Database\Seeders\ToothDefinitionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
    $this->seed(ToothDefinitionSeeder::class);
});

it('splits the definitions into adult and child tabs with their counts', function (): void {
    $adult = ToothDefinition::where('fdi_code', 11)->first();
    $child = ToothDefinition::where('fdi_code', 51)->first();

    Livewire::test(ListToothDefinitions::class)
        ->assertSet('activeTab', 'adult')
        ->assertCanSeeTableRecords([$adult])
        ->assertCanNotSeeTableRecords([$child])
        ->set('activeTab', 'child')
        ->assertCanSeeTableRecords([$child])
        ->assertCanNotSeeTableRecords([$adult]);

    expect((new ListToothDefinitions)->getTabs())->toHaveKeys(['adult', 'child']);
});

it('does not allow creating or deleting definitions', function (): void {
    $definition = ToothDefinition::where('fdi_code', 11)->first();

    expect(ToothDefinitionResource::canCreate())->toBeFalse()
        ->and(ToothDefinitionResource::canDelete($definition))->toBeFalse()
        ->and(ToothDefinitionResource::canDeleteAny())->toBeFalse()
        ->and(array_keys(ToothDefinitionResource::getPages()))->toBe(['index', 'edit']);
});

it('only lets the anatomy and display fields be edited', function (): void {
    $definition = ToothDefinition::where('fdi_code', 11)->first();

    Livewire::test(EditToothDefinition::class, ['record' => $definition->getKey()])
        ->fillForm(['fdi_code' => 99, 'root_count' => 2, 'display_order' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    $definition->refresh();

    expect($definition->fdi_code)->toBe(11)
        ->and($definition->root_count)->toBe(2)
        ->and($definition->display_order)->toBe(5);
});

it('saves a definition whose display order starts at 0 without changing it', function (): void {
    $definition = ToothDefinition::where('fdi_code', 18)->first();

    expect($definition->display_order)->toBe(0);

    Livewire::test(EditToothDefinition::class, ['record' => $definition->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();
});
