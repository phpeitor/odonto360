<?php

use App\Services\UbuntuxPatientLookup;
use Illuminate\Support\Facades\Http;

it('maps the Ubuntux patient response to the patient form fields', function (): void {
    config()->set('services.ubuntux.url', 'https://ubuntux.test/config/api.php?dni=');

    Http::fake([
        'https://ubuntux.test/*' => Http::response([
            'PATERNO' => 'PERCA',
            'MATERNO' => 'VILCAHUAMAN',
            'NOMBRES' => 'WILLY JOEL',
            'NACIMIENTO' => '1990-06-26',
        ]),
    ]);

    $patient = app(UbuntuxPatientLookup::class)->findByDocument('48118043');

    expect($patient)->toBe([
        'first_name' => 'WILLY JOEL',
        'last_name' => 'PERCA VILCAHUAMAN',
        'birth_date' => '1990-06-26',
    ]);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://ubuntux.test/config/api.php?dni=48118043');
});

it('returns null when Ubuntux does not provide complete patient data', function (): void {
    config()->set('services.ubuntux.url', 'https://ubuntux.test/config/api.php?dni=');

    Http::fake([
        'https://ubuntux.test/*' => Http::response([
            'PATERNO' => 'PERCA',
        ]),
    ]);

    expect(app(UbuntuxPatientLookup::class)->findByDocument('48118043'))->toBeNull();
});
