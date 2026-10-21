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

    /** Mayor de edad (18 años cumplidos a hoy); sin fecha de nacimiento no se puede afirmar. */
    public function isAdult(): bool
    {
        return $this->birth_date !== null && $this->birth_date->age >= self::ADULT_AGE;
    }

    /** El odontograma único del paciente: todo tratamiento se registra sobre él. */
    public function odontogram(): HasOne
    {
        return $this->hasOne(Odontogram::class, 'patient_id');
    }
}
