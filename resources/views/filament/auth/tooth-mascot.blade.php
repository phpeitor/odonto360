{{-- Mascota del login (Rive): mira el correo, se tapa los ojos con la contraseña y reacciona al resultado. --}}
<div wire:ignore class="flex justify-center" data-tooth-login>
    <canvas
        data-tooth-canvas
        data-src="{{ asset('images/login.riv') }}"
        class="h-44 w-44 sm:h-52 sm:w-52"
        role="img"
        aria-label="Mascota de Odonto360"
    ></canvas>
    @vite('resources/js/login-rive.js')
</div>
