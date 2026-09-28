<?php

declare(strict_types=1);

/**
 * Receipt renderer built on the bundled FPDF.
 *
 * The previous version hardcoded millimetre positions for A4 portrait
 * (Cell(90, ...) at x=10, totals at x=120, rules drawn to x=200). Two things
 * followed from that:
 *
 *   1. The item name cell spanned 10-100mm while QTY was centred at 79mm, so
 *      any long product name was drawn straight through the quantity.
 *   2. Nothing adapted to the page. A different paper size, landscape, or a
 *      long address simply overflowed.
 *
 * Everything here is derived from the live page box instead: one margin, one
 * content width, and column widths expressed as fractions of it. Change the
 * paper format or orientation and the whole layout rescales.
 */
/**
 * FPDF subclass that stamps the footer on every page.
 *
 * FPDF calls Footer() for the outgoing page from AddPage() and once more for
 * the last page from Close(), so overriding it is the only reliable way to get
 * a footer on all pages. Doing it by hand needs getNumPages(), which the
 * bundled build does not have.
 */
final class ReceiptPage extends FPDF
{
    public string $footerNote = '';
    public float $layoutScale = 1.0;
    public float $pageMargin = 14.0;
    public float $contentWidth = 0.0;

    /** @var array{0:int[],1:int[]} */
    public array $footerInk = [122, 132, 148];

    /** @var array{0:int[],1:int[]} */
    public array $footerLine = [226, 230, 236];

    function Header()
    {
        // The brand band is drawn by the caller, which needs to know where the
        // content starts, so there is deliberately no automatic header.
    }

    function Footer()
    {
        $y = $this->GetPageHeight() - $this->pageMargin - (16.0 * $this->layoutScale);

        $this->SetDrawColor(...$this->footerLine);
        $this->SetLineWidth(0.2);
        $this->Line($this->pageMargin, $y, $this->pageMargin + $this->contentWidth, $y);

        $this->SetTextColor(...$this->footerInk);
        $this->SetFont('Helvetica', 'I', round(7.5 * $this->layoutScale, 2));
        $this->SetXY($this->pageMargin, $y + (2.5 * $this->layoutScale));
        $this->Cell(
            $this->contentWidth * 0.7,
            4 * $this->layoutScale,
            $this->footerNote,
            0,
            0,
            'L'
        );

        $this->SetFont('Helvetica', '', round(7.5 * $this->layoutScale, 2));
        $this->SetXY($this->pageMargin, $y + (2.5 * $this->layoutScale));
        $this->Cell(
            $this->contentWidth,
            4 * $this->layoutScale,
            'Page ' . $this->PageNo() . ' of {nb}',
            0,
            0,
            'R'
        );
    }
}

final class ReceiptPdf
{
    /** Paper sizes in millimetres, portrait. */
    private const FORMATS = [
        'a3'     => [297.0, 420.0],
        'a4'     => [210.0, 297.0],
        'a5'     => [148.0, 210.0],
        'letter' => [215.9, 279.4],
        'legal'  => [215.9, 355.6],
    ];

    /** Column widths as a share of the content width. Sums to 1.0. */
    private const COLUMNS = [
        'item'   => 0.50,
        'qty'    => 0.09,
        'price'  => 0.19,
        'amount' => 0.22,
    ];

    private const INK        = [26, 32, 44];
    private const MUTED      = [122, 132, 148];
    private const LINE       = [226, 230, 236];
    private const ZEBRA      = [249, 250, 252];
    private const PANEL      = [247, 248, 250];
    private const BRAND      = [238, 77, 45];
    private const BRAND_SOFT = [255, 244, 241];

    private FPDF $pdf;
    private array $store;

    private float $margin = 14.0;
    private float $contentW = 0.0;
    private float $pageW = 0.0;
    private float $pageH = 0.0;
    private float $scale = 1.0;

    /** Vertical space held back at the foot of every page for the footer. */
    private float $footerH = 16.0;

    private string $logoPath;
    private bool $logoUsable = false;
    private string $disposition = 'D';
    private string $filename = 'receipt.pdf';
    private bool $built = false;

    /** Y position where drawMeta() finished, i.e. the top of the item table. */
    private float $metaEnd = 0.0;

    /** Height of one table header band, used when a page break repeats it. */
    private float $tableHeadHeight = 0.0;

    public function __construct(array $store, array $options = [])
    {
        $this->store = $store;

        $format = strtolower((string) ($options['format'] ?? 'a4'));
        if (!isset(self::FORMATS[$format])) {
            $format = 'a4';
        }

        [$w, $h] = self::FORMATS[$format];

        $landscape = strtoupper((string) ($options['orientation'] ?? 'P')) === 'L';
        if ($landscape) {
            [$w, $h] = [$h, $w];
        }

        $this->pageW = $w;
        $this->pageH = $h;

        // A4 portrait is the reference layout; everything else scales from it.
        $this->margin  = 14.0;
        $this->contentW = $this->pageW - ($this->margin * 2);
        $this->scale   = min(1.0, $this->contentW / 182.0);

        $this->pdf = new ReceiptPage($landscape ? 'L' : 'P', 'mm', [$w, $h]);
        $this->pdf->SetMargins($this->margin, $this->margin, $this->margin);
        $this->pdf->SetAutoPageBreak(true, $this->margin + $this->footerH);
        $this->pdf->AliasNbPages();
        $this->pdf->SetTitle('Receipt');
        $this->pdf->SetAuthor((string) ($store['legal'] ?? 'Receipt'));
        $this->pdf->SetCreator('Jayann Store');
        $this->pdf->SetDisplayMode('fullpage');

        $this->pdf->layoutScale  = $this->scale;
        $this->pdf->pageMargin   = $this->margin;
        $this->pdf->contentWidth = $this->contentW;
        $this->pdf->footerInk    = self::MUTED;
        $this->pdf->footerLine   = self::LINE;
        $this->pdf->footerNote   = $this->txt('Thank you for shopping with ' . (string) ($store['legal'] ?? 'us') . '.');

        $this->logoPath   = rtrim((string) ($options['logo'] ?? ''), '/\\') . '/assets/img/storenijayann.png';
        $this->logoUsable = is_file($this->logoPath) && is_readable($this->logoPath);
    }

    /**
     * Build the document.
     *
     * $view carries display-ready values, so this class never has to know about
     * the orders table or how a status maps to a label:
     *
     *   date_label, status_label, status_tone, customer_name, customer_phone,
     *   customer_email, method_label, gateway_ref, address
     *
     * $totals expects already-formatted strings: subtotal, shipping, total and
     * an optional saved_note.
     *
     * Returns the filename that was offered.
     */
    public function render(array $view, array $lines, array $totals, string $reference): string
    {
        $this->build($view, $lines, $totals, $reference);

        return $this->output();
    }

    /**
     * Lay the document out without emitting it.
     *
     * Separated from output() so a test can render, measure and assert against
     * the finished PDF instead of guessing at the result. Calling build() twice
     * on the same instance is a no-op rather than a doubled document.
     */
    public function build(array $view, array $lines, array $totals, string $reference): void
    {
        if ($this->built) {
            return;
        }
        $this->built = true;

        $pdf = $this->pdf;

        $pdf->SetSubject('Order ' . $this->txt($reference));
        $pdf->AddPage();

        $y = $this->drawHeader($view, $reference);
        $y = $this->drawMeta($view, $y);
        $this->metaEnd = $y;
        $this->drawTable($lines, $y, $totals);

        $this->filename = 'receipt_' . preg_replace('/[^A-Za-z0-9_-]/', '', $reference) . '.pdf';
    }

    /**
     * Emit the built document according to the configured disposition and
     * return the filename that was used.
     */
    public function output(): string
    {
        $this->pdf->Output($this->disposition, $this->filename);

        return $this->filename;
    }

    /** The raw PDF bytes, for callers that want to buffer or cache them. */
    public function bytes(): string
    {
        return $this->pdf->Output('S', $this->filename);
    }

    /** 'D' downloads, 'I' opens in the browser, 'S' returns the bytes. */
    public function setDisposition(string $mode): void
    {
        $this->disposition = in_array($mode, ['D', 'I', 'S', 'F'], true) ? $mode : 'D';
    }

    /* Read-only geometry, so a test can assert the layout stays inside the
       page box without having to parse the generated PDF. */
    public function pageWidth(): float
    {
        return $this->pageW;
    }

    public function pageHeight(): float
    {
        return $this->pageH;
    }

    public function contentWidth(): float
    {
        return $this->contentW;
    }

    public function marginValue(): float
    {
        return $this->margin;
    }

    public function scale(): float
    {
        return $this->scale;
    }

    /**
     * The underlying FPDF, for a test that needs FPDF's own text metrics to
     * measure what was drawn. Not needed to produce a receipt.
     */
    public function fpdfForMetrics(): FPDF
    {
        return $this->pdf;
    }

    /* ------------------------------------------------------------------ head */

    private function drawHeader(array $order, string $reference): float
    {
        $pdf = $this->pdf;

        $bandH = $this->u(30);
        $top   = $this->margin;

        $pdf->SetFillColor(...self::BRAND);
        $pdf->Rect(0, $top, $this->pageW, $bandH, 'F');

        $pad = $this->u(6);
        $textX = $this->margin + $pad;
        $logoH = $this->u(15);
        $textY = $top + $this->u(7.5);

        // Logo, scaled to the band. FPDF parses non-interlaced PNG without GD,
        // but a missing or exotic file must not take the whole receipt down.
        if ($this->logoUsable) {
            try {
                $ratio = 1.0;
                $size = @getimagesize($this->logoPath);
                if ($size && $size[1] > 0) {
                    $ratio = $size[0] / $size[1];
                }
                $logoW = $logoH * $ratio;
                $this->logoUsable = $pdf->Image(
                    $this->logoPath,
                    $textX,
                    $top + ($bandH - $logoH) / 2,
                    $logoW,
                    $logoH,
                    'PNG'
                ) !== false;
                if ($this->logoUsable) {
                    $textX += $logoW + $this->u(4);
                }
            } catch (Throwable) {
                $this->logoUsable = false;
            }
        }

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', $this->fs(15));
        $pdf->SetXY($textX, $textY);
        $pdf->Cell($this->contentW - ($textX - $this->margin), $this->u(7), $this->txt((string) ($this->store['name'] ?? '')), 0, 2, 'L');

        $pdf->SetFont('Helvetica', '', $this->fs(8.5));
        $pdf->SetXY($textX, $textY + $this->u(7));
        $tagline = trim((string) ($this->store['tagline'] ?? ''));
        $contact = trim((string) ($this->store['phone'] ?? ''));
        if ($contact !== '') {
            $tagline = $tagline === '' ? $contact : $tagline . '   ·   ' . $contact;
        }
        $pdf->Cell($this->contentW - ($textX - $this->margin), $this->u(5), $this->txt($tagline), 0, 0, 'L');

        // Reference, right-aligned inside the band.
        $pdf->SetFont('Helvetica', 'B', $this->fs(8));
        $pdf->SetXY($this->margin, $top + $this->u(5.5));
        $pdf->Cell($this->contentW, $this->u(4.5), $this->txt('ORDER RECEIPT'), 0, 0, 'R');

        $pdf->SetFont('Helvetica', '', $this->fs(8));
        $pdf->SetXY($this->margin, $top + $this->u(10.5));
        $pdf->Cell($this->contentW, $this->u(4.5), $this->txt('Ref ' . $reference), 0, 0, 'R');

        return $top + $bandH + $this->u(6);
    }

    /* ------------------------------------------------------------------ meta */

    private function drawMeta(array $order, float $y): float
    {
        $pdf = $this->pdf;

        $statusLabel = (string) ($order['status_label'] ?? '');
        $statusTone  = (string) ($order['status_tone'] ?? 'neutral');

        $rowH = $this->u(6.5);

        // Status chip on the right, reference date on the left.
        // Square corners throughout: the bundled FPDF predates RoundedRect(),
        // and the flat look suits the design without needing GD for curves.
        if ($statusLabel !== '') {
            [$bg, $fg] = $this->statusColours($statusTone);
            $pdf->SetFont('Helvetica', 'B', $this->fs(7.5));
            $chipW = $this->u(4) + $pdf->GetStringWidth($this->txt($statusLabel)) + $this->u(4);
            $chipH = $this->u(5.5);

            $pdf->SetFillColor(...$bg);
            $pdf->Rect($this->margin + $this->contentW - $chipW, $y, $chipW, $chipH, 'F');

            $pdf->SetTextColor(...$fg);
            $pdf->SetXY($this->margin + $this->contentW - $chipW, $y + $this->u(1.1));
            $pdf->Cell($chipW, $this->u(3.5), $this->txt($statusLabel), 0, 0, 'C');
        }

        $pdf->SetTextColor(...self::INK);
        $pdf->SetFont('Helvetica', 'B', $this->fs(9.5));
        $pdf->SetXY($this->margin, $y + $this->u(0.4));
        $pdf->Cell(
            $this->contentW * 0.6,
            $rowH,
            $this->txt('Placed ' . (string) ($order['date_label'] ?? '')),
            0,
            0,
            'L'
        );

        $y += $rowH + $this->u(3);

        // Two panels side by side; a single full-width panel when the page is
        // too narrow for two to stay legible.
        $twoUp = $this->contentW >= $this->u(120);
        $gap   = $this->u(5);
        $panelW = $twoUp ? ($this->contentW - $gap) / 2 : $this->contentW;

        $left = [
            'Billed to' => (string) ($order['customer_name'] ?? ''),
            'Contact'   => trim((string) ($order['customer_phone'] ?? '') . '  ' . (string) ($order['customer_email'] ?? '')),
        ];
        $right = [];
        if (($method = trim((string) ($order['method_label'] ?? ''))) !== '') {
            $right['Paid by'] = $method;
        }
        if (($ref = trim((string) ($order['gateway_ref'] ?? ''))) !== '') {
            $right['Gateway ref'] = $ref;
        }
        // No note/remark panel: the orders table has no such column, so the
        // key is always absent and a panel for it would be dead weight.

        $panelH = $this->panel($left, $this->margin, $y, $panelW);
        $panelHR = $right === [] ? 0.0 : $this->panel($right, $this->margin + $panelW + $gap, $y, $panelW);

        $y += max($panelH, $panelHR) + $this->u(4);

        // Delivery address gets its own full-width panel: it is the field most
        // likely to run long, and wrapping it across the page beats a narrow
        // column that shreds it into one word per line.
        $address = trim((string) ($order['address'] ?? ''));
        if ($address !== '') {
            // Advance past the panel. Assigning the returned height to $y here
            // would throw away the running position and draw everything after
            // it back at the top of the page, inside the brand band.
            $y += $this->panel(['Delivery address' => $address], $this->margin, $y, $this->contentW) + $this->u(4);
        }

        return $y;
    }

    /**
     * A labelled key/value block. Returns the height consumed.
     */
    private function panel(array $rows, float $x, float $y, float $w): float
    {
        if ($rows === []) {
            return 0.0;
        }

        $pdf = $this->pdf;
        $pad = $this->u(3.5);
        $labelH = $this->u(3.6);
        $valueH = $this->u(4.4);

        // Measure first so the background can be filled to the exact height.
        $height = $pad;
        $measured = [];
        foreach ($rows as $label => $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $lines = $this->wrap($this->txt($value), $w - ($pad * 2));
            $measured[$label] = $lines;
            $height += $labelH + (count($lines) * $valueH) + $this->u(1.2);
        }

        if ($measured === []) {
            return 0.0;
        }
        $height += $pad - $this->u(1.2);

        $pdf->SetFillColor(...self::PANEL);
        $pdf->Rect($x, $y, $w, $height, 'F');

        $cursor = $y + $pad;
        foreach ($measured as $label => $lines) {
            $pdf->SetFont('Helvetica', '', $this->fs(6.8));
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x + $pad, $cursor);
            $pdf->Cell($w - ($pad * 2), $labelH, $this->txt(strtoupper((string) $label)), 0, 0, 'L');

            $cursor += $labelH;

            $pdf->SetFont('Helvetica', '', $this->fs(8.6));
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x + $pad, $cursor);
            $pdf->MultiCell($w - ($pad * 2), $valueH, implode("\n", $lines), 0, 'L');
            $cursor += (count($lines) * $valueH) + $this->u(1.2);
        }

        return $height;
    }

    /* ----------------------------------------------------------------- table */

    private function drawTable(array $lines, float $y, array $totals): float
    {
        $x = $this->margin;
        $w = $this->contentW;

        $cols = [];
        $cursor = 0.0;
        foreach (self::COLUMNS as $key => $share) {
            $cols[$key] = $x + ($w * $cursor);
            $cursor += $share;
        }

        $headH = $this->u(7);
        $this->tableHeadHeight = $headH + $this->u(2.5);

        $pdf = $this->pdf;
        $pdf->SetFont('Helvetica', 'B', $this->fs(7.6));
        $pdf->SetTextColor(...self::MUTED);

        // Space held back at the foot of every page so the totals block is never
        // orphaned from the last row. Fixed, not proportional to the row count:
        // scaling it by the number of lines made every row look like it would
        // overflow and produced one page per line.
        $reserve = $this->u(26);

        $y = $this->tableHead($cols, $headH, $y);

        $pdf->SetFont('Helvetica', '', $this->fs(9));
        $rowGap = $this->u(1.2);
        $index  = 0;

        foreach ($lines as $line) {
            $qty    = (int) ($line['quantity'] ?? 0);
            $unit   = (float) ($line['unit'] ?? 0);
            $amount = $unit * $qty;
            $name   = (string) ($line['name'] ?? '');

            $nameLines = $this->wrap($this->txt($name), $w * self::COLUMNS['item'] - $this->u(3));
            $rowH = max($this->u(5.2), (count($nameLines) * $this->u(4.4)) + $this->u(2));

            // Break before drawing rather than letting FPDF clip at the margin.
            if ($this->nearBottom($y, $rowH + $reserve)) {
                $pdf->AddPage();
                $y = $this->tableHead($cols, $headH, $this->margin);
                $pdf->SetFont('Helvetica', '', $this->fs(9));
            }

            if ($index % 2 === 1) {
                $pdf->SetFillColor(...self::ZEBRA);
                $pdf->Rect($x, $y, $w, $rowH, 'F');
            }

            $pdf->SetTextColor(...self::INK);
            $pdf->SetFont('Helvetica', '', $this->fs(9));
            $pdf->SetXY($cols['item'] + $this->u(1.5), $y + $this->u(1));
            $pdf->MultiCell(
                $w * self::COLUMNS['item'] - $this->u(3),
                $this->u(4.4),
                implode("\n", $nameLines),
                0,
                'L'
            );

            $pdf->SetXY($cols['qty'], $y + $this->u(1));
            $pdf->Cell($w * self::COLUMNS['qty'], $this->u(4), (string) $qty, 0, 0, 'C');

            $pdf->SetXY($cols['price'], $y + $this->u(1));
            $pdf->Cell(
                $w * self::COLUMNS['price'] - $this->u(2),
                $this->u(4),
                $unit > 0 ? $this->money($unit) : '-',
                0,
                0,
                'R'
            );

            $pdf->SetFont('Helvetica', 'B', $this->fs(9));
            $pdf->SetXY($cols['amount'], $y + $this->u(1));
            $pdf->Cell(
                $w * self::COLUMNS['amount'] - $this->u(1.5),
                $this->u(4),
                $unit > 0 ? $this->money($amount) : '-',
                0,
                0,
                'R'
            );

            $y += $rowH + $rowGap;
            $index++;
        }

        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($x, $y, $x + $w, $y);
        $y += $this->u(4);

        return $this->drawTotals($totals, $y);
    }

    private function tableHead(array $cols, float $headH, float $y): float
    {
        $pdf = $this->pdf;
        $w = $this->contentW;

        $pdf->SetFont('Helvetica', 'B', $this->fs(7.6));
        $pdf->SetTextColor(...self::MUTED);

        $pdf->SetFillColor(...self::PANEL);
        $pdf->Rect($this->margin, $y, $w, $headH, 'F');

        $pad = $this->u(2);
        $midY = $y + ($headH / 2) - $this->u(1.9);

        $pdf->SetXY($cols['item'] + $pad, $midY);
        $pdf->Cell($w * self::COLUMNS['item'] - $pad, $this->u(3.8), 'ITEM', 0, 0, 'L');

        $pdf->SetXY($cols['qty'], $midY);
        $pdf->Cell($w * self::COLUMNS['qty'], $this->u(3.8), 'QTY', 0, 0, 'C');

        $pdf->SetXY($cols['price'], $midY);
        $pdf->Cell($w * self::COLUMNS['price'] - $pad, $this->u(3.8), 'PRICE', 0, 0, 'R');

        $pdf->SetXY($cols['amount'], $midY);
        $pdf->Cell($w * self::COLUMNS['amount'] - $pad, $this->u(3.8), 'AMOUNT', 0, 0, 'R');

        return $y + $headH + $this->u(2.5);
    }

    /**
     * Totals, right-aligned. The total row is filled with a brand tint so the
     * figure the customer cares about is the first thing the eye lands on.
     */
    private function drawTotals(array $totals, float $y): float
    {
        $pdf = $this->pdf;

        $blockW = min($this->contentW * 0.52, $this->u(78));
        $blockX = $this->margin + $this->contentW - $blockW;
        $rowH   = $this->u(5.4);
        $labelW = $blockW * 0.52;

        $pdf->SetFont('Helvetica', '', $this->fs(8.6));

        foreach (['subtotal' => 'Subtotal', 'shipping' => 'Delivery'] as $key => $label) {
            if (!array_key_exists($key, $totals)) {
                continue;
            }
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($blockX, $y);
            $pdf->Cell($labelW, $rowH, $this->txt((string) $label), 0, 0, 'L');

            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($blockX + $labelW, $y);
            $pdf->Cell($blockW - $labelW, $rowH, $this->txt((string) $totals[$key]), 0, 0, 'R');

            $y += $rowH;
        }

        // Highlighted total.
        $totalH = $this->u(8);
        $pdf->SetFillColor(...self::BRAND_SOFT);
        $pdf->Rect($blockX, $y, $blockW, $totalH, 'F');

        $pdf->SetTextColor(...self::BRAND);
        $pdf->SetFont('Helvetica', 'B', $this->fs(9.5));
        $pdf->SetXY($blockX + $this->u(2.5), $y + $this->u(2.2));
        $pdf->Cell($labelW - $this->u(2.5), $this->u(4.5), 'TOTAL', 0, 0, 'L');

        $pdf->SetFont('Helvetica', 'B', $this->fs(12));
        $pdf->SetXY($blockX + $labelW, $y + $this->u(1.6));
        $pdf->Cell($blockW - $labelW - $this->u(2.5), $this->u(6), $this->txt((string) ($totals['total'] ?? '')), 0, 0, 'R');

        $y += $totalH + $this->u(5);

        // Per-item savings only appears when there is something to say.
        if (($totals['saved_note'] ?? '') !== '') {
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetFont('Helvetica', 'I', $this->fs(7.8));
            $pdf->SetXY($this->margin, $y);
            $pdf->Cell($this->contentW, $this->u(4), $this->txt((string) $totals['saved_note']), 0, 0, 'R');
            $y += $this->u(4);
        }

        return $y;
    }

    /* ----------------------------------------------------------------- utils */

    /**
     * Would drawing $needed millimetres from $y reach the footer band?
     *
     * Takes the absolute position rather than reading FPDF's cursor, because
     * every element here is placed with SetXY and GetY() at this point holds
     * whatever the previous cell happened to leave behind.
     */
    private function nearBottom(float $y, float $needed): bool
    {
        $limit = $this->pageH - $this->margin - $this->footerH;
        return ($y + $needed) > $limit;
    }

    /**
     * Greedy word wrap using FPDF's own metrics, so the measured width and the
     * drawn width can never disagree. Words longer than the column (a SKU, a
     * URL) are hard-split instead of overflowing.
     */
    private function wrap(string $text, float $width): array
    {
        $text = trim($text);
        if ($text === '' || $width <= 0) {
            return [''];
        }

        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            if ($words === []) {
                $lines[] = '';
                continue;
            }

            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->pdf->GetStringWidth($candidate) <= $width) {
                    $line = $candidate;
                    continue;
                }

                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                while ($this->pdf->GetStringWidth($word) > $width && $word !== '') {
                    $cut = strlen($word) - 1;
                    while ($cut > 1 && $this->pdf->GetStringWidth(substr($word, 0, $cut)) > $width) {
                        $cut--;
                    }
                    $lines[] = substr($word, 0, $cut);
                    $word = substr($word, $cut);
                }
                $line = $word;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Reduce a string to characters FPDF's core fonts can actually draw.
     *
     * FPDF decides how to encode a string by testing whether every byte is
     * below 128 (_isascii). Anything else is assumed to be UTF-8 and handed to
     * iconv as UTF-16BE. So the contract is stricter than "Windows-1252 is
     * enough": the result must be pure ASCII. A single stray high byte is fed
     * to iconv as malformed UTF-8 and comes back as "?", which is how the
     * middle-dot separator in the header and the peso sign in every amount
     * both turned into question marks.
     *
     * Known typographic characters are mapped to sensible ASCII equivalents
     * and anything still non-ASCII is transliterated, then stripped as a last
     * resort so a character can never reach the renderer.
     */
    private function txt(string $s): string
    {
        static $map = [
            // Currency and symbols
            "\u{20B1}" => 'PHP ',   // the peso sign has no core-font glyph
            "\u{00A2}" => 'c',      // cent sign
            "\u{20AC}" => 'EUR',
            "\u{00D7}" => 'x',      // multiplication sign
            "\u{00F7}" => '/',      // division sign
            "\u{00B0}" => ' deg',   // degree
            "\u{2122}" => '(TM)',
            "\u{00AE}" => '(R)',

            // Quotes and dashes
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201A}" => ',',
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{201E}" => '"',
            "\u{2032}" => "'",
            "\u{2033}" => '"',
            "\u{2010}" => '-',
            "\u{2011}" => '-',
            "\u{2012}" => '-',
            "\u{2013}" => '-',      // en dash
            "\u{2014}" => '-',      // em dash
            "\u{2015}" => '--',
            "\u{2212}" => '-',      // minus sign

            // Spacing and punctuation
            "\u{00A0}" => ' ',      // non-breaking space
            "\u{2000}" => ' ',
            "\u{2001}" => ' ',
            "\u{2002}" => ' ',
            "\u{2003}" => ' ',
            "\u{2007}" => ' ',
            "\u{2008}" => ' ',
            "\u{2009}" => ' ',
            "\u{200A}" => ' ',
            "\u{202F}" => ' ',
            "\u{205F}" => ' ',
            "\u{3000}" => ' ',
            "\u{2002}" => ' ',
            "\u{200B}" => '',       // zero-width space
            "\u{200C}" => '',
            "\u{200D}" => '',
            "\u{FEFF}" => '',       // BOM
            "\u{2022}" => '-',      // bullet
            "\u{2023}" => '-',      // triangle bullet
            "\u{00B7}" => '-',      // middle dot
            "\u{2026}" => '...',    // ellipsis
            "\u{2027}" => '-',
            "\u{2039}" => '<',
            "\u{203A}" => '>',

            // Accented Latin letters. A customer name or address may well be
            // "Jos\u{00E9}" or "Cafe\u{0301}", and neither the pound nor euro
            // sign nor any of the symbols below survive //TRANSLIT reliably.
            "ÀÁÂÃÄÅ" => 'A',
            "àáâãäå" => 'a',
            "Æ" => 'AE',
            "æ" => 'ae',
            "Ç" => 'C',
            "ç" => 'c',
            "ÈÉÊË" => 'E',
            "èéêë" => 'e',
            "ÌÍÎÏ" => 'I',
            "ìíîï" => 'i',
            "Ð" => 'D',
            "ð" => 'd',
            "Ñ" => 'N',
            "ñ" => 'n',
            "ÒÓÔÕÖØ" => 'O',
            "òóôõöø" => 'o',
            "ÙÚÛÜ" => 'U',
            "ùúûü" => 'u',
            "Ý" => 'Y',
            "ýÿ" => 'y',
            "Þ" => 'Th',
            "þ" => 'th',
            "Š" => 'S',
            "š" => 's',
            "Ž" => 'Z',
            "ž" => 'z',
            "Ł" => 'L',
            "ł" => 'l',
            "Đ" => 'D',
            "đ" => 'd',
            "Ħ" => 'H',
            "ħ" => 'h',
            "Ŧ" => 'T',
            "ŧ" => 't',
            "Ŋ" => 'N',
            "ŋ" => 'n',
            "Œ" => 'OE',
            "œ" => 'oe',
            "ß" => 'ss',
            "Ĳ" => 'IJ',
            "ĳ" => 'ij',
            "ŉ" => 'n',
            "ſ" => 's',

            // Combining marks are dropped after the base letter is folded, so
            // a decomposed "Cafe" + U+0301 does not become "Cafe?".
            "\u{0300}" => '', "\u{0301}" => '', "\u{0302}" => '', "\u{0303}" => '',
            "\u{0304}" => '', "\u{0306}" => '', "\u{0307}" => '', "\u{0308}" => '',
            "\u{0309}" => '', "\u{030A}" => '', "\u{030B}" => '', "\u{030C}" => '',
            "\u{030F}" => '', "\u{0311}" => '', "\u{0327}" => '', "\u{0328}" => '',

            // Symbols a product name or promo line may contain
            "€" => 'EUR', "£" => 'GBP', "¥" => 'JPY',
            "§" => 'S', "¶" => 'P', "©" => '(C)', "±" => '+/-',
            "½" => ' 1/2 ', "¼" => ' 1/4 ', "¾" => ' 3/4 ',
            "≈" => '~', "≠" => '!=', "≤" => '<=', "≥" => '>=',
            "→" => '->', "←" => '<-', "↑" => '^', "↓" => 'v',
            "✔" => 'v', "✓" => 'v', "★" => '*', "☆" => '*',
            "♥" => '', "☺" => '', "☃" => '', "❄" => '',
            "\u{FFFD}" => '',

            // Fullwidth punctuation, which is how a lot of Asian input lands
            "\u{FF0C}" => ',', "\u{FF1A}" => ':', "\u{FF1B}" => ';',
            "\u{FF08}" => '(', "\u{FF09}" => ')', "\u{FF0E}" => '.',
        ];

        $s = strtr($s, $map);

        // Transliterate whatever is still left. iconv is tried first because
        // //TRANSLIT produces readable ASCII for many characters
        // mb_convert_encoding would flatten to "?", but it can also fail
        // outright, so both are attempted and the result is verified.
        if (preg_match('/[\x80-\xFF]/', $s)) {
            foreach ([['iconv', 'UTF-8', 'ASCII//TRANSLIT'], ['mb', 'UTF-8', 'ASCII']] as [$how, $from, $to]) {
                $out = $how === 'iconv'
                    ? (@function_exists('iconv') ? @iconv($from, $to, $s) : false)
                    : (@function_exists('mb_convert_encoding') ? @mb_convert_encoding($s, $to, $from) : false);

                if (is_string($out) && $out !== '') {
                    $s = $out;
                    if (!preg_match('/[\x80-\xFF]/', $s)) {
                        break;
                    }
                }
            }
        }

        // Last resort: nothing above 127 may survive, whatever happened above.
        // "?" is used rather than deletion so a lost character is visible in
        // the PDF instead of silently welding two words together.
        $s = preg_replace('/[\x80-\xFF]/', '?', $s) ?? $s;

        return $s;
    }

    private function money(float $amount): string
    {
        return $this->txt((string) ($this->store['currency'] ?? '')) . number_format($amount, 2);
    }

    private function fs(float $size): float
    {
        return round($size * $this->scale, 2);
    }

    private function u(float $size): float
    {
        return $size * $this->scale;
    }

    /**
     * How many rows still fit before the foot of the page, given the space
     * reserved for the totals block.
     *
     * Exposed so a test can assert the table starts where it should rather than
     * inferring it from the rendered text.
     */
    public function rowsPerPageHint(float $rowHeight): float
    {
        $firstPage = $this->metaEnd - $this->tableHeadHeight;
        $laterPage = $this->margin + $this->tableHeadHeight;

        $limit = $this->pageH - $this->margin - $this->footerH;
        $reserve = $this->u(26);
        $gap = $this->u(1.2);

        $a = ($limit - $reserve - $firstPage) / ($rowHeight + $gap);
        $b = ($limit - $reserve - $laterPage) / ($rowHeight + $gap);

        return max(0.0, floor(min($a, $b)));
    }

    /** @return array{0:int[],1:int[]} */
    private function statusColours(string $tone): array
    {
        return match ($tone) {
            'good'    => [[226, 244, 232], [22, 101, 52]],
            'warn'    => [[254, 243, 199], [146, 64, 14]],
            'bad'     => [[253, 226, 226], [153, 27, 27]],
            'info'    => [[226, 240, 254], [30, 64, 175]],
            'brand'   => [self::BRAND_SOFT, self::BRAND],
            default   => [self::PANEL, self::MUTED],
        };
    }
}
