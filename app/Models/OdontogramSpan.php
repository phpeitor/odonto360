<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hallazgo que abarca de la pieza inicial a la final de una misma fila (prótesis, diastema…). */
class OdontogramSpan extends Model
{
    protected $fillable = [
        'odontogram_id',
        'condition_id',
        'start_fdi_code',
        'end_fdi_code',
        'state',
    ];

    protected $casts = [
        'start_fdi_code' => 'integer',
        'end_fdi_code' => 'integer',
    ];

    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(
            related: ToothCondition::class,
            foreignKey: 'condition_id'
        );
    }
}
