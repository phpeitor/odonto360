<?php

namespace App\Livewire;

use App\Models\OdontogramTreatmentLog;
use App\Models\Patient;
use App\Models\ToothCondition;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class DentalTreatment extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Patient $patient;

    public function mount(Patient $patient): void
    {
        $this->patient = $patient;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OdontogramTreatmentLog::query()
                    ->whereHas('odontogram', fn ($q) => $q->where('patient_id', $this->patient->id))
                    ->with(['condition', 'user'])
            )
            ->striped()
            ->searchable()
            ->paginated([5, 10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('fdi_code')
                    ->label('Diente')
                    ->sortable(),
                TextColumn::make('face')
                    ->label('Cara')
                    ->formatStateUsing(fn ($state): string => $state ? (ToothCondition::FACE_LABELS[$state] ?? $state) : 'Pieza completa')
                    ->sortable(),
                TextColumn::make('condition.label')
                    ->label('Condición')
                    ->sortable(),
                TextColumn::make('observation')
                    ->label('Observación')
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label('Registró')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('registered_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                // ...
            ])
            ->headerActions([
                Action::make('register')
                    ->label('Registro odontograma')
                    ->icon('fas-tooth')
                    ->outlined()
                    ->modalWidth(Width::ScreenTwoExtraLarge)
                    ->modalHeading('Registro odontograma')
                    ->modalSubmitAction(false)
                    ->form([
                        ViewField::make('view')
                            ->view('filament.patients.odontogram-board')
                            ->viewData(fn (): array => ['record' => $this->patient])
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                // ...
            ]);
    }

    public function render()
    {
        return view('livewire.dental-treatment');
    }
}
