<?php

declare(strict_types=1);

/**
 * Structural audit of a generated receipt PDF.
 *
 * Renders nothing and trusts nothing: the PDF's content streams are
 * decompressed and every drawing operator is checked against the page box.
 *
 * Note on units: FPDF works in millimetres but writes user-space coordinates in
 * points (k = 72/25.4). Everything below is converted back to millimetres
 * before being compared with the page box, otherwise every rectangle looks
 * three times too large.
 *
 * Usage:
 *   php tools/receipt_audit.php <pdf> [format] [orientation]
 */

const PT_PER_MM = 72.0 / 25.4;

final class PdfContent
{
    /**
     * Decompress the page content streams, in page order.
     *
     * FPDF appends streams in the order pages are finalised, so the drawing
     * streams come out in page order once the info dictionary is excluded.
     *
     * @return list<string>
     */
    public static function pageStreams(string $pdf): array
    {
        $out = [];
        $offset = 0;

        while (($start = strpos($pdf, 'stream', $offset)) !== false) {
            $dictStart = strrpos(substr($pdf, max(0, $start - 500), min(500, $start)), '<<');
            $dict = $dictStart === false ? '' : substr($pdf, max(0, $start - 500) + $dictStart, min(500, $start) - $dictStart);

            $dataStart = $start + 6;
            if (($pdf[$dataStart] ?? '') === "\r") {
                $dataStart++;
            }
            if (($pdf[$dataStart] ?? '') === "\n") {
                $dataStart++;
            }

            $end = strpos($pdf, 'endstream', $dataStart);
            if ($end === false) {
                break;
            }

            $raw = substr($pdf, $dataStart, $end - $dataStart);
            $offset = $end + 9;

            if (!str_contains($dict, 'FlateDecode')) {
                continue;
            }

            $decoded = @gzuncompress($raw);
            if ($decoded === false) {
                $decoded = @gzinflate($raw);
            }
            if ($decoded === false || $decoded === '') {
                continue;
            }

            // A drawing stream contains path or text operators.
            if (str_contains($decoded, ' re') || str_contains($decoded, 'BT')) {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    /**
     * Split a content stream into tokens, keeping parenthesised strings whole
     * and honouring backslash escapes inside them.
     *
     * @return list<string>
     */
    private static function tokenise(string $stream): array
    {
        $tokens = [];
        $len = strlen($stream);
        $i = 0;
        $buf = '';

        while ($i < $len) {
            $c = $stream[$i];

            if ($c === ' ' || $c === "\n" || $c === "\r" || $c === "\t") {
                if ($buf !== '') {
                    $tokens[] = $buf;
                    $buf = '';
                }
                $i++;
                continue;
            }

            if ($c === '(') {
                $depth = 1;
                $str = '(';
                $i++;
                while ($i < $len && $depth > 0) {
                    $ch = $stream[$i];
                    if ($ch === '\\') {
                        $str .= $ch . ($stream[$i + 1] ?? '');
                        $i += 2;
                        continue;
                    }
                    if ($ch === '(') {
                        $depth++;
                    } elseif ($ch === ')') {
                        $depth--;
                        if ($depth === 0) {
                            // Keep the closing paren so the token is still a
                            // well-formed "(...)" literal; the caller strips it
                            // with substr($t, 1, -1).
                            $str .= ')';
                            $i++;
                            break;
                        }
                    }
                    $str .= $ch;
                    $i++;
                }
                if ($buf !== '') {
                    $tokens[] = $buf;
                    $buf = '';
                }
                $tokens[] = $str;
                continue;
            }

            $buf .= $c;
            $i++;
        }

        if ($buf !== '') {
            $tokens[] = $buf;
        }

        return $tokens;
    }

    /**
     * Extract drawn rectangles and positioned text, converted to millimetres
     * measured from the TOP-LEFT of the page.
     *
     * PDF user space puts the origin at the bottom-left, and FPDF writes
     * rectangle heights as negative values (it draws downward on screen but
     * upward in user space). Both are normalised here so the page-box checks
     * downstream do not have to think about it.
     *
     * @return array{rects: list<array{0:float,1:float,2:float,3:float}>, text: list<array{x:float,y:float,h:float,s:string}>}
     */
    public static function analyse(string $stream, float $pageH): array
    {
        $rects = [];
        $text = [];

        // Tokenise by hand rather than splitting on whitespace: a text string
        // like "(Order Receipt)" contains spaces, and a naive split would tear
        // it into "(" and "Receipt)" so the string never gets recognised.
        $tokens = self::tokenise($stream);

        $operand = [];
        $inText = false;
        $tx = 0.0;
        $ty = 0.0;
        $size = 10.0;

        foreach ($tokens as $t) {
            if ($t === '') {
                continue;
            }

            if ($t === 'BT') {
                $inText = true;
                $tx = $ty = 0.0;
                $operand = [];
                continue;
            }
            if ($t === 'ET') {
                $inText = false;
                $operand = [];
                continue;
            }

            // FPDF emits the font selection in its own BT/ET block
            // ("BT /F1 15.00 Tf ET") and the text in a following block, so the
            // size has to survive the block boundary rather than resetting.
            if ($t === 'Tf' && $operand !== []) {
                $size = (float) end($operand);
                $operand = [];
                continue;
            }

            if (($t === 'Tm' || $t === 'Td' || $t === 'TD') && count($operand) >= 2) {
                $vals = array_map('floatval', $operand);
                $tx = $vals[count($vals) - 2];
                $ty = $vals[count($vals) - 1];
                $operand = [];
                continue;
            }

            if (($t === 'Tj' || $t === 'TJ' || $t === "'" || $t === '"') && $inText) {
                $s = '';
                foreach ($operand as $o) {
                    if (is_string($o) && str_starts_with($o, '(')) {
                        $s .= substr($o, 1, -1);
                    }
                }
                if ($s !== '') {
                    // $ty is a bottom-up baseline in PDF user space; flip it to
                    // a top-down offset so it can be compared with the page box.
                    $text[] = [
                        'x' => $tx / PT_PER_MM,
                        'y' => $pageH - ($ty / PT_PER_MM),
                        'h' => $size / PT_PER_MM,
                        's' => $s,
                    ];
                }
                $operand = [];
                continue;
            }

            if ($t === 're' && count($operand) >= 4) {
                $v = array_map('floatval', $operand);
                $rx = $v[count($v) - 4] / PT_PER_MM;
                $ry = $v[count($v) - 3] / PT_PER_MM;
                $rw = $v[count($v) - 2] / PT_PER_MM;
                $rh = $v[count($v) - 1] / PT_PER_MM;

                // FPDF emits a negative height, so the rectangle runs upward
                // from its y. Normalise to a top-left origin with a positive
                // height, which is how the rest of this tool reads geometry.
                $rects[] = [
                    $rx,
                    $pageH - ($ry + $rh),
                    $rw,
                    abs($rh),
                ];
                $operand = [];
                continue;
            }

            if (is_numeric($t)) {
                $operand[] = $t;
            } elseif (str_starts_with($t, '(')) {
                // Hold on to the literal; the operator that consumes it (Tj,
                // TJ) comes after it and clears the list.
                $operand[] = $t;
            } else {
                $operand = [];
            }
        }

        return ['rects' => $rects, 'text' => $text];
    }
}

/* ------------------------------------------------------------------ inputs */

$root = dirname(__DIR__);
require_once $root . '/libs/fpdf.php';
require_once $root . '/app/receipt_pdf.php';

$pdfPath = $argv[1] ?? '';
if ($pdfPath === '' || !is_file($pdfPath)) {
    fwrite(STDERR, "usage: php tools/receipt_audit.php <pdf> [format] [orientation]\n");
    exit(2);
}

$format = $argv[2] ?? 'a4';
$orientation = $argv[3] ?? 'P';

$store = ['legal' => 'Jayann Store', 'currency' => "\u{20B1}"];
$probe = new ReceiptPdf($store, [
    'format'      => $format,
    'orientation' => $orientation,
    'logo'        => $root,
]);

$pageW   = $probe->pageWidth();
$pageH   = $probe->pageHeight();
$margin  = $probe->marginValue();
$contentW = $probe->contentWidth();

$pdf = file_get_contents($pdfPath);
$streams = PdfContent::pageStreams($pdf);
$pageCount = count($streams);

/* ------------------------------------------------------------- page bounds */

$problems = [];
$TOL = 0.5; // FPDF rounds to 2dp; half a millimetre of slop is plenty.

$perPage = [];
foreach ($streams as $i => $stream) {
    $page = $i + 1;
    $a = PdfContent::analyse($stream, $pageH);
    $perPage[] = $a;

    foreach ($a['rects'] as [$rx, $ry, $rw, $rh]) {
        if ($rx < -$TOL || $ry < -$TOL) {
            $problems[] = sprintf('p%d: rect starts off-page at (%.2f, %.2f)', $page, $rx, $ry);
        }
        if ($rx + $rw > $pageW + $TOL) {
            $problems[] = sprintf('p%d: rect overflows right edge (%.2f > %.2f mm)', $page, $rx + $rw, $pageW);
        }
        if ($ry + $rh > $pageH + $TOL) {
            $problems[] = sprintf('p%d: rect overflows bottom edge (%.2f > %.2f mm)', $page, $ry + $rh, $pageH);
        }
    }

    foreach ($a['text'] as $t) {
        if ($t['x'] < $margin - $TOL) {
            $problems[] = sprintf(
                'p%d: text "%.28s" sits in the left margin (x=%.2f, margin=%.2f)',
                $page,
                $t['s'],
                $t['x'],
                $margin
            );
        }
        if ($t['x'] > $pageW - $margin + $TOL) {
            $problems[] = sprintf(
                'p%d: text "%.28s" sits past the right margin (x=%.2f, limit=%.2f)',
                $page,
                $t['s'],
                $t['x'],
                $pageW - $margin
            );
        }
        // A baseline is a couple of points above the visual bottom of the
        // glyphs, so allow the full font size as leading before calling it an
        // overflow past the page edge.
        if ($t['y'] - $t['h'] < -$TOL) {
            $problems[] = sprintf(
                'p%d: text "%.28s" overflows the top edge (baseline %.2f, size %.2f)',
                $page,
                $t['s'],
                $t['y'],
                $t['h']
            );
        }
        if ($t['y'] > $pageH + $TOL) {
            $problems[] = sprintf(
                'p%d: text "%.28s" overflows the bottom edge (y=%.2f > %.2f)',
                $page,
                $t['s'],
                $t['y'],
                $pageH
            );
        }
    }
}

/* ------------------------------------------------- page numbering agrees N */

$flat = '';
foreach ($streams as $s) {
    $flat .= $s;
}

if (preg_match_all('/Page (\d+) of (\d+)/', $flat, $m, PREG_SET_ORDER) === 0) {
    $problems[] = 'no "Page N of M" footer text found on any page';
} else {
    foreach ($m as $hit) {
        $n = (int) $hit[1];
        $total = (int) $hit[2];
        if ($total !== $pageCount) {
            $problems[] = "footer reads \"of $total\" but the document has $pageCount page(s)";
        }
        if ($n < 1 || $n > $pageCount) {
            $problems[] = "footer page number $n is outside 1..$pageCount";
        }
    }
    if (count($m) !== $pageCount) {
        $problems[] = sprintf('%d footer(s) for %d page(s)', count($m), $pageCount);
    }
}

/* ------------------------------------------- encoding stayed inside cp1252 */

// FPDF converts non-ASCII to UTF-16BE with a BOM. If the map in ReceiptPdf::txt()
// missed a character it would show up as a UTF-16 code point outside the
// Windows-1252 repertoire, or as a literal '?' substitution.
if (preg_match_all('/\xEF\xBB\xBF/', $flat, $bm) && count($bm[0]) > 0) {
    // FPDF escaping non-ASCII is legitimate; report it for review rather than
    // failing, so a legitimately-transliterated receipt is not a false alarm.
    $utf16Runs = count($bm[0]);
}

// ReceiptPdf::txt() substitutes "?" for any character it cannot reduce to
// ASCII, so a "?" inside a string literal means something was lost on the way
// to the page. FPDF writes non-ASCII as UTF-16BE with a BOM, which would show
// up as a literal "?" in the same position, so both symptoms are caught here.
$qmarks = preg_match_all('/\([^)]*\?[^)]*\)/', $flat);
if ($qmarks > 0) {
    $sample = [];
    if (preg_match_all('/\(([^)]*\?[^)]*)\)/', $flat, $qm)) {
        $sample = array_slice(array_unique($qm[1]), 0, 5);
    }
    $problems[] = sprintf(
        '%d string(s) contain a literal "?": a character could not be transliterated%s',
        $qmarks,
        $sample === [] ? '' : ' e.g. ' . implode(' | ', $sample)
    );
}

// Belt and braces: FPDF only writes UTF-16 (a BOM) when a string is not pure
// ASCII. ReceiptPdf::txt() is contracted to return ASCII, so a BOM means the
// contract was broken somewhere.
if (str_contains($flat, "\xEF\xBB\xBF")) {
    $problems[] = 'a UTF-16 byte-order mark is present: non-ASCII reached FPDF';
}

/* ------------------------------------------------------------- totals row */

if (!str_contains($flat, 'TOTAL')) {
    $problems[] = 'no TOTAL row found in any page';
}

/* -------------------------------------------- nothing in the footer band */

$footerTop = $pageH - $margin - 16.0 * $probe->scale();
foreach ($perPage as $i => $a) {
    foreach ($a['text'] as $t) {
        // Body text must end above the footer band. The footer itself is drawn
        // there, so its own two runs are exempt.
        $isFooter = str_contains($t['s'], 'Page ') || str_contains($t['s'], 'Thank you');
        if (!$isFooter && $t['y'] > $footerTop + $TOL) {
            $problems[] = sprintf(
                'p%d: body text "%.28s" at y=%.2f collides with the footer band (starts %.2f)',
                $i + 1,
                $t['s'],
                $t['y'],
                $footerTop
            );
        }
    }
}

/* ----------------------------------------------------------------- report */

echo str_repeat('=', 74), "\n";
printf("audited : %s\n", basename($pdfPath));
printf(
    "format  : %s %s   page %.1f x %.1f mm   content %.1f mm   margin %.1f mm   footer band from %.1f\n",
    strtoupper($format),
    $orientation,
    $pageW,
    $pageH,
    $contentW,
    $margin,
    $footerTop
);
printf("pages   : %d   streams: %d   rects: %d   text runs: %d\n", $pageCount, count($streams), array_sum(array_map(static fn($a) => count($a['rects']), $perPage)), array_sum(array_map(static fn($a) => count($a['text']), $perPage)));
echo str_repeat('=', 74), "\n";

if ($problems === []) {
    echo "OK: geometry inside the page box, footers agree, encoding clean.\n";
    exit(0);
}

echo count($problems), " problem(s):\n";
foreach ($problems as $p) {
    echo "  - $p\n";
}
exit(1);
