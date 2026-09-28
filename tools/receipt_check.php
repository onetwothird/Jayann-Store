<?php

declare(strict_types=1);

/**
 * Renders ReceiptPdf against sample data in every supported paper format and
 * orientation, and asserts the layout never overflows its own page box.
 *
 * Run from the project root:
 *   php tools/receipt_check.php [output-directory]
 *
 * Writes one PDF per combination into the output directory so the result can
 * be eyeballed as well as measured.
 */

$root = dirname(__DIR__);

require_once $root . '/libs/fpdf.php';
require_once $root . '/app/receipt_pdf.php';

$outDir = $argv[1] ?? ($root . '/tools/.receipt-out');

if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Could not create $outDir\n");
    exit(1);
}

/* ---------------------------------------------------------------- fixtures */

$store = [
    'name'     => "Jayann's Store",
    'legal'    => 'Jayann Store',
    'tagline'  => 'Your one-stop shop',
    'phone'    => '+63 938 510 0460',
    'email'    => 'contact@jayannstore.com',
    'address'  => 'Blk 57, Lot 14, Hyacinth Residence',
    'currency' => "\u{20B1}", // the peso sign, which is not in Windows-1252
];

$shortLines = [
    ['pid' => 2, 'name' => 'Summit Natural Drinking Water 350ml', 'quantity' => 2, 'unit' => 27.00],
    ['pid' => 7, 'name' => "Jack 'n Jill V Cut Potato Chips Spicy BBQ 162g", 'quantity' => 1, 'unit' => 30.00],
    ['pid' => 11, 'name' => 'LISTERINE Mouthwash 250mL Cool Mint', 'quantity' => 3, 'unit' => 20.00],
];

// The awkward cases a receipt actually hits: a name with no spaces, a name far
// too long for any column, and enough rows to spill onto a second page.
$awkward = [['pid' => 1, 'name' => 'SILKA_Whitening_Herbal_Soap_Green_Papaya_135g', 'quantity' => 1, 'unit' => 20.00]];
for ($i = 1; $i <= 30; $i++) {
    $awkward[] = [
        'pid'      => 100 + $i,
        'name'     => 'COCA-COLA Regular Mismo 290ml multipack case number ' . $i
            . ' with a deliberately long product title that has to wrap across more than one line',
        'quantity' => $i,
        'unit'     => 12.50 * $i,
    ];
}

$view = [
    'date_label'     => '14 Mar 2025, 3:42 PM',
    'status_label'   => 'Paid',
    'status_tone'    => 'good',
    'customer_name'  => 'Juan Dela Cruz',
    'customer_phone' => '09385100460',
    'customer_email' => 'juan.delacruz@example.com',
    'method_label'   => 'GCash',
    'gateway_ref'    => 'PAY-2X9K4LM7QW8RT3YU5',
    'address'        => "Blk 57, Lot 14, Hyacinth Residence, Barangay Bagumbayan North, "
        . "Cavite City, Cavite 4104, Philippines. Landmark: beside the pale blue tricycle terminal.",
];

/* ------------------------------------------------------------------ runner */

$formats = ['a3', 'a4', 'a5', 'letter', 'legal'];
$failures = [];
/**
 * Group a receipt's text runs by page, with y measured downward from the top of
 * the page (PDF user space is bottom-up, which reads backwards).
 *
 * @return list<array<int, array{x: float, y: float, s: string}>>
 */
function receiptPages(string $pdf, float $pageH): array
{
    $streams = [];
    $offset = 0;

    while (($start = strpos($pdf, 'stream', $offset)) !== false) {
        $before = substr($pdf, max(0, $start - 500), min(500, $start));
        $dictAt = strrpos($before, '<<');
        $dict = $dictAt === false ? '' : substr($before, $dictAt);

        $at = $start + 6;
        if (($pdf[$at] ?? '') === "\r") {
            $at++;
        }
        if (($pdf[$at] ?? '') === "\n") {
            $at++;
        }

        $end = strpos($pdf, 'endstream', $at);
        if ($end === false) {
            break;
        }

        $raw = substr($pdf, $at, $end - $at);
        $offset = $end + 9;

        if (!str_contains($dict, 'FlateDecode')) {
            continue;
        }

        $data = @gzuncompress($raw);
        if ($data === false) {
            $data = @gzinflate($raw);
        }
        if ($data === false || $data === '' || (!str_contains($data, ' re') && !str_contains($data, 'BT'))) {
            continue;
        }

        $streams[] = $data;
    }

    $pages = [];
    foreach ($streams as $stream) {
        $runs = [];
        if (preg_match_all('/BT ([-\d.]+) ([-\d.]+) Td \(([^)]*)\) Tj/', $stream, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $runs[] = [
                    'x' => (float) $hit[1],
                    'y' => $pageH - ((float) $hit[2] / (72.0 / 25.4)),
                    's' => $hit[3],
                ];
            }
        }
        $pages[] = $runs;
    }

    return $pages;
}

/** @return array<string,float> the topmost y of each named run */
function landmarkY(array $runs, string $needle): ?float
{
    foreach ($runs as $r) {
        if (str_contains($r['s'], $needle)) {
            return $r['y'];
        }
    }
    return null;
}

$rows = [];

foreach ($formats as $format) {
    foreach (['P', 'L'] as $orientation) {
        foreach (['short' => $shortLines, 'awkward' => $awkward] as $label => $set) {
            $name = sprintf('receipt_%s_%s_%s.pdf', $format, strtolower($orientation), $label);

            $subtotal = 0.0;
            foreach ($set as $l) {
                $subtotal += $l['unit'] * $l['quantity'];
            }
            $total = $subtotal + 50;

            try {
                $pdf = new ReceiptPdf($store, [
                    'format'      => $format,
                    'orientation' => $orientation,
                    'logo'        => $root,
                ]);

                // build() + bytes() rather than render(): FPDF's 'F' mode always
                // resolves relative to the process working directory, so writing
                // 20 combinations that way would overwrite one file 20 times.
                $pdf->build($view, $set, [
                    'subtotal' => "\u{20B1}" . number_format($subtotal, 2),
                    'shipping' => "\u{20B1}50.00",
                    'total'    => "\u{20B1}" . number_format($total, 2),
                ], 'JYS-000123');

                $bytes = $pdf->bytes();

                if (strncmp($bytes, '%PDF-', 5) !== 0) {
                    $failures[] = "$format/$orientation/$label did not produce a PDF header";
                    continue;
                }

                file_put_contents($outDir . '/' . $name, $bytes);

                $pages = receiptPages($bytes, $pdf->pageHeight());
                $where = $format . '/' . $orientation . '/' . $label;

                /* Reading order is part of the layout, not a detail. A row that
                   lands back at the top of the page, above the item table, is
                   invisible to a geometry check because it is still inside the
                   page box. */
                $first = $pages[0] ?? [];
                $tableTop = landmarkY($first, 'ITEM');
                $metaTop  = landmarkY($first, 'BILLED TO');

                if ($tableTop === null) {
                    $failures[] = "$where: no ITEM column header found on page 1";
                }
                if ($metaTop !== null && $tableTop !== null && $tableTop <= $metaTop) {
                    $failures[] = sprintf(
                        '%s: item table (y=%.1f) starts above the meta panels (y=%.1f); the layout returned to the top of the page',
                        $where,
                        $tableTop,
                        $metaTop
                    );
                }

                // The header band must not be reused by the table.
                $storeName = landmarkY($first, "Jayann's Store");
                if ($storeName !== null && $tableTop !== null && $tableTop <= $storeName) {
                    $failures[] = sprintf(
                        '%s: item table (y=%.1f) overlaps the brand band (y=%.1f)',
                        $where,
                        $tableTop,
                        $storeName
                    );
                }

                // Every page after the first must repeat the column headers,
                // otherwise a continued table has no labels.
                foreach (array_slice($pages, 1) as $i => $runs) {
                    if (landmarkY($runs, 'ITEM') === null) {
                        $failures[] = sprintf('%s: page %d has no repeated column header', $where, $i + 2);
                    }
                }

                // Exactly one footer per page, numbered 1..N.
                $footerCount = 0;
                foreach ($pages as $i => $runs) {
                    foreach ($runs as $r) {
                        if (preg_match('/^Page (\d+) of (\d+)$/', $r['s'], $fm)) {
                            $footerCount++;
                            if ((int) $fm[1] !== $i + 1) {
                                $failures[] = sprintf('%s: page %d is numbered %s', $where, $i + 1, $fm[1]);
                            }
                            if ((int) $fm[2] !== count($pages)) {
                                $failures[] = sprintf('%s: page %d says "of %s" but there are %d pages', $where, $i + 1, $fm[2], count($pages));
                            }
                        }
                    }
                }
                if ($footerCount !== count($pages)) {
                    $failures[] = sprintf('%s: %d footers for %d pages', $where, $footerCount, count($pages));
                }

                // Subtotal + delivery must reconcile to the printed total, or
                // the receipt contradicts itself.
                $printed = [];
                foreach ($pages as $runs) {
                    foreach ($runs as $r) {
                        if (preg_match('/^PHP ([\d,]+\.\d{2})$/', trim($r['s']), $am)) {
                            $printed[] = (float) str_replace(',', '', $am[1]);
                        }
                    }
                }
                $last = $printed === [] ? null : $printed[count($printed) - 1];
                if ($last !== null && abs($last - $total) > 0.005) {
                    $failures[] = sprintf(
                        '%s: the last printed amount is %.2f but the order total is %.2f',
                        $where,
                        $last,
                        $total
                    );
                }

                $rows[] = [
                    $format,
                    $orientation,
                    $label,
                    $name,
                    count($pages),
                    number_format(strlen($bytes) / 1024, 1) . ' KB',
                ];
            } catch (Throwable $e) {
                $failures[] = sprintf('%s/%s/%s threw %s: %s', $format, $orientation, $label, get_class($e), $e->getMessage());
            }
        }
    }
}

/* --------------------------------------------- overflow assertions via API */

$geometry = [];

foreach ([['a3', 'P'], ['a3', 'L'], ['a4', 'P'], ['a4', 'L'], ['a5', 'P'], ['a5', 'L'], ['letter', 'P'], ['legal', 'L']] as [$f, $o]) {
    $p = new ReceiptPdf($store, ['format' => $f, 'orientation' => $o, 'logo' => $root]);
    $geometry[] = sprintf(
        '%-7s %s   page %6.1f x %-6.1f   margin %4.1f   content %6.1f   scale %.3f',
        $f,
        $o,
        $p->pageWidth(),
        $p->pageHeight(),
        $p->marginValue(),
        $p->contentWidth(),
        $p->scale()
    );

    if ($p->contentWidth() <= 0) {
        $failures[] = "$f/$o produced a non-positive content width";
    }
    if ($p->contentWidth() > $p->pageWidth() - (2 * $p->marginValue()) + 0.001) {
        $failures[] = "$f/$o content width exceeds the page box";
    }
    // The footer must not eat the whole page on a small format.
    if ($p->pageHeight() - 2 * $p->marginValue() < 60) {
        $failures[] = "$f/$o has too little usable height";
    }
}

/* ----------------------------------------------------------------- report */

printf("%-8s %-4s %-9s %-30s %6s %8s\n", 'FORMAT', 'ORIENT', 'DATA', 'FILE', 'PAGES', 'SIZE');
echo str_repeat('-', 72), "\n";
foreach ($rows as $r) {
    printf("%-8s %-4s %-9s %-30s %6d %8s\n", $r[0], $r[1], $r[2], $r[3], $r[4], $r[5]);
}

echo "\nGeometry (everything derived, nothing hardcoded):\n";
foreach ($geometry as $g) {
    echo '  ', $g, "\n";
}

echo "\n", str_repeat('=', 78), "\n";

if ($failures) {
    echo count($failures), " problem(s):\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
}

echo 'All ' . count($rows) . " renders completed with no geometry violations.\n";
echo "PDFs written to: $outDir\n";
