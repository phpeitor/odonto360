<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('usa la zona horaria America/Lima', function () {
    expect(config('app.timezone'))->toBe('America/Lima')
        ->and(date_default_timezone_get())->toBe('America/Lima')
        ->and(now()->getOffset())->toBe(-5 * 3600);
});

it('guarda los registros con la hora de Lima', function () {
    $this->travelTo(now('UTC')->setTime(3, 0));

    $user = User::factory()->create();

    expect($user->fresh()->getRawOriginal('created_at'))
        ->toBe(now('America/Lima')->format('Y-m-d H:i:s'));
});
