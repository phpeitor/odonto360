<?php

declare(strict_types=1);

namespace App\Domain\Odontogram;

use App\Models\Odontogram;
use App\Models\OdontogramTooth;
use App\Models\Patient;
use App\Models\ToothCondition;
use DragonOfMercy\PhpPdf\Color;
use DragonOfMercy\PhpPdf\Document;
use DragonOfMercy\PhpPdf\Font;
use DragonOfMercy\PhpPdf\Image;
use DragonOfMercy\PhpPdf\Orientation;
use DragonOfMercy\PhpPdf\Page;
use DragonOfMercy\PhpPdf\PageFormat;
use DragonOfMercy\PhpPdf\Unit;
use Illuminate\Support\Collection;

/**
 * PDF A4 horizontal del odontograma. Todo el contenido fluye con un cursor vertical (`$y`):
 * cada bloque se dibuja donde terminó el anterior y salta de página si no cabe, de modo que
 * nada puede superponerse.
 */
final class OdontogramPdfExport
{
    private const MARGIN = 15.0;

    private const CONTENT_WIDTH = 267.0;

    private const PAGE_WIDTH = 297.0;

    /** Límite inferior del contenido; debajo va el pie de página. */
    private const BOTTOM = 193.0;

    private const TOOTH_WIDTH = 12.0;

    private const TOOTH_HEIGHT = 21.6;

    private const TOOTH_GAP = 1.0;

    private const ACCENT = '#D97706';

    private const INK = '#111827';

    private const MUTED = '#6B7280';

    private const BORDER = '#E5E7EB';

    private const SOFT = '#F8FAFC';

    private Document $doc;

    private Font $font;

    private Font $boldFont;

    private Page $page;

    /** @var list<Page> */
    private array $pages = [];

    private float $y = 0.0;

    private Collection $conditionsByCode;

    private array $conditionColors;

    private Patient $patient;

    public function __construct()
    {
        $this->doc = new Document(Unit::MM);
        $this->font = Font::helvetica();
        $this->boldFont = Font::helvetica()->bold();
        $this->doc->setDefaultFont($this->font, 10);
        $this->doc->setMargins(self::MARGIN);
        $this->doc->setAutoPageBreak(false);

        $this->conditionsByCode = ToothCondition::orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $this->conditionColors = $this->conditionsByCode->map->color->all();
    }

    public function generate(Odontogram $odontogram): string
    {
        $this->patient = $odontogram->patient;

        $this->newPage();
        $this->renderHeader($odontogram);
        $this->renderOdontogramChart($odontogram);
        $this->renderLegend();
        $this->renderFindings($odontogram);
        $this->renderHistory($odontogram);

        $this->renderToothNotes($odontogram);

        if (trim((string) $odontogram->notes) !== '') {
            $this->renderNotes($odontogram);
        }

        $this->renderFooters();

        return $this->doc->output();
    }

    private function newPage(): void
    {
        $this->doc->addPage(PageFormat::A4, Orientation::LANDSCAPE);
        $this->page = $this->doc->getCurrentPage();
        $this->pages[] = $this->page;
        $this->y = $this->pages === [$this->page] ? 12.0 : 15.0;

        if (count($this->pages) > 1) {
            $this->write(self::MARGIN, $this->y + 3, "Odontograma · {$this->patient->fullName()}", $this->font, 8, self::MUTED);
            $this->hr($this->y + 6);
            $this->y += 12;
        }
    }

    /** Si el bloque de `$height` mm no cabe en la página actual, abre una nueva. */
    private function ensureSpace(float $height): bool
    {
        if ($this->y + $height <= self::BOTTOM) {
            return false;
        }

        $this->newPage();

        return true;
    }

    private function renderHeader(Odontogram $odontogram): void
    {
        $patient = $this->patient;

        $this->fillRect(self::MARGIN, $this->y, 2.2, 13, self::ACCENT);
        $this->write(self::MARGIN + 5, $this->y + 6.5, 'Odontograma', $this->boldFont, 19, self::INK);
        $this->write(self::MARGIN + 5, $this->y + 12, 'Odonto360', $this->font, 8.5, self::ACCENT);

        $examDate = ($odontogram->examined_at ?? now())->format('d/m/Y');
        $this->writeRight(self::MARGIN + self::CONTENT_WIDTH, $this->y + 4, 'FECHA DE EXAMEN', $this->font, 7, self::MUTED);
        $this->writeRight(self::MARGIN + self::CONTENT_WIDTH, $this->y + 11, $examDate, $this->boldFont, 13, self::INK);

        $this->y += 18;

        $boxHeight = 25.0;
        $this->fillRect(self::MARGIN, $this->y, self::CONTENT_WIDTH, $boxHeight, self::SOFT, self::BORDER);

        $age = $patient->birth_date?->age;
        $birth = $patient->birth_date
            ? $patient->birth_date->format('d/m/Y').($age !== null ? " ({$age} años)" : '')
            : '—';

        $columns = [self::MARGIN + 5, self::MARGIN + 100, self::MARGIN + 185];
        $fields = [
            [['Paciente', $patient->fullName()], ['Documento', $patient->document_number ?: '—'], ['Fecha de nacimiento', $birth]],
            [['Sexo', $patient->sex?->value ?? '—'], ['Teléfono', $patient->phone ?: '—'], ['Correo electrónico', $patient->email ?: '—']],
        ];

        foreach ($fields as $row => $cells) {
            $labelY = $this->y + 6 + $row * 11;

            foreach ($cells as $col => [$label, $value]) {
                $maxWidth = ($columns[$col + 1] ?? self::MARGIN + self::CONTENT_WIDTH) - $columns[$col] - 5;
                $this->write($columns[$col], $labelY, mb_strtoupper($label), $this->font, 6.5, self::MUTED);
                $this->write($columns[$col], $labelY + 4.6, $this->fit((string) $value, $maxWidth, $this->boldFont, 9.5), $this->boldFont, 9.5, self::INK);
            }
        }

        $this->y += $boxHeight + 5;

        $dentition = match ($odontogram->dentition) {
            'child' => 'Infantil (20 piezas)',
            'mixed' => 'Mixta (52 piezas)',
            default => 'Adulto (32 piezas)',
        };
        $numbering = $odontogram->numbering_system === 'universal' ? 'Universal' : 'FDI';
        $practitioner = $odontogram->practitioner?->name ?? 'Sin asignar';

        $this->write(
            self::MARGIN,
            $this->y + 3,
            "Odontólogo: {$practitioner}    ·    Dentición: {$dentition}    ·    Numeración: {$numbering}",
            $this->font,
            8.5,
            self::MUTED,
        );

        $this->y += 9;
    }

    private function renderOdontogramChart(Odontogram $odontogram): void
    {
        $numberingSystem = $odontogram->numbering_system;

        $teethByCode = $odontogram->teeth()
            ->with(['wholeCondition', 'faces.condition'])
            ->get()
            ->keyBy('fdi_code');

        $rows = DentitionLayout::rows($odontogram->dentition);
        $rowCount = count($rows['upper']) + count($rows['lower']);

        // Filas de cada arcada separadas 2 mm; 6 mm para la línea media y 6 mm de cierre.
        $chartHeight = self::TOOTH_HEIGHT * $rowCount + 2 * ($rowCount - 2) + 14;
        $this->ensureSpace($chartHeight);

        $widestRow = max(array_map(fn (Collection $row): int => $row->count(), [...$rows['upper'], ...$rows['lower']]));
        $chartWidth = $widestRow * (self::TOOTH_WIDTH + self::TOOTH_GAP) - self::TOOTH_GAP;
        $chartStartX = (self::PAGE_WIDTH - $chartWidth) / 2;

        foreach (['upper', 'lower'] as $arch) {
            if ($arch === 'lower') {
                $label = 'LÍNEA MEDIA';
                $labelWidth = $this->page->stringWidth($label, $this->font, 6.5);
                $center = self::PAGE_WIDTH / 2;
                $this->hr($this->y + 2, $chartStartX, $center - $labelWidth / 2 - 3);
                $this->hr($this->y + 2, $center + $labelWidth / 2 + 3, $chartStartX + $chartWidth);
                $this->write($center - $labelWidth / 2, $this->y + 3.2, $label, $this->font, 6.5, self::MUTED);
                $this->y += 6;
            }

            $archHeight = self::TOOTH_HEIGHT * count($rows[$arch]) + 2 * (count($rows[$arch]) - 1);
            $this->write(self::MARGIN, $this->y + $archHeight / 2, 'DER', $this->boldFont, 7, self::MUTED);
            $this->writeRight(self::MARGIN + self::CONTENT_WIDTH, $this->y + $archHeight / 2, 'IZQ', $this->boldFont, 7, self::MUTED);

            foreach ($rows[$arch] as $i => $row) {
                if ($i > 0) {
                    $this->y += 2;
                }

                $rowWidth = $row->count() * (self::TOOTH_WIDTH + self::TOOTH_GAP) - self::TOOTH_GAP;
                $this->renderArch($row, $teethByCode, $numberingSystem, (self::PAGE_WIDTH - $rowWidth) / 2, $this->y);
                $this->y += self::TOOTH_HEIGHT;
            }

            $this->y += $arch === 'upper' ? 2 : 6;
        }
    }

    private function renderArch(
        Collection $definitions,
        Collection $teethByCode,
        string $numberingSystem,
        float $startX,
        float $y,
    ): void {
        foreach ($definitions->values() as $i => $def) {
            $state = $teethByCode->get($def->fdi_code);
            $faceMap = $state?->faceMap() ?? OdontogramTooth::DEFAULT_FACE_MAP;
            $wholeCode = $state?->wholeCondition?->code;

            $svg = ToothSvgBuilder::render(
                def: $def,
                shownNumber: ToothNumbering::display($def->fdi_code, $def->dentition, $numberingSystem),
                faceMap: $faceMap,
                wholeCode: $wholeCode,
                conditionColors: $this->conditionColors,
                interactive: false,
            );

            $x = $startX + $i * (self::TOOTH_WIDTH + self::TOOTH_GAP);
            $this->page->image(Image::fromBytes($svg), $x, $y, self::TOOTH_WIDTH, self::TOOTH_HEIGHT);
        }
    }

    /** Leyenda de colores: solo las condiciones distintas de «sano». */
    private function renderLegend(): void
    {
        $items = $this->conditionsByCode->reject(fn (ToothCondition $c): bool => $c->code === ToothCondition::CODE_SANO);

        if ($items->isEmpty()) {
            return;
        }

        $this->ensureSpace(10);
        $x = self::MARGIN;

        foreach ($items as $condition) {
            $width = 6 + $this->page->stringWidth($condition->label, $this->font, 7.5) + 6;

            if ($x + $width > self::MARGIN + self::CONTENT_WIDTH) {
                $x = self::MARGIN;
                $this->y += 5;
                $this->ensureSpace(5);
            }

            $this->page->setFillColor(Color::hex($condition->color));
            $this->page->circle($x + 1.5, $this->y + 1.7, 1.3)->fill();
            $this->write($x + 4.2, $this->y + 2.6, $condition->label, $this->font, 7.5, self::INK);
            $x += $width;
        }

        $this->y += 10;
    }

    private function renderFindings(Odontogram $odontogram): void
    {
        $findings = $this->collectFindings(
            $odontogram->teeth()->with(['wholeCondition', 'faces.condition'])->get()
        );

        $this->sectionHeader('Hallazgos', 12);

        if ($findings === []) {
            $this->write(self::MARGIN, $this->y + 3, 'Sin hallazgos registrados.', $this->font, 9, self::MUTED);
            $this->y += 9;

            return;
        }

        $columns = 3;
        $columnWidth = self::CONTENT_WIDTH / $columns;
        $rows = (int) ceil(count($findings) / $columns);

        for ($row = 0; $row < $rows; $row++) {
            if ($this->ensureSpace(6)) {
                $this->sectionHeader('Hallazgos (continuación)', 12);
            }

            for ($col = 0; $col < $columns; $col++) {
                // Se llena por columnas para que el orden de lectura sea de arriba hacia abajo.
                $finding = $findings[$col * $rows + $row] ?? null;

                if ($finding === null) {
                    continue;
                }

                $x = self::MARGIN + $col * $columnWidth;
                $this->page->setFillColor(Color::hex($finding['color']));
                $this->page->circle($x + 1.5, $this->y + 1.8, 1.4)->fill();
                $text = "{$finding['fdi']} — {$finding['label']} ({$finding['face']})";
                $this->write($x + 4.5, $this->y + 2.8, $this->fit($text, $columnWidth - 7, $this->font, 8.5), $this->font, 8.5, self::INK);
            }

            $this->y += 5.2;
        }

        $this->y += 5;
    }

    /** @return list<array{fdi: int, label: string, color: string, face: string}> */
    private function collectFindings(Collection $teeth): array
    {
        $findings = [];

        foreach ($teeth as $tooth) {
            if ($tooth->wholeCondition && $tooth->wholeCondition->code !== ToothCondition::CODE_SANO) {
                $findings[] = [
                    'fdi' => $tooth->fdi_code,
                    'label' => $tooth->wholeCondition->label,
                    'color' => $tooth->wholeCondition->color,
                    'face' => 'Pieza completa',
                ];
            }

            foreach ($tooth->faces as $face) {
                if ($face->condition?->code !== ToothCondition::CODE_SANO) {
                    $findings[] = [
                        'fdi' => $tooth->fdi_code,
                        'label' => $face->condition->label,
                        'color' => $face->condition->color,
                        'face' => ToothCondition::FACE_LABELS[$face->face] ?? $face->face,
                    ];
                }
            }
        }

        return $findings;
    }

    private function renderHistory(Odontogram $odontogram): void
    {
        $log = $odontogram->treatmentLog()
            ->with(['condition', 'user', 'voidedBy'])
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->get();

        if ($log->isEmpty()) {
            $this->sectionHeader('Historial de tratamientos', 8);
            $this->write(self::MARGIN, $this->y + 3, 'Sin historial registrado todavía.', $this->font, 9, self::MUTED);
            $this->y += 9;

            return;
        }

        // [título, x, ancho]
        $columns = [
            ['Fecha', 15.0, 32.0],
            ['Pieza', 47.0, 14.0],
            ['Cara', 61.0, 34.0],
            ['Condición', 95.0, 42.0],
            ['Registró', 137.0, 40.0],
            ['Observación', 177.0, 105.0],
        ];

        // Se miden todas las filas antes de dibujar para no dejar el encabezado huérfano.
        $rows = $log->map(function ($entry) use ($columns): array {
            $faceLabel = $entry->face
                ? (ToothCondition::FACE_LABELS[$entry->face] ?? $entry->face)
                : 'Pieza completa';

            $cells = [
                $entry->registered_at->format('d/m/Y H:i'),
                (string) $entry->fdi_code,
                $faceLabel,
                $entry->condition?->label ?? '—',
                $entry->user?->name ?? '—',
                trim(($entry->voided_at
                    ? 'ANULADO'.($entry->voidedBy ? " por {$entry->voidedBy->name}" : '')." el {$entry->voided_at->format('d/m/Y H:i')}. "
                    : '').($entry->action === 'clear' ? 'Borrado. ' : '').($entry->observation ?? '')),
            ];

            $wrapped = [];
            foreach ($cells as $c => $text) {
                $wrapped[$c] = $this->wrap($text, $columns[$c][2] - 3, $this->font, 8);
            }

            return ['voided' => $entry->voided_at !== null, 'cells' => $wrapped, 'height' => max(array_map('count', $wrapped)) * 3.8 + 2.4];
        })->all();

        $this->sectionHeader('Historial de tratamientos', 6.4 + $rows[0]['height']);
        $this->tableHeader($columns);

        foreach ($rows as $i => $row) {
            if ($this->ensureSpace($row['height'])) {
                $this->tableHeader($columns);
            }

            if ($i % 2 === 1) {
                $this->fillRect(self::MARGIN, $this->y, self::CONTENT_WIDTH, $row['height'], self::SOFT);
            }

            foreach ($row['cells'] as $c => $textLines) {
                foreach ($textLines as $n => $line) {
                    $this->write($columns[$c][1] + 1.5, $this->y + 4 + $n * 3.8, $line, $this->font, 8, $row['voided'] ? self::MUTED : self::INK);
                }
            }

            $this->y += $row['height'];
        }

        $this->y += 4;
    }

    /** @param list<array{0: string, 1: float, 2: float}> $columns */
    private function tableHeader(array $columns): void
    {
        $this->fillRect(self::MARGIN, $this->y, self::CONTENT_WIDTH, 6.4, '#F1F5F9');

        foreach ($columns as [$title, $x]) {
            $this->write($x + 1.5, $this->y + 4.4, mb_strtoupper($title), $this->boldFont, 6.8, self::MUTED);
        }

        $this->y += 6.4;
    }

    /** Notas escritas en cada pieza (botón «N» del tablero). */
    private function renderToothNotes(Odontogram $odontogram): void
    {
        $notes = $odontogram->teeth()
            ->whereNotNull('notes')
            ->orderBy('fdi_code')
            ->get()
            ->filter(fn (OdontogramTooth $tooth): bool => trim((string) $tooth->notes) !== '');

        if ($notes->isEmpty()) {
            return;
        }

        $this->sectionHeader('Notas por pieza', 12);

        foreach ($notes as $tooth) {
            $lines = $this->wrap(preg_replace('/\s+/u', ' ', (string) $tooth->notes), self::CONTENT_WIDTH - 14, $this->font, 9);

            foreach ($lines as $n => $line) {
                if ($this->ensureSpace(5)) {
                    $this->sectionHeader('Notas por pieza (continuación)', 10);
                }

                if ($n === 0) {
                    $this->write(self::MARGIN, $this->y + 3.4, (string) $tooth->fdi_code, $this->boldFont, 9, self::ACCENT);
                }

                $this->write(self::MARGIN + 14, $this->y + 3.4, $line, $this->font, 9, self::INK);
                $this->y += 4.6;
            }

            $this->y += 1.6;
        }

        $this->y += 3;
    }

    private function renderNotes(Odontogram $odontogram): void
    {
        $this->sectionHeader('Notas generales del examen', 16);

        foreach (explode("\n", (string) $odontogram->notes) as $paragraph) {
            foreach ($this->wrap(trim($paragraph), self::CONTENT_WIDTH, $this->font, 9) as $line) {
                if ($this->ensureSpace(5)) {
                    $this->sectionHeader('Notas generales (continuación)', 10);
                }

                $this->write(self::MARGIN, $this->y + 3.4, $line, $this->font, 9, self::INK);
                $this->y += 4.6;
            }
        }

        $this->y += 3;
    }

    private function sectionHeader(string $title, float $minContentHeight): void
    {
        $this->ensureSpace(10 + $minContentHeight);
        $this->write(self::MARGIN, $this->y + 4, $title, $this->boldFont, 11, self::INK);
        $this->hr($this->y + 6.5, self::MARGIN, self::MARGIN + self::CONTENT_WIDTH, self::ACCENT, 0.5);
        $this->y += 10;
    }

    private function renderFooters(): void
    {
        $total = count($this->pages);
        $generated = now()->format('d/m/Y H:i');

        foreach ($this->pages as $i => $page) {
            $this->page = $page;
            $this->hr(197);
            $this->write(self::MARGIN, 201.5, "Odonto360 · {$this->patient->fullName()} · Generado el {$generated}", $this->font, 7.5, self::MUTED);
            $this->writeRight(self::MARGIN + self::CONTENT_WIDTH, 201.5, 'Página '.($i + 1)." de {$total}", $this->font, 7.5, self::MUTED);
        }
    }

    // ---- Primitivas de dibujo ------------------------------------------------------------

    private function write(float $x, float $y, string $text, Font $font, float $size, string $color): void
    {
        $this->page->setFont($font, $size);
        $this->page->setFillColor(Color::hex($color));
        $this->page->text($x, $y, $text);
    }

    private function writeRight(float $right, float $y, string $text, Font $font, float $size, string $color): void
    {
        $this->write($right - $this->page->stringWidth($text, $font, $size), $y, $text, $font, $size, $color);
    }

    private function hr(float $y, float $x1 = self::MARGIN, float $x2 = self::MARGIN + self::CONTENT_WIDTH, string $color = self::BORDER, float $width = 0.3): void
    {
        $this->page->setStrokeColor(Color::hex($color));
        $this->page->setLineWidth($width);
        $this->page->line($x1, $y, $x2, $y)->stroke();
    }

    private function fillRect(float $x, float $y, float $w, float $h, string $fill, ?string $stroke = null): void
    {
        $this->page->setFillColor(Color::hex($fill));

        if ($stroke === null) {
            $this->page->rect($x, $y, $w, $h)->fill();

            return;
        }

        $this->page->setStrokeColor(Color::hex($stroke));
        $this->page->setLineWidth(0.3);
        $this->page->rect($x, $y, $w, $h)->strokeAndFill();
    }

    /** Recorta con «…» el texto que no cabe en `$maxWidth`. */
    private function fit(string $text, float $maxWidth, Font $font, float $size): string
    {
        if ($this->page->stringWidth($text, $font, $size) <= $maxWidth) {
            return $text;
        }

        while ($text !== '' && $this->page->stringWidth($text.'…', $font, $size) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    /** @return list<string> */
    private function wrap(string $text, float $maxWidth, Font $font, float $size): array
    {
        $text = trim($text);

        if ($text === '') {
            return [''];
        }

        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            // Palabra más larga que la línea: se parte por caracteres.
            while ($this->page->stringWidth($word, $font, $size) > $maxWidth) {
                $cut = $word;

                while (mb_strlen($cut) > 1 && $this->page->stringWidth($cut, $font, $size) > $maxWidth) {
                    $cut = mb_substr($cut, 0, -1);
                }

                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                $lines[] = $cut;
                $word = mb_substr($word, mb_strlen($cut));
            }

            $candidate = $line === '' ? $word : "{$line} {$word}";

            if ($this->page->stringWidth($candidate, $font, $size) <= $maxWidth) {
                $line = $candidate;

                continue;
            }

            $lines[] = $line;
            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }
}
