<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Enum\PatientSex;
use App\Models\Patient;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\View\View;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->searchable()
            ->paginated([5, 10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->getStateUsing(fn (Patient $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->action(
                        ViewAction::make('patient-tratamient')
                            ->modal()
                            ->modalHeading(fn (Patient $record): string => $record->fullName())
                            ->modalContent(fn (Patient $record): View => view('filament.patients.dental-treatment', ['patient' => $record]))
                    )
                    ->color('amber'),
                TextColumn::make('document_number')
                    ->label('Documento'),
                // TextColumn::make('birth_date'),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->formatStateUsing(fn (PatientSex $state): string => '')
                    ->tooltip(fn (PatientSex $state): string => $state->getLabel())
                    ->alignLeft(),
                // TextColumn::make('phone'),
                // TextColumn::make('email'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('patient-tratamient')
                    ->label('Tratamientos Dentales')
                    ->button()
                    ->icon('fas-tooth')
                    // ->iconButton()
                    ->outlined()
                    ->slideOver()
                    ->modalHeading('Historial de tratamientos')
                    ->modalSubmitAction(false)
                    // ->modalHeading(fn(Patient $record): string => $record->fullName())
                    ->fillForm(fn (Patient $record): array => [
                        'document_number' => $record->document_number,
                        'birth_date' => $record->birth_date,
                        'sex' => $record->sex,
                        'phone' => $record->phone,
                        'email' => $record->email,
                    ])
                    ->form([
                        Grid::make(3)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('document_number')
                                    ->label('DNI')
                                    ->disabled(),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de nacimiento')
                                    ->native(false)
                                    ->disabled(),
                                Select::make('sex')
                                    ->label('Género')
                                    ->options(PatientSex::class)
                                    ->native(false)
                                    ->disabled(),
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->disabled(),
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->disabled(),
                            ]),
                        ViewField::make('view')
                            ->view('filament.patients.dental-treatment')
                            ->viewData(fn (Patient $record): array => ['patient' => $record]),
                    ]),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
