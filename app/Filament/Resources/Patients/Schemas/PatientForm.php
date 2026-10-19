<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enum\PatientSex;
use App\Models\Patient;
use App\Services\UbuntuxPatientLookup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('document_number')
                    ->label('Documento de identidad')
                    ->length(8)
                    ->numeric()
                    ->rule('digits:8')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $documentNumber = preg_replace('/\D+/', '', (string) $state);

                        if (strlen($documentNumber) !== 8) {
                            Notification::make()
                                ->title('Documento incompleto')
                                ->body('Ingrese un documento de identidad de 8 dígitos.')
                                ->warning()
                                ->send();

                            return;
                        }

                        try {
                            $patient = app(UbuntuxPatientLookup::class)->findByDocument($documentNumber);
                        } catch (ConnectionException|RequestException) {
                            Notification::make()
                                ->title('No se pudo consultar el documento')
                                ->body('Verifique la conexión con el servicio de identificación e inténtelo nuevamente.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($patient === null) {
                            Notification::make()
                                ->title('Documento no encontrado')
                                ->body('No se encontraron datos para el documento ingresado.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $set('first_name', $patient['first_name']);
                        $set('last_name', $patient['last_name']);
                        $set('birth_date', $patient['birth_date']);

                        Notification::make()
                            ->title('Datos del paciente encontrados')
                            ->success()
                            ->send();
                    })
                    ->required(),
                TextInput::make('first_name')
                    ->label('Nombre'),
                TextInput::make('last_name')
                    ->label('Apellidos'),
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->native(false),
                Select::make('sex')
                    ->label('Sexo')
                    ->options(PatientSex::class)
                    ->native(false),
                TextInput::make('phone')
                    ->label('Teléfono'),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email(),
                ViewField::make('view')
                    ->view('filament.patients.odontogram-board')
                    ->visible(fn (?Patient $record) => (bool) ($record?->exists ?? false)),
            ]);
    }
}
