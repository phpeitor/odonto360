<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('tells the browser to play the success animation before redirecting', function (): void {
    $user = User::factory()->create(['password' => 'secreto-123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'secreto-123'])
        ->call('authenticate')
        ->assertNoRedirect()
        ->assertDispatched('login-succeeded', url: Filament::getUrl());

    $this->assertAuthenticatedAs($user);
});

it('sends the user back to the page they asked for after the animation', function (): void {
    $user = User::factory()->create(['password' => 'secreto-123']);
    session(['url.intended' => url('/patients')]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'secreto-123'])
        ->call('authenticate')
        ->assertDispatched('login-succeeded', url: url('/patients'));
});

it('tells the browser to play the fail animation on wrong credentials', function (): void {
    $user = User::factory()->create(['password' => 'secreto-123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'incorrecta'])
        ->call('authenticate')
        ->assertDispatched('login-failed')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('renders the tooth mascot on the login page only', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('data-tooth-canvas', false)
        ->assertSee('images/login.riv');

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertDontSee('data-tooth-canvas', false);
});

it('shows its own heading instead of the default one', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Bienvenido de nuevo')
        ->assertDontSee('Entre a su cuenta');
});

it('turns off native browser validation so the server marks empty fields', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('novalidate', false)
        ->assertSee('data-login-form', false);
});

it('marks empty fields and explains it in a toast', function (): void {
    Livewire::test(Login::class)
        ->fillForm(['email' => '', 'password' => ''])
        ->call('authenticate')
        ->assertHasFormErrors(['email' => 'required', 'password' => 'required'])
        ->assertDispatched('login-failed')
        ->assertNotified('Completa los campos marcados');

    $this->assertGuest();
});

it('shows the wrong credentials message in a toast', function (): void {
    $user = User::factory()->create(['password' => 'secreto-123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'incorrecta'])
        ->call('authenticate')
        ->assertNotified('No pudimos iniciar sesión');
});

it('greets the user in a toast after signing in', function (): void {
    $user = User::factory()->create(['name' => 'Ana', 'password' => 'secreto-123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'secreto-123'])
        ->call('authenticate')
        ->assertNotified('¡Hola, Ana!');
});

it('lets a signed-in user into the panel outside the local environment', function (): void {
    config()->set('app.env', 'production');

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get('/odontogram-board')
        ->assertOk();
});
