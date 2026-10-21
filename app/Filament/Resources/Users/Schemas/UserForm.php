<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enum\PatientSex;
use App\Services\UbuntuxPatientLookup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\HtmlString;

class UserForm
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
                        .'Consultando datos del odontólogo...</span>',
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
                            $person = app(UbuntuxPatientLookup::class)->findByDocument($documentNumber);
                        } catch (ConnectionException|RequestException) {
                            Notification::make()
                                ->title('No se pudo consultar el documento')
                                ->body('Verifique la conexión con el servicio de identificación e inténtelo nuevamente.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($person === null) {
                            Notification::make()
                                ->title('Documento no encontrado')
                                ->body('No se encontraron datos para el documento ingresado.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $set('name', trim("{$person['first_name']} {$person['last_name']}"));
                        $set('phone', $person['phone']);
                        $set('sex', $person['sex']);

                        Notification::make()
                            ->title('Datos del odontólogo encontrados')
                            ->success()
                            ->send();
                    })
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('name')
                    ->label('Nombre completo')
                    ->helperText('Se completa automáticamente con los datos del documento.')
                    ->required()
                    ->maxLength(255),
                Select::make('sex')
                    ->label('Sexo')
                    ->options(PatientSex::class)
                    ->native(false),
                TextInput::make('phone')
                    ->label('Teléfono'),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? 'Use al menos 8 caracteres.'
                        : 'Déjelo vacío para conservar la contraseña actual.')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ]);
    }
}
