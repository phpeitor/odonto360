<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre completo')
                    ->helperText('Nombre que aparecerá al seleccionar el odontólogo.')
                    ->required()
                    ->maxLength(255),
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
