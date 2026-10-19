<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UbuntuxPatientLookup
{
    /**
     * @return array{first_name: string, last_name: string, birth_date: string}|null
     */
    public function findByDocument(string $documentNumber): ?array
    {
        $response = Http::acceptJson()
            ->timeout(8)
            ->get($this->buildUrl($documentNumber))
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        $person = $this->extractPerson($payload);

        if ($person === null) {
            return null;
        }

        $firstName = $this->value($person, 'NOMBRES', 'nombres', 'first_name');
        $paternalSurname = $this->value($person, 'PATERNO', 'paterno');
        $maternalSurname = $this->value($person, 'MATERNO', 'materno');
        $birthDate = $this->value($person, 'NACIMIENTO', 'nacimiento', 'birth_date');

        if ($firstName === null || ($paternalSurname === null && $maternalSurname === null) || $birthDate === null) {
            return null;
        }

        return [
            'first_name' => $firstName,
            'last_name' => trim(implode(' ', array_filter([$paternalSurname, $maternalSurname]))),
            'birth_date' => $birthDate,
        ];
    }

    private function buildUrl(string $documentNumber): string
    {
        return (string) config('services.ubuntux.url').urlencode($documentNumber);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function extractPerson(array $payload): ?array
    {
        foreach (['data', 'resultado', 'result'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload = $payload[$key];
                break;
            }
        }

        if (array_is_list($payload)) {
            $payload = $payload[0] ?? null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function value(array $person, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $person[$key] ?? null;

            if (is_scalar($value) && filled($value)) {
                return Str::squish((string) $value);
            }
        }

        return null;
    }
}
