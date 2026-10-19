<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enum\PatientSex;
use App\Models\Patient;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('Nombre'),
                TextInput::make('last_name')
                    ->label('Apellidos'),
                TextInput::make('document_number')
                    ->label('Documento'),
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
