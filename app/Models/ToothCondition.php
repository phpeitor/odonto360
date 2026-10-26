<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Hallazgo clínico de la nomenclatura de la NTS N° 188-MINSA/DGIESP-2022 (numeral 6.1).
 * Se dibuja en azul si está en buen estado y en rojo si está en mal estado (5.13).
 */
class ToothCondition extends Model
{
    use HasFactory;

    /** Códigos de condición que el resto del dominio referencia por nombre. */
    public const CODE_SANO = 'sano';

    public const CODE_CARIES = 'caries';

    public const CODE_OBTURACION = 'obturacion';

    public const CODE_SELLANTE = 'sellante';

    public const CODE_FRACTURA = 'fractura';

    public const CODE_CORONA = 'corona';

    public const CODE_ENDODONCIA = 'endodoncia';

    public const CODE_AUSENTE = 'ausente';

    public const CODE_IMPLANTE = 'implante';

    public const STATE_GOOD = 'bueno';

    public const STATE_BAD = 'malo';

    /** Únicos colores permitidos por la norma (5.13): azul = buen estado, rojo = mal estado. */
    public const COLOR_GOOD = '#1D4ED8';

    public const COLOR_BAD = '#DC2626';

    public const STATE_LABELS = [
        self::STATE_GOOD => 'Buen estado',
        self::STATE_BAD => 'Mal estado',
    ];

    public const CATEGORY_LABELS = [
        'sano' => 'Sano',
        'patologia' => 'Patología',
        'restauracion' => 'Restauraciones y tratamientos',
        'anomalia' => 'Anomalías y posición',
    ];

    /** Etiquetas legibles de las caras dentales. */
    public const FACE_LABELS = [
        'v' => 'Vestibular',
        'o' => 'Oclusal/Incisal',
        'p' => 'Palatino/Lingual',
        'm' => 'Mesial',
        'd' => 'Distal',
    ];

    protected $fillable = [
        'code',
        'label',
        'color',
        'target',
        'category',
        'allowed_states',
        'abbreviations',
        'glyph',
        'sort_order',
    ];

    protected $casts = [
        'abbreviations' => 'array',
    ];

    /** El color de la paleta lo fija la norma según el estado; no se elige a mano. */
    protected static function booted(): void
    {
        static::saving(function (ToothCondition $condition): void {
            $condition->color = match (true) {
                $condition->code === self::CODE_SANO => '#FFFFFF',
                $condition->allowed_states === self::STATE_BAD => self::COLOR_BAD,
                default => self::COLOR_GOOD,
            };
        });
    }

    public static function colorForState(?string $state): string
    {
        return $state === self::STATE_BAD ? self::COLOR_BAD : self::COLOR_GOOD;
    }

    public function appliesToFace(): bool
    {
        return in_array($this->target, ['face', 'both'], true);
    }

    public function appliesToTooth(): bool
    {
        return in_array($this->target, ['tooth', 'both'], true);
    }

    /** @return list<string> Estados con los que se puede registrar el hallazgo. */
    public function states(): array
    {
        return match ($this->allowed_states) {
            self::STATE_GOOD => [self::STATE_GOOD],
            self::STATE_BAD => [self::STATE_BAD],
            default => [self::STATE_GOOD, self::STATE_BAD],
        };
    }

    public function defaultState(): string
    {
        return $this->states()[0];
    }

    public function allowsState(?string $state): bool
    {
        return in_array($state, $this->states(), true);
    }

    /** @return list<string> */
    public function abbreviationOptions(): array
    {
        return array_values($this->abbreviations ?? []);
    }

    /** Sin siglas definidas no se admite ninguna; con siglas, debe ser una de ellas. */
    public function allowsAbbreviation(?string $abbreviation): bool
    {
        $options = $this->abbreviationOptions();

        return $options === [] ? $abbreviation === null : in_array($abbreviation, $options, true);
    }
}
