<?php

namespace App\Models;

use App\Enum\PatientSex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'document_number',
        'birth_date',
        'sex',
        'phone',
        'email',
    ];

    protected $casts = [
        'sex' => PatientSex::class,
        'birth_date' => 'date',
    ];

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public const ADULT_AGE = 18;

    public const PERMANENT_TEETH_AGE = 6;

    public const MIXED_DENTITION_MAX_AGE = 12;

    /** Mayor de edad (18 años cumplidos a hoy); sin fecha de nacimiento no se puede afirmar. */
    public function isAdult(): bool
    {
        return $this->birth_date !== null && $this->birth_date->age >= self::ADULT_AGE;
    }

    /** Menor de 6 años: aún no tiene dientes permanentes; sin fecha de nacimiento no se puede afirmar. */
    public function isUnderPermanentTeethAge(): bool
    {
        return $this->birth_date !== null && $this->birth_date->age < self::PERMANENT_TEETH_AGE;
    }

    /**
     * Denticiones que el paciente puede tener según su edad: `adult` (permanente), `child` (temporal)
     * o `mixed` (temporal + permanente, de 6 a 17 años).
     */
    public function allowsDentition(string $dentition): bool
    {
        return match ($dentition) {
            'adult' => ! $this->isUnderPermanentTeethAge(),
            'child' => ! $this->isAdult(),
            'mixed' => ! $this->isUnderPermanentTeethAge() && ! $this->isAdult(),
            default => false,
        };
    }

    /** Dentición con la que se abre un odontograma nuevo: infantil antes de los 6, mixta de 6 a 12 y adulta después. */
    public function defaultDentition(): string
    {
        if ($this->isUnderPermanentTeethAge()) {
            return 'child';
        }

        if ($this->birth_date !== null && $this->birth_date->age <= self::MIXED_DENTITION_MAX_AGE) {
            return 'mixed';
        }

        return 'adult';
    }

    /** El odontograma único del paciente: todo tratamiento se registra sobre él. */
    public function odontogram(): HasOne
    {
        return $this->hasOne(Odontogram::class, 'patient_id');
    }
}
