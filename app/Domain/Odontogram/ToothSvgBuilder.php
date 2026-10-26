<?php

namespace App\Domain\Odontogram;

use App\Models\ToothCondition;
use App\Models\ToothDefinition;

/**
 * SVG de una pieza según el gráfico de la NTS N° 188-MINSA/DGIESP-2022:
 * - Recuadro de siglas por encima (arcada superior) o por debajo (arcada inferior) de la pieza (5.12).
 * - Hallazgos dibujados solo en azul (buen estado) o rojo (mal estado) (5.13).
 * - Franja libre del lado oclusal para las flechas que van «fuera del gráfico» (extruida, intruida, giroversión).
 */
final class ToothSvgBuilder
{
    /** Colores PDF estáticos (sustituyen las CSS variables del tema). */
    private const PDF_INK = '#0F2A30';

    private const PDF_SURFACE_ALT = '#F1F5F9';

    private const PDF_BORDER = '#CBD5E1';

    /** Alto del cuerpo de la pieza (número + raíz + corona), del recuadro de siglas y de la franja oclusal. */
    private const BODY_HEIGHT = 180;

    private const BOX_HEIGHT = 28;

    private const GUTTER_HEIGHT = 16;

    public const VIEWBOX_HEIGHT = self::BOX_HEIGHT + 4 + self::BODY_HEIGHT + self::GUTTER_HEIGHT;

    /** Lo que un tramo de varias piezas sobresale de la pieza para unirse con la vecina (cubre el hueco entre piezas). */
    private const SPAN_REACH = 12;

    /**
     * @param  ToothDefinition  $def  Definición de la pieza (arco, tipo, nº raíces...).
     * @param  string  $shownNumber  Número/letra ya resuelto (FDI o Universal).
     * @param  array<string, array{code: string, abbreviation: ?string, state: string, glyph: ?string}>  $faces  Hallazgo de cada cara.
     * @param  array<string, array{abbreviation: ?string, state: string, glyph: ?string}>  $findings  Hallazgos de pieza completa por código.
     * @param  list<array{glyph: ?string, state: string, position: string}>  $spans  Tramo de cada hallazgo de varias piezas (ver SpanLayout).
     * @param  bool  $interactive  Si false, omite Alpine directives y usa colores hardcoded (para PDF).
     */
    public static function render(
        ToothDefinition $def,
        string $shownNumber,
        array $faces = [],
        array $findings = [],
        array $spans = [],
        bool $hasNote = false,
        bool $interactive = true,
    ): string {
        $class = $interactive ? ' class="tooth-svg"' : '';

        return sprintf(
            '<svg viewBox="0 0 100 %d"%s xmlns="http://www.w3.org/2000/svg">%s</svg>',
            self::VIEWBOX_HEIGHT,
            $class,
            self::content($def, $shownNumber, $faces, $findings, $spans, $hasNote, $interactive)
        );
    }

    /**
     * Una fila completa en un solo SVG (para el PDF): los tramos de varias piezas quedan continuos.
     *
     * @param  list<array{def: ToothDefinition, shownNumber: string, faces: array, findings: array, spans: array}>  $teeth
     * @param  float  $gap  Separación entre piezas, en unidades del viewBox (100 = ancho de una pieza).
     */
    public static function renderRow(array $teeth, float $gap): string
    {
        $width = count($teeth) * 100 + max(count($teeth) - 1, 0) * $gap;
        $groups = '';

        foreach (array_values($teeth) as $i => $tooth) {
            $groups .= sprintf(
                '<g transform="translate(%s,0)">%s</g>',
                round($i * (100 + $gap), 2),
                self::content($tooth['def'], $tooth['shownNumber'], $tooth['faces'], $tooth['findings'], $tooth['spans'], false, false)
            );
        }

        return sprintf(
            '<svg viewBox="0 0 %s %d" xmlns="http://www.w3.org/2000/svg">%s</svg>',
            round($width, 2),
            self::VIEWBOX_HEIGHT,
            $groups
        );
    }

    private static function content(
        ToothDefinition $def,
        string $shownNumber,
        array $faces,
        array $findings,
        array $spans,
        bool $hasNote,
        bool $interactive,
    ): string {
        $code = $def->fdi_code;
        $isLower = $def->isLower();

        // Layout del cuerpo (viewBox 0 0 100 180), idéntico al HTML original.
        $crownTop = $isLower ? 0 : 88;
        $rootBaseY = $isLower ? $crownTop + 92 : $crownTop;
        $rootApexY = $isLower ? $crownTop + 92 + 62 : 26;
        $numberY = $isLower ? 169 : 15;
        $uid = 't'.$code;

        $layout = [
            'crownTop' => $crownTop,
            'rootBaseY' => $rootBaseY,
            'rootApexY' => $rootApexY,
            'numberY' => $numberY,
            // Borde oclusal y sentido «hacia afuera» de la pieza (hacia la franja oclusal).
            'occlusalY' => $isLower ? 0 : self::BODY_HEIGHT,
            'outward' => $isLower ? -1 : 1,
        ];

        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;

        $body = self::buildDefs($uid, $crownTop)
            .self::buildNumberBlock($code, $numberY, $shownNumber, $interactive)
            .self::buildRoots($def->root_count, $rootBaseY, $rootApexY, $uid, $interactive)
            .self::buildCrownFaces($code, $crownTop, $uid, $faces, $interactive);

        foreach ($findings as $finding) {
            $body .= self::glyph($finding['glyph'] ?? null, ToothCondition::colorForState($finding['state']), $layout);
        }

        foreach ($spans as $span) {
            $body .= self::spanGlyph($span['glyph'], ToothCondition::colorForState($span['state']), $span['position'], $layout);
        }

        if ($hasNote) {
            $body .= sprintf('<circle cx="88" cy="%d" r="4" fill="#F59E0B" stroke="%s" stroke-width="1"/>', $numberY + 0.5, $ink);
        }

        // Arcada superior: recuadro arriba y franja oclusal abajo; la inferior, al revés.
        $bodyOffset = $isLower ? self::GUTTER_HEIGHT : self::BOX_HEIGHT + 4;
        $boxY = $isLower ? self::GUTTER_HEIGHT + self::BODY_HEIGHT + 4 : 0;

        return sprintf('<g transform="translate(0,%d)">%s</g>', $bodyOffset, $body)
            .self::buildAbbreviationBox($boxY, self::abbreviations($faces, $findings), $interactive);
    }

    /**
     * Siglas del recuadro, sin repetir, con el color de su estado.
     *
     * @return list<array{text: string, color: string}>
     */
    private static function abbreviations(array $faces, array $findings): array
    {
        $items = [];

        foreach ([...array_values($faces), ...array_values($findings)] as $finding) {
            if (($finding['abbreviation'] ?? null) === null) {
                continue;
            }

            $color = ToothCondition::colorForState($finding['state']);
            $items[$finding['abbreviation'].$color] = ['text' => $finding['abbreviation'], 'color' => $color];
        }

        return array_values($items);
    }

    /** @param list<array{text: string, color: string}> $items */
    private static function buildAbbreviationBox(float $y, array $items, bool $interactive): string
    {
        $border = $interactive ? 'var(--border)' : self::PDF_BORDER;
        $box = sprintf(
            '<rect x="3" y="%s" width="94" height="%d" rx="3" fill="none" stroke="%s" stroke-width="1.5"/>',
            $y + 1,
            self::BOX_HEIGHT - 2,
            $border
        );

        if ($items === []) {
            return $box;
        }

        // Texto monoespaciado: el ancho de cada sigla es predecible (≈0.6 em por carácter).
        $chars = array_sum(array_map(fn (array $item): int => mb_strlen($item['text']), $items)) + count($items) - 1;
        $fontSize = match (true) {
            $chars <= 6 => 14,
            $chars <= 9 => 11,
            default => 9,
        };
        $charWidth = $fontSize * 0.6;
        $maxChars = (int) floor(88 / $charWidth);

        // Lo que no entra se resume con «+»; el detalle completo queda en el listado de hallazgos (5.14).
        $visible = [];
        $used = 0;

        foreach ($items as $i => $item) {
            $separator = $visible === [] ? 0 : 1;
            $length = mb_strlen($item['text']) + $separator;
            // Si quedan siglas por detrás, se reserva espacio para « +».
            $limit = $i === count($items) - 1 ? $maxChars : $maxChars - 2;

            if ($used + $length > $limit) {
                $visible[] = ['text' => '+', 'color' => $item['color']];
                $used += 1 + $separator;
                break;
            }

            $visible[] = $item;
            $used += $length;
        }

        $x = 50 - ($used * $charWidth) / 2;
        $texts = '';

        foreach ($visible as $i => $item) {
            if ($i > 0) {
                $x += $charWidth;
            }

            $texts .= sprintf(
                '<text x="%s" y="%s" dominant-baseline="middle" font-family="\'JetBrains Mono\',monospace" '
                .'font-size="%d" font-weight="700" fill="%s">%s</text>',
                round($x, 1),
                $y + self::BOX_HEIGHT / 2 + 0.5,
                $fontSize,
                $item['color'],
                e($item['text'])
            );
            $x += mb_strlen($item['text']) * $charWidth;
        }

        return $box.$texts;
    }

    private static function buildRoots(int $nRoots, float $rootBaseY, float $rootApexY, string $uid, bool $interactive = true): string
    {
        $trunkY = $rootBaseY + ($rootApexY - $rootBaseY) * 0.20;

        if ($nRoots === 1) {
            $parts = [self::branch(28, 72, $rootBaseY, 50, $rootApexY, 6)];
        } elseif ($nRoots === 2) {
            $parts = [
                sprintf('M28,%s L72,%s L62,%s L38,%s Z', $rootBaseY, $rootBaseY, round($trunkY, 1), round($trunkY, 1)),
                self::branch(38, 51, $trunkY, 32, $rootApexY, 4.5),
                self::branch(49, 62, $trunkY, 68, $rootApexY, 4.5),
            ];
        } else {
            $shortApex = $trunkY + ($rootApexY - $trunkY) * 0.80;
            $parts = [
                sprintf('M28,%s L72,%s L62,%s L38,%s Z', $rootBaseY, $rootBaseY, round($trunkY, 1), round($trunkY, 1)),
                self::branch(38, 49, $trunkY, 26, $rootApexY, 4),
                self::branch(45, 55, $trunkY, 50, $shortApex, 4),
                self::branch(51, 62, $trunkY, 74, $rootApexY, 4),
            ];
        }

        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;
        $paths = array_map(
            fn (string $d) => sprintf(
                '<path d="%s" fill="url(#rootgrad-%s)" stroke="%s" stroke-width="1.6" stroke-linejoin="round"/>',
                $d,
                $uid,
                $ink
            ),
            $parts
        );

        return implode('', $paths);
    }

    /** Ramal de raíz con punta redondeada. */
    private static function branch(
        float $xLeft,
        float $xRight,
        float $yTop,
        float $xApex,
        float $yApex,
        float $apexHalf = 4.0
    ): string {
        $dirY = $yApex > $yTop ? 1 : -1;
        $mid1 = $yTop + ($yApex - $yTop) * 0.30;
        $mid2 = $yTop + ($yApex - $yTop) * 0.72;
        $yTip = $yApex - $apexHalf * 0.9 * $dirY;
        $lc1 = $xLeft + ($xApex - $xLeft) * 0.08;
        $lc2 = $xLeft + ($xApex - $xLeft) * 0.5;
        $rc1 = $xRight - ($xRight - $xApex) * 0.08;
        $rc2 = $xRight - ($xRight - $xApex) * 0.5;
        $sweep = $dirY > 0 ? 1 : 0;

        return sprintf(
            'M%s,%s C%s,%s %s,%s %s,%s A%s,%s 0 0 %d %s,%s C%s,%s %s,%s %s,%s Z',
            round($xLeft, 1), round($yTop, 1),
            round($lc1, 1), round($mid1, 1), round($lc2, 1), round($mid2, 1),
            round($xApex - $apexHalf, 1), round($yTip, 1),
            round($apexHalf, 1), round($apexHalf, 1), $sweep,
            round($xApex + $apexHalf, 1), round($yTip, 1),
            round($rc2, 1), round($mid2, 1), round($rc1, 1), round($mid1, 1),
            round($xRight, 1), round($yTop, 1)
        );
    }

    private static function buildDefs(string $uid, float $crownTop): string
    {
        return <<<SVG
        <defs>
          <clipPath id="clip-{$uid}"><rect x="4" y="{$crownTop}" width="92" height="92" rx="20" ry="20"/></clipPath>
          <linearGradient id="rootgrad-{$uid}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#FFFFFF"/>
            <stop offset="100%" stop-color="#DCE7E9"/>
          </linearGradient>
        </defs>
        SVG;
    }

    /** @param array<string, array{code: string, abbreviation: ?string, state: string, glyph: ?string}> $faces */
    private static function buildCrownFaces(
        int $code,
        float $crownTop,
        string $uid,
        array $faces,
        bool $interactive = true,
    ): string {
        $divider = 'rgba(15,42,48,0.32)';

        // fill: superficie pintada; tint: pintada a media opacidad; outline: solo el contorno (restauración temporal).
        $paint = function (string $face) use ($faces, $divider): string {
            $finding = $faces[$face] ?? null;

            if ($finding === null || $finding['code'] === ToothCondition::CODE_SANO) {
                return sprintf('fill="#FFFFFF" stroke="%s" stroke-width="1"', $divider);
            }

            $color = ToothCondition::colorForState($finding['state']);

            return match ($finding['glyph'] ?? 'fill') {
                'outline' => sprintf('fill="#FFFFFF" stroke="%s" stroke-width="3.5" stroke-linejoin="round"', $color),
                'tint' => sprintf('fill="%s" fill-opacity="0.4" stroke="%s" stroke-width="1"', $color, $divider),
                default => sprintf('fill="%s" stroke="%s" stroke-width="1"', $color, $divider),
            };
        };

        $zone = function (string $face, string $points) use ($code, $crownTop, $paint, $interactive) {
            $shifted = self::shift($points, $crownTop);
            $name = ToothCondition::FACE_LABELS[$face];

            if ($interactive) {
                return sprintf(
                    '<polygon class="zone" data-face="%s" points="%s" %s x-tooltip="{ content: \'%s\', theme: $store.theme }" '
                    .'@click="$wire.selectZone(%d, \'%s\')" '
                    .'@mouseenter="hover = {code: %d, face: \'%s\'}" @mouseleave="hover = null"/>',
                    $face, $shifted, $paint($face), e($name), $code, $face, $code, $name
                );
            }

            return sprintf('<polygon points="%s" %s/>', $shifted, $paint($face));
        };

        $ellipse = sprintf(
            '<ellipse cx="26" cy="%s" rx="14" ry="9" fill="#FFFFFF" opacity="0.22" pointer-events="none"/>',
            $crownTop + 20
        );

        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;

        return sprintf(
            '<g clip-path="url(#clip-%s)">%s%s%s%s%s%s</g>'
            .'<rect x="4" y="%s" width="92" height="92" rx="20" ry="20" fill="none" stroke="%s" stroke-width="1.6"/>',
            $uid,
            $zone('v', '6,6 94,6 50,50'),
            $zone('d', '94,6 94,94 50,50'),
            $zone('p', '6,94 94,94 50,50'),
            $zone('m', '6,6 6,94 50,50'),
            $zone('o', '50,26 74,50 50,74 26,50'),
            $ellipse,
            $crownTop + 4,
            $ink
        );
    }

    /**
     * Dibujo de un hallazgo de pieza completa (numerales 6.1.x de la norma). Los hallazgos que solo
     * llevan sigla en el recuadro (implante, movilidad, macrodoncia…) no tienen dibujo.
     *
     * @param  array{crownTop: float, rootBaseY: float, rootApexY: float, numberY: float, occlusalY: float, outward: int}  $l
     */
    private static function glyph(?string $glyph, string $color, array $l): string
    {
        $crownTop = $l['crownTop'];
        $occlusal = $l['occlusalY'];
        $out = $l['outward'];
        $line = fn (float $x1, float $y1, float $x2, float $y2, float $width = 4.5): string => sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s" stroke-linecap="round" pointer-events="none"/>',
            $x1, $y1, $x2, $y2, $color, $width
        );

        return match ($glyph) {
            // 6.1.3 / 6.1.4: cuadrado bordeando la corona.
            'corona' => sprintf(
                '<rect x="1.5" y="%s" width="97" height="97" rx="6" fill="none" stroke="%s" stroke-width="5" pointer-events="none"/>',
                $crownTop + 1.5,
                $color
            ),
            // 6.1.8: línea vertical en la raíz unida a un cuadrado en la corona.
            'espigo' => $line(50, $l['rootBaseY'], 50, $l['rootBaseY'] + ($l['rootApexY'] - $l['rootBaseY']) * 0.65)
                .sprintf(
                    '<rect x="37" y="%s" width="26" height="26" fill="none" stroke="%s" stroke-width="4" pointer-events="none"/>',
                    $crownTop + 37,
                    $color
                ),
            // 6.1.37: línea recta vertical a lo largo de la raíz.
            'conducto' => $line(50, $l['rootBaseY'], 50, $l['rootApexY'] - ($l['rootApexY'] - $l['rootBaseY']) * 0.06),
            // 6.1.27: pulpa coronal pintada.
            'pulpotomia' => sprintf(
                '<rect x="34" y="%s" width="32" height="22" rx="3" fill="%s" pointer-events="none"/>',
                $crownTop + 39,
                $color
            ),
            // 6.1.10: línea sobre la corona.
            'fractura' => $line(16, $crownTop + 14, 84, $crownTop + 78, 5),
            // 6.1.20: aspa sobre toda la pieza.
            'aspa' => (function () use ($line, $l, $crownTop): string {
                $top = min($l['rootApexY'], $crownTop);
                $bottom = max($l['rootApexY'], $crownTop + 92);

                return $line(10, $top, 90, $bottom, 5).$line(90, $top, 10, $bottom, 5);
            })(),
            // 6.1.23: flecha en zigzag dirigida hacia el plano oclusal.
            'erupcion' => (function () use ($color, $l, $out, $occlusal): string {
                $start = $l['rootBaseY'] - $out * 30;
                $end = $occlusal - $out * 10;
                $step = ($end - $start) / 6;
                $points = [];

                for ($i = 0; $i <= 6; $i++) {
                    $points[] = sprintf('%s,%s', $i === 6 ? 50 : ($i % 2 === 0 ? 40 : 60), round($start + $step * $i, 1));
                }

                return sprintf(
                    '<path d="M%s" fill="none" stroke="%s" stroke-width="4" stroke-linejoin="round" pointer-events="none"/>',
                    implode(' L', $points),
                    $color
                ).self::arrowHead(50, $end + $out * 6, $out, $color);
            })(),
            // 6.1.24: flecha fuera de la pieza, en sentido externo desde oclusal.
            'extruida' => $line(50, $occlusal + $out * 2, 50, $occlusal + $out * 11, 3.5)
                .self::arrowHead(50, $occlusal + $out * 15, $out, $color),
            // 6.1.25: flecha fuera de la pieza, dirigida hacia oclusal.
            'intruida' => $line(50, $occlusal + $out * 14, 50, $occlusal + $out * 6, 3.5)
                .self::arrowHead(50, $occlusal + $out * 2, -$out, $color),
            // 6.1.13: flecha curva a nivel de la zona oclusal.
            'giroversion' => sprintf(
                '<path d="M24,%s Q50,%s 72,%s" fill="none" stroke="%s" stroke-width="3.5" stroke-linecap="round" pointer-events="none"/>',
                $occlusal + $out * 4,
                $occlusal + $out * 18,
                $occlusal + $out * 6,
                $color
            ).sprintf(
                '<path d="M80,%s L68,%s L70,%s Z" fill="%s" pointer-events="none"/>',
                $occlusal + $out * 3,
                $occlusal + $out * 2,
                $occlusal + $out * 12,
                $color
            ),
            // 6.1.21: triángulo sobre la raíz.
            'clavija' => sprintf(
                '<path d="M50,%s L28,%s L72,%s Z" fill="none" stroke="%s" stroke-width="3.5" stroke-linejoin="round" pointer-events="none"/>',
                $l['rootApexY'],
                $l['rootApexY'] + $out * 34,
                $l['rootApexY'] + $out * 34,
                $color
            ),
            // 6.1.12: circunferencia encerrando el número de la pieza.
            'geminacion' => sprintf(
                '<ellipse cx="50" cy="%s" rx="29" ry="14" fill="none" stroke="%s" stroke-width="3" pointer-events="none"/>',
                $l['numberY'],
                $color
            ),
            default => '',
        };
    }

    /**
     * Parte que dibuja una pieza de un hallazgo de varias piezas, según su posición en el tramo
     * (`start`, `middle`, `end` o `single`). Las líneas sobresalen SPAN_REACH para unirse con la vecina.
     *
     * @param  array{crownTop: float, rootBaseY: float, rootApexY: float, numberY: float, occlusalY: float, outward: int}  $l
     */
    private static function spanGlyph(?string $glyph, string $color, string $position, array $l): string
    {
        $reach = self::SPAN_REACH;
        // Nivel de los ápices, un poco hacia la corona; `$out` apunta de los ápices hacia la corona.
        $out = $l['outward'];
        $apex = $l['rootApexY'] + $out * 5;
        $crownTop = $l['crownTop'];
        $isEdge = in_array($position, ['start', 'end', 'single'], true);

        // Tramo que cubre toda la pieza, o que nace en su centro (pilares, brackets).
        [$fullFrom, $fullTo] = match ($position) {
            'start' => [4, 100 + $reach],
            'middle' => [-$reach, 100 + $reach],
            'end' => [-$reach, 96],
            default => [4, 96],
        };
        [$centerFrom, $centerTo] = match ($position) {
            'start' => [50, 100 + $reach],
            'middle' => [-$reach, 100 + $reach],
            'end' => [-$reach, 50],
            default => [50, 50],
        };

        $line = fn (float $x1, float $y1, float $x2, float $y2, float $width = 3.5): string => $x1 === $x2 && $y1 === $y2 ? '' : sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s" stroke-linecap="butt" pointer-events="none"/>',
            $x1, $y1, $x2, $y2, $color, $width
        );
        $path = fn (string $d, float $width = 3.5): string => sprintf(
            '<path d="%s" fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round" pointer-events="none"/>',
            $d, $color, $width
        );

        return match ($glyph) {
            // 6.1.29: línea horizontal a nivel de los ápices, con líneas verticales sobre los pilares.
            'protesis_fija' => $line($centerFrom, $apex, $centerTo, $apex)
                .($isEdge ? $line(50, $apex - $out * 1.75, 50, $apex + $out * 14) : ''),
            // 6.1.30 / 6.1.31: dos líneas paralelas a nivel de los ápices.
            'protesis_completa', 'protesis_removible' => $line($fullFrom, $apex, $fullTo, $apex, 3)
                .$line($fullFrom, $apex + $out * 7, $fullTo, $apex + $out * 7, 3),
            // 6.1.1: cuadrados con una cruz en los extremos, unidos por una línea recta.
            'ortodoncia_fija' => $line(
                $position === 'start' || $position === 'single' ? 57 : $centerFrom,
                $apex,
                $position === 'end' || $position === 'single' ? 43 : $centerTo,
                $apex
            ).($isEdge ? sprintf(
                '<rect x="43" y="%s" width="14" height="14" fill="#FFFFFF" stroke="%s" stroke-width="2.5" pointer-events="none"/>',
                $apex - 7,
                $color
            ).$line(50, $apex - 7, 50, $apex + 7, 2).$line(43, $apex, 57, $apex, 2) : ''),
            // 6.1.2: línea en zigzag a la altura de los ápices.
            'ortodoncia_removible' => (function () use ($path, $fullFrom, $fullTo, $apex): string {
                $points = [];

                for ($x = $fullFrom, $i = 0; $x <= $fullTo; $x += 10, $i++) {
                    $points[] = sprintf('%s,%s', $x, $apex + ($i % 2 === 0 ? -4 : 4));
                }

                return $path('M'.implode(' L', $points), 3);
            })(),
            // 6.1.7: línea recta horizontal sobre las coronas.
            'edentulo' => $line($fullFrom, $crownTop + 46, $fullTo, $crownTop + 46, 4),
            // 6.1.6: paréntesis invertido «)(» entre las dos piezas.
            'diastema' => match ($position) {
                'start' => $path(sprintf('M98,%s Q110,%s 98,%s', $crownTop + 18, $crownTop + 46, $crownTop + 74)),
                'end' => $path(sprintf('M2,%s Q-10,%s 2,%s', $crownTop + 18, $crownTop + 46, $crownTop + 74)),
                default => '',
            },
            // 6.1.11: dos circunferencias interceptadas encerrando los números.
            'fusion' => in_array($position, ['start', 'end'], true) ? sprintf(
                '<ellipse cx="%s" cy="%s" rx="44" ry="13" fill="none" stroke="%s" stroke-width="3" pointer-events="none"/>',
                $position === 'start' ? 64 : 36,
                $l['numberY'],
                $color
            ) : '',
            // 6.1.26: circunferencia con la «S» entre los ápices de las piezas vecinas.
            'supernumeraria' => $position === 'start' ? sprintf(
                '<circle cx="106" cy="%s" r="9" fill="#FFFFFF" stroke="%s" stroke-width="2.5" pointer-events="none"/>'
                .'<text x="106" y="%s" text-anchor="middle" dominant-baseline="middle" font-family="Helvetica,Arial,sans-serif" '
                .'font-size="11" font-weight="700" fill="%s" pointer-events="none">S</text>',
                $apex,
                $color,
                $apex + 0.5,
                $color
            ) : '',
            // 6.1.38: dos flechas curvas entrecruzadas a la altura de los números.
            'transposicion' => $position === 'start' ? (function () use ($path, $color, $l, $out): string {
                // Bajo los números, hacia las raíces: cada flecha va de una pieza a la otra y se cruzan al medio.
                $near = $l['numberY'] + $out * 14;
                $far = $l['numberY'] + $out * 26;
                $control = $l['numberY'] - $out * 3;

                return $path(sprintf('M40,%s Q100,%s 160,%s', $far, $control, $near), 3)
                    .$path(sprintf('M160,%s Q100,%s 40,%s', $far, $control, $near), 3)
                    .self::arrowHead(160, $near + $out * 5, $out, $color)
                    .self::arrowHead(40, $near + $out * 5, $out, $color);
            })() : '',
            default => '',
        };
    }

    /** Punta de flecha con el vértice en (x, y), apuntando hacia abajo (+1) o hacia arriba (-1). */
    private static function arrowHead(float $x, float $y, int $direction, string $color): string
    {
        return sprintf(
            '<path d="M%s,%s L%s,%s L%s,%s Z" fill="%s" pointer-events="none"/>',
            $x, $y,
            $x - 6, $y - $direction * 8,
            $x + 6, $y - $direction * 8,
            $color
        );
    }

    private static function buildNumberBlock(int $code, float $numberY, string $shownNumber, bool $interactive = true): string
    {
        $surfaceAlt = $interactive ? 'var(--surface-alt)' : self::PDF_SURFACE_ALT;
        $border = $interactive ? 'var(--border)' : self::PDF_BORDER;
        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;

        $plate = sprintf(
            '<rect x="27" y="%s" width="46" height="21" rx="10.5" fill="%s" stroke="%s" stroke-width="1"/>',
            $numberY - 10.5,
            $surfaceAlt,
            $border
        );

        if ($interactive) {
            return $plate.sprintf(
                '<text x="50" y="%s" text-anchor="middle" dominant-baseline="middle" '
                .'font-family="\'JetBrains Mono\',monospace" font-size="17" font-weight="800" fill="%s" '
                .'style="cursor:pointer" @click="$wire.selectZone(%d, \'numero\')" '
                .'@mouseenter="hover = {code: %d, face: \'Pieza completa\'}" @mouseleave="hover = null">%s</text>',
                $numberY + 0.5, $ink, $code, $code, e($shownNumber)
            );
        }

        return $plate.sprintf(
            '<text x="50" y="%s" text-anchor="middle" dominant-baseline="middle" '
            .'font-family="\'JetBrains Mono\',monospace" font-size="17" font-weight="800" fill="%s">%s</text>',
            $numberY + 0.5, $ink, e($shownNumber)
        );
    }

    /** Desplaza verticalmente una lista de puntos "x,y x,y ...". */
    private static function shift(string $points, float $dy): string
    {
        $pairs = preg_split('/\s+/', trim($points));

        $shifted = array_map(function (string $pair) use ($dy) {
            [$x, $y] = array_map('floatval', explode(',', $pair));

            return $x.','.round($y + $dy, 1);
        }, $pairs);

        return implode(' ', $shifted);
    }
}
