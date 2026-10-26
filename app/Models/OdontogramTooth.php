<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdontogramTooth extends Model
{
    /** Mapa cara => condición con el que se muestran las caras sin hallazgos. */
    public const DEFAULT_FACE_MAP = [
        'v' => ToothCondition::CODE_SANO,
        'o' => ToothCondition::CODE_SANO,
        'p' => ToothCondition::CODE_SANO,
        'm' => ToothCondition::CODE_SANO,
        'd' => ToothCondition::CODE_SANO,
    ];

    protected $fillable = [
        'odontogram_id',
        'fdi_code',
        'whole_condition_id',
        'notes',
    ];

    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ToothDefinition::class, 'fdi_code', 'fdi_code');
    }

    public function wholeCondition(): BelongsTo
    {
        return $this->belongsTo(
            related: ToothCondition::class,
            foreignKey: 'whole_condition_id'
        );
    }

    public function faces(): HasMany
    {
        return $this->hasMany(
            related: OdontogramToothFace::class,
            foreignKey: 'odontogram_tooth_id'
        );
    }

    /** Hallazgos de pieza completa (varios por pieza, NTS 188 numeral 5.16). */
    public function findings(): HasMany
    {
        return $this->hasMany(
            related: OdontogramToothFinding::class,
            foreignKey: 'odontogram_tooth_id'
        );
    }

    /** Mapa cara => código de condición, ej. ['v' => 'caries', 'o' => 'sano', ...]. */
    public function faceMap(): array
    {
        $map = self::DEFAULT_FACE_MAP;

        foreach ($this->faces as $face) {
            $map[$face->face] = $face->condition->code;
        }

        return $map;
    }
}
