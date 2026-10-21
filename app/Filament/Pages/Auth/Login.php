<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Login con la mascota animada: en lugar de redirigir de inmediato, avisa al navegador (`login-succeeded`
 * / `login-failed`) para que reproduzca la animación; el propio navegador navega después del éxito.
 */
class Login extends BaseLogin
{
    /** Sin título: la mascota y el logo ya identifican la pantalla. */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $this->dispatch('login-succeeded', url: session()->pull('url.intended', Filament::getUrl()));

        return null;
    }

    protected function throwFailureValidationException(): never
    {
        $this->dispatch('login-failed');

        parent::throwFailureValidationException();
    }
}
