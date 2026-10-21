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
use Illuminate\Support\HtmlString;

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
                    ->helperText(new HtmlString(
                        '<span wire:loading.flex wire:target="data.document_number" class="hidden items-center gap-1.5 text-primary-600">'
                        .'<svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                        .'<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
                        .'<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>'
                        .'</svg>'
                        .'Consultando datos del paciente...</span>',
                    ))
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
