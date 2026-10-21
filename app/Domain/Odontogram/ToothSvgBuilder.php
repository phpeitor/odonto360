<?php

namespace App\Domain\Odontogram;

use App\Models\ToothCondition;
use App\Models\ToothDefinition;

final class ToothSvgBuilder
{
    /** Colores PDF estáticos (sustituyen las CSS variables del tema). */
    private const PDF_INK = '#0F2A30';

    private const PDF_SURFACE_ALT = '#F1F5F9';

    private const PDF_BORDER = '#CBD5E1';

    /**
     * @param  ToothDefinition  $def  Definición de la pieza (arco, tipo, nº raíces...).
     * @param  string  $shownNumber  Número/letra ya resuelto (FDI o Universal).
     * @param  array<string,string>  $faceMap  ['v' => 'caries', 'o' => 'sano', ...].
     * @param  string|null  $wholeCode  Código de condición de pieza completa (o null).
     * @param  array<string,string>  $conditionColors  ['caries' => '#D6455A', ...].
     * @param  bool  $interactive  Si false, omite Alpine directives y usa colores hardcoded (para PDF).
     */
    public static function render(
        ToothDefinition $def,
        string $shownNumber,
        array $faceMap,
        ?string $wholeCode,
        array $conditionColors,
        bool $hasNote = false,
        bool $interactive = true,
    ): string {
        $code = $def->fdi_code;
        $isLower = $def->isLower();

        $missing = $wholeCode === ToothCondition::CODE_AUSENTE;
        $extraction = $wholeCode === ToothCondition::CODE_EXTRACCION;
        $implant = $wholeCode === ToothCondition::CODE_IMPLANTE;
        $crown = $wholeCode === ToothCondition::CODE_CORONA;
        $endo = $wholeCode === ToothCondition::CODE_ENDODONCIA;
        $bridge = $wholeCode === ToothCondition::CODE_PUENTE;

        // Layout (viewBox 0 0 100 180) — idéntico al HTML original.
        $crownTop = $isLower ? 0 : 88;
        $rootBaseY = $isLower ? $crownTop + 92 : $crownTop;
        $rootApexY = $isLower ? $crownTop + 92 + 62 : 26;
        $numberY = $isLower ? 169 : 15;
        $bridgeY = $isLower ? 158 : 24;
        $uid = 't'.$code;

        $rootPoly = self::buildRoots($def->root_count, $rootBaseY, $rootApexY, $uid, $endo, $interactive);

        $defs = self::buildDefs($uid, $crownTop, $endo);

        $crownContent = $implant
            ? self::buildImplant($crownTop, $uid, $interactive)
            : self::buildCrownFaces(
                $code,
                $crownTop,
                $uid,
                $faceMap,
                $conditionColors,
                $missing,
                $crown,
                $wholeCode,
                $interactive,
            );

        $extractionMark = $extraction ? self::diagonalCross($crownTop, '#9C2B44') : '';
        $missingMark = $missing ? self::diagonalCross($crownTop, '#5A6B78') : '';
        $bridgeMark = $bridge
            ? sprintf('<rect x="30" y="%d" width="40" height="4" rx="2" fill="#5B67C7"/>', $bridgeY)
            : '';

        $numberBlock = self::buildNumberBlock($code, $numberY, $shownNumber, $interactive);

        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;
        $noteIndicator = $hasNote
            ? sprintf('<circle cx="88" cy="%d" r="4" fill="#F59E0B" stroke="%s" stroke-width="1"/>', $numberY + 0.5, $ink)
            : '';

        $crownOpacity = $missing ? '0.45' : '1';
        $crownGroup = "<g opacity=\"{$crownOpacity}\">{$crownContent}{$extractionMark}{$missingMark}</g>";
        $rootGroup = "<g opacity=\"{$crownOpacity}\">{$rootPoly}</g>";

        $svgBody = $defs.$numberBlock.$rootGroup.$crownGroup.$bridgeMark.$noteIndicator;

        $class = $interactive ? ' class="tooth-svg"' : '';

        return "<svg viewBox=\"0 0 100 180\"{$class} xmlns=\"http://www.w3.org/2000/svg\">{$svgBody}</svg>";
    }

    private static function buildRoots(int $nRoots, float $rootBaseY, float $rootApexY, string $uid, bool $endo, bool $interactive = true): string
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

    private static function buildDefs(string $uid, float $crownTop, bool $endo): string
    {
        $rootGradTop = $endo ? '#A996E6' : '#FFFFFF';
        $rootGradBottom = $endo ? '#6B4FC4' : '#DCE7E9';

        return <<<SVG
        <defs>
          <clipPath id="clip-{$uid}"><rect x="4" y="{$crownTop}" width="92" height="92" rx="20" ry="20"/></clipPath>
          <linearGradient id="rootgrad-{$uid}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="{$rootGradTop}"/>
            <stop offset="100%" stop-color="{$rootGradBottom}"/>
          </linearGradient>
          <linearGradient id="implgrad-{$uid}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#57697D"/>
            <stop offset="100%" stop-color="#2E3A48"/>
          </linearGradient>
        </defs>
        SVG;
    }

    private static function buildCrownFaces(
        int $code,
        float $crownTop,
        string $uid,
        array $faceMap,
        array $conditionColors,
        bool $missing,
        bool $crownRing,
        ?string $wholeCode = null,
        bool $interactive = true,
    ): string {
        $divider = 'rgba(15,42,48,0.32)';
        $fill = fn (string $face) => $faceMap[$face] === ToothCondition::CODE_SANO ? '#FFFFFF' : ($conditionColors[$faceMap[$face]] ?? '#FFFFFF');

        $zone = function (string $face, string $points) use ($code, $crownTop, $divider, $fill, $interactive) {
            $shifted = self::shift($points, $crownTop);
            $name = ToothCondition::FACE_LABELS[$face];

            if ($interactive) {
                return sprintf(
                    '<polygon class="zone" data-face="%s" points="%s" fill="%s" stroke="%s" stroke-width="1" x-tooltip="{ content: \'%s\', theme: $store.theme }" '
                    .'@click="$wire.selectZone(%d, \'%s\')" '
                    .'@mouseenter="hover = {code: %d, face: \'%s\'}" @mouseleave="hover = null"/>',
                    $face, $shifted, $fill($face), $divider, e($name), $code, $face, $code, $name
                );
            }

            return sprintf(
                '<polygon points="%s" fill="%s" stroke="%s" stroke-width="1"/>',
                $shifted, $fill($face), $divider
            );
        };

        // Tinte suave de toda la corona para condiciones de pieza completa
        // (endodoncia, corona, puente…): hace visible el marcado más allá del número.
        $tint = in_array($wholeCode, [ToothCondition::CODE_ENDODONCIA, ToothCondition::CODE_CORONA, ToothCondition::CODE_PUENTE], true)
            ? ($conditionColors[$wholeCode] ?? null)
            : null;
        $tintRect = $tint
            ? sprintf(
                '<rect x="5" y="%s" width="90" height="90" rx="19" ry="19" fill="%s" fill-opacity="0.22" pointer-events="none"/>',
                $crownTop + 6,
                $tint
            )
            : '';

        $ellipse = $missing ? '' : sprintf(
            '<ellipse cx="26" cy="%s" rx="14" ry="9" fill="#FFFFFF" opacity="0.22" pointer-events="none"/>',
            $crownTop + 20
        );

        $outerRing = $crownRing ? sprintf(
            '<rect x="1.5" y="%s" width="97" height="97" rx="22" ry="22" fill="none" stroke="#B8863A" stroke-width="4"/>',
            $crownTop + 1.5
        ) : '';

        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;

        return sprintf(
            '<g clip-path="url(#clip-%s)">%s%s%s%s%s%s%s</g>'
            .'<rect x="4" y="%s" width="92" height="92" rx="20" ry="20" fill="none" stroke="%s" stroke-width="1.6"/>%s',
            $uid,
            $zone('v', '6,6 94,6 50,50'),
            $zone('d', '94,6 94,94 50,50'),
            $zone('p', '6,94 94,94 50,50'),
            $zone('m', '6,6 6,94 50,50'),
            $zone('o', '50,26 74,50 50,74 26,50'),
            $ellipse,
            $tintRect,
            $crownTop + 4,
            $ink,
            $outerRing
        );
    }

    private static function buildImplant(float $crownTop, string $uid, bool $interactive = true): string
    {
        $points = self::shift('50,8 88,29 88,71 50,92 12,71 12,29', $crownTop);
        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;

        return sprintf(
            '<polygon points="%s" fill="url(#implgrad-%s)" stroke="%s" stroke-width="1.5" stroke-linejoin="round"/>'
            .'<line x1="50" y1="%s" x2="50" y2="%s" stroke="#AEB9C4" stroke-width="2"/>'
            .'<line x1="26" y1="%s" x2="74" y2="%s" stroke="#AEB9C4" stroke-width="1.5"/>'
            .'<line x1="22" y1="%s" x2="78" y2="%s" stroke="#AEB9C4" stroke-width="2"/>'
            .'<line x1="26" y1="%s" x2="74" y2="%s" stroke="#AEB9C4" stroke-width="1.5"/>',
            $points, $uid, $ink,
            $crownTop + 15, $crownTop + 85,
            $crownTop + 38, $crownTop + 38,
            $crownTop + 50, $crownTop + 50,
            $crownTop + 62, $crownTop + 62
        );
    }

    private static function diagonalCross(float $crownTop, string $color): string
    {
        return sprintf(
            '<line x1="8" y1="%s" x2="92" y2="%s" stroke="%s" stroke-width="4" stroke-linecap="round"/>'
            .'<line x1="92" y1="%s" x2="8" y2="%s" stroke="%s" stroke-width="4" stroke-linecap="round"/>',
            $crownTop + 8, $crownTop + 92, $color,
            $crownTop + 8, $crownTop + 92, $color
        );
    }

    private static function buildNumberBlock(int $code, float $numberY, string $shownNumber, bool $interactive = true): string
    {
        $surfaceAlt = $interactive ? 'var(--surface-alt)' : self::PDF_SURFACE_ALT;
        $border = $interactive ? 'var(--border)' : self::PDF_BORDER;
        $ink = $interactive ? 'var(--ink)' : self::PDF_INK;

        $plate = sprintf(
            '<rect x="34" y="%s" width="32" height="19" rx="9.5" fill="%s" stroke="%s" stroke-width="1"/>',
            $numberY - 10,
            $surfaceAlt,
            $border
        );

        if ($interactive) {
            return $plate.sprintf(
                '<text x="50" y="%s" text-anchor="middle" dominant-baseline="middle" '
                .'font-family="\'JetBrains Mono\',monospace" font-size="15" font-weight="700" fill="%s" '
                .'style="cursor:pointer" @click="$wire.selectZone(%d, \'numero\')" '
                .'@mouseenter="hover = {code: %d, face: \'Pieza completa\'}" @mouseleave="hover = null">%s</text>',
                $numberY + 0.5, $ink, $code, $code, e($shownNumber)
            );
        }

        return $plate.sprintf(
            '<text x="50" y="%s" text-anchor="middle" dominant-baseline="middle" '
            .'font-family="\'JetBrains Mono\',monospace" font-size="15" font-weight="700" fill="%s">%s</text>',
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
