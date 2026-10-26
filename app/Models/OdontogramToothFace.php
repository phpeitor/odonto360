<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdontogramToothFace extends Model
{
    protected $fillable = [
        'odontogram_tooth_id',
        'face',
        'condition_id',
        'abbreviation',
        'state',
    ];

    public function tooth(): BelongsTo
    {
        return $this->belongsTo(
            related: OdontogramTooth::class,
            foreignKey: 'odontogram_tooth_id'
        );
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(
            related: ToothCondition::class,
            foreignKey: 'condition_id'
        );
    }
}
