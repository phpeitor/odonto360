<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Form;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Login con la mascota animada: en lugar de redirigir de inmediato, avisa al navegador (`login-succeeded`
 * / `login-failed`) para que reproduzca la animación; el propio navegador navega después del éxito.
 *
 * Los mensajes se muestran como toast; el campo con error queda solo con el borde rojo.
 */
class Login extends BaseLogin
{
    protected bool $hasInvalidCredentials = false;

    public function getHeading(): string|Htmlable
    {
        return 'Bienvenido de nuevo';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Ingresa tus datos para continuar';
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            $this->dispatch('login-failed');
            $this->sendFailureNotification($exception);

            throw $exception;
        }

        if ($response === null) {
            return null;
        }

        Notification::make()
            ->success()
            ->title('¡Hola, '.Filament::auth()->user()?->name.'!')
            ->body('Abriendo tu panel…')
            ->send();

        $this->dispatch('login-succeeded', url: session()->pull('url.intended', Filament::getUrl()));

        return null;
    }

    /**
     * Sin `novalidate` el navegador frena el envío con su globo nativo (en inglés) y el servidor nunca valida.
     */
    public function getFormContentComponent(): Component
    {
        /** @var Form $form */
        $form = parent::getFormContentComponent();

        return $form->extraAttributes(['novalidate' => true, 'data-login-form' => true]);
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getEmailFormComponent();

        return $component
            ->placeholder('nombre@clinica.com')
            ->prefixIcon(Heroicon::OutlinedEnvelope)
            ->validationMessages([
                'required' => 'Ingresa tu correo electrónico.',
                'email' => 'El correo electrónico no es válido.',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getPasswordFormComponent();

        return $component
            ->placeholder('••••••••')
            ->prefixIcon(Heroicon::OutlinedLockClosed)
            ->validationMessages([
                'required' => 'Ingresa tu contraseña.',
            ]);
    }

    protected function throwFailureValidationException(): never
    {
        $this->hasInvalidCredentials = true;

        parent::throwFailureValidationException();
    }

    protected function sendFailureNotification(ValidationException $exception): void
    {
        if ($this->hasInvalidCredentials) {
            Notification::make()
                ->danger()
                ->title('No pudimos iniciar sesión')
                ->body(__('filament-panels::auth/pages/login.messages.failed'))
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title('Completa los campos marcados')
            ->body(implode(' ', Arr::flatten($exception->errors())))
            ->send();
    }
}
