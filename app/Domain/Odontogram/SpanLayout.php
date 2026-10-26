<?php

namespace App\Domain\Odontogram;

use App\Models\ToothDefinition;
use Illuminate\Support\Collection;

/**
 * Ubicación de los hallazgos de varias piezas sobre las filas del odontograma (DentitionLayout).
 * Cada hallazgo se reparte en tramos por pieza: inicio, medio y fin; así cada SVG de pieza dibuja su parte.
 */
final class SpanLayout
{
    /** Dos piezas contiguas: diastema, fusión, supernumeraria y transposición. */
    public const PAIR = ['diastema', 'fusion', 'supernumeraria', 'transposicion'];

    /** De la pieza inicial a la final de la fila: prótesis parciales y aparatos ortodónticos. */
    public const RANGE = ['protesis_fija', 'protesis_removible', 'ortodoncia_fija', 'ortodoncia_removible'];

    /** Toda la fila: prótesis completa y edéntulo total. */
    public const ARCH = ['protesis_completa', 'edentulo'];

    public static function kind(?string $glyph): string
    {
        return match (true) {
            in_array($glyph, self::PAIR, true) => 'pair',
            in_array($glyph, self::ARCH, true) => 'arch',
            default => 'range',
        };
    }

    /**
     * Piezas inicial y final (en el orden de la fila) del hallazgo marcado entre `$first` y `$second`;
     * null si no están en la misma fila o, para los de dos piezas, si no son contiguas.
     *
     * @param  array{upper: list<Collection<int, ToothDefinition>>, lower: list<Collection<int, ToothDefinition>>}  $rows
     * @return array{0: int, 1: int}|null
     */
    public static function resolve(array $rows, ?string $glyph, int $first, ?int $second = null): ?array
    {
        $kind = self::kind($glyph);

        foreach (self::fdiRows($rows) as $row) {
            $a = array_search($first, $row, true);

            if ($a === false) {
                continue;
            }

            if ($kind === 'arch') {
                return [$row[0], $row[count($row) - 1]];
            }

            $b = $second === null ? false : array_search($second, $row, true);

            if ($b === false || $a === $b || ($kind === 'pair' && abs($a - $b) !== 1)) {
                return null;
            }

            return [$row[min($a, $b)], $row[max($a, $b)]];
        }

        return null;
    }

    /**
     * Tramo que dibuja cada pieza, por código FDI. Los hallazgos cuyas piezas no están en una misma
     * fila de la dentición mostrada (p. ej. tras cambiar de dentición) no se dibujan.
     *
     * @param  array{upper: list<Collection<int, ToothDefinition>>, lower: list<Collection<int, ToothDefinition>>}  $rows
     * @param  iterable<array{glyph: ?string, state: string, start: int, end: int}>  $spans
     * @return array<int, list<array{glyph: ?string, state: string, position: string}>>
     */
    public static function segments(array $rows, iterable $spans): array
    {
        $segments = [];
        $fdiRows = self::fdiRows($rows);

        foreach ($spans as $span) {
            foreach ($fdiRows as $row) {
                $a = array_search($span['start'], $row, true);
                $b = array_search($span['end'], $row, true);

                if ($a === false || $b === false) {
                    continue;
                }

                [$from, $to] = [min($a, $b), max($a, $b)];

                for ($i = $from; $i <= $to; $i++) {
                    $segments[$row[$i]][] = [
                        'glyph' => $span['glyph'],
                        'state' => $span['state'],
                        'position' => match (true) {
                            $from === $to => 'single',
                            $i === $from => 'start',
                            $i === $to => 'end',
                            default => 'middle',
                        },
                    ];
                }

                break;
            }
        }

        return $segments;
    }

    /** @return list<list<int>> */
    private static function fdiRows(array $rows): array
    {
        return array_map(
            fn (Collection $row): array => $row->pluck('fdi_code')->map(fn ($code): int => (int) $code)->values()->all(),
            [...$rows['upper'], ...$rows['lower']]
        );
    }
}
