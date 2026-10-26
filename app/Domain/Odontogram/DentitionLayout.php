<?php

namespace App\Domain\Odontogram;

use App\Models\ToothDefinition;
use Illuminate\Support\Collection;

/**
 * Filas de piezas que forman el odontograma de cada dentición, de arriba hacia abajo:
 * - Adulta / infantil: una fila por arcada.
 * - Mixta (formato MINSA, 52 piezas): permanente superior, temporal superior,
 *   temporal inferior y permanente inferior; las temporales quedan junto a la línea media.
 */
final class DentitionLayout
{
    public const DENTITIONS = ['adult', 'child', 'mixed'];

    /**
     * @return array{upper: list<Collection<int, ToothDefinition>>, lower: list<Collection<int, ToothDefinition>>}
     */
    public static function rows(string $dentition): array
    {
        $sets = $dentition === 'mixed' ? ['adult', 'child'] : [$dentition];

        $definitions = ToothDefinition::query()
            ->whereIn('dentition', $sets)
            ->orderBy('display_order')
            ->get()
            ->groupBy(['dentition', 'arch']);

        $row = fn (string $set, string $arch): Collection => $definitions->get($set)?->get($arch)?->values() ?? collect();

        return [
            'upper' => array_map(fn (string $set): Collection => $row($set, 'upper'), $sets),
            'lower' => array_map(fn (string $set): Collection => $row($set, 'lower'), array_reverse($sets)),
        ];
    }
}
