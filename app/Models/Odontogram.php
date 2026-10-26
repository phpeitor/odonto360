<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Odontogram extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'practitioner_id',
        'dentition',
        'numbering_system',
        'notes',
        'specifications',
        'examined_at',
    ];

    protected $casts = [
        'examined_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            related: Patient::class,
            foreignKey: 'patient_id'
        );
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'practitioner_id'
        );
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(
            related: OdontogramTooth::class,
            foreignKey: 'odontogram_id'
        );
    }

    /** Hallazgos que abarcan varias piezas (prótesis, aparatos ortodónticos, diastema…). */
    public function spans(): HasMany
    {
        return $this->hasMany(
            related: OdontogramSpan::class,
            foreignKey: 'odontogram_id'
        );
    }

    public function treatmentLog(): HasMany
    {
        return $this->hasMany(
            related: OdontogramTreatmentLog::class,
            foreignKey: 'odontogram_id'
        );
    }
}
