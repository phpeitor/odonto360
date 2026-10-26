<?php

namespace App\Livewire;

use App\Domain\Odontogram\Exceptions\CannotVoidTreatment;
use App\Domain\Odontogram\TreatmentVoider;
use App\Models\OdontogramTreatmentLog;
use App\Models\Patient;
use App\Models\ToothCondition;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
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
                    ->with(['condition', 'user', 'voidedBy'])
            )
            ->striped()
            ->recordClasses(fn (OdontogramTreatmentLog $record): ?string => $record->isVoided() ? 'opacity-60' : null)
            ->defaultSort('registered_at', 'desc')
            ->searchable()
            ->paginated([5, 10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('fdi_code')
                    ->label('Diente')
                    ->formatStateUsing(fn (OdontogramTreatmentLog $record): string => $record->teethLabel())
                    ->sortable(),
                TextColumn::make('face')
                    ->label('Cara')
                    ->state(fn (OdontogramTreatmentLog $record): string => match (true) {
                        $record->end_fdi_code !== null => 'Varias piezas',
                        $record->face !== null => ToothCondition::FACE_LABELS[$record->face] ?? $record->face,
                        default => 'Pieza completa',
                    })
                    ->sortable(),
                TextColumn::make('condition.label')
                    ->label('Condición')
                    ->description(fn (OdontogramTreatmentLog $record): ?string => collect([
                        $record->abbreviation,
                        $record->state ? ToothCondition::STATE_LABELS[$record->state] : null,
                    ])->filter()->implode(' · ') ?: null)
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
                TextColumn::make('status')
                    ->label('Estado')
                    ->state(fn (OdontogramTreatmentLog $record): string => $record->isVoided() ? 'Anulado' : 'Vigente')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Anulado' ? 'danger' : 'success')
                    ->description(fn (OdontogramTreatmentLog $record): ?string => $record->isVoided()
                        ? trim(($record->voidedBy?->name ?? '').' · '.$record->voided_at->format('d/m/Y H:i'), ' ·')
                        : null),
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
                Action::make('void')
                    ->label('Anular')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (OdontogramTreatmentLog $record): bool => ! $record->isVoided())
                    ->requiresConfirmation()
                    ->modalHeading('Anular tratamiento')
                    ->modalDescription(fn (OdontogramTreatmentLog $record): string => in_array($record->action, ['apply', 'clear'], true)
                        ? 'La zona volverá a su estado anterior en el odontograma y el registro quedará marcado como anulado (con tu nombre y la fecha). No se borra del historial.'
                        : 'El registro quedará marcado como anulado (con tu nombre y la fecha). Es anterior al control de reversas o es solo una observación, así que el odontograma no cambia: si hace falta, corrige la pieza con «Sano / Borrar».')
                    ->modalSubmitActionLabel('Anular')
                    ->action(function (OdontogramTreatmentLog $record): void {
                        try {
                            $reverted = app(TreatmentVoider::class)->void($record, Auth::user());
                        } catch (CannotVoidTreatment $exception) {
                            Notification::make()->title('No se pudo anular')->body($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Tratamiento anulado')
                            ->body($reverted ? 'La zona volvió a su estado anterior.' : 'El registro se marcó como anulado.')
                            ->success()
                            ->send();
                    }),
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
