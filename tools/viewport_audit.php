<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$base = 'http://localhost/Jayann_Store';
$jar  = tempnam(sys_get_temp_dir(), 'vpa');

require __DIR__ . '/css_manifest.php';

function get(string $url, string $jar): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
    ]);
    $b = (string) curl_exec($ch);
    curl_close($ch);
    return $b;
}

function post(string $url, array $fields, string $jar): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
    ]);
    $b = (string) curl_exec($ch);
    curl_close($ch);
    return $b;
}

$viewports = [320, 360, 390, 414, 480, 600, 768, 834, 1024, 1180,
              1280, 1440, 1600, 1920, 2200, 2560, 3000, 3440, 3840];

function root_px(int $vw): float
{
    return $vw >= 3000 ? 18.0 : ($vw >= 2200 ? 17.0 : 16.0);
}

function pad_storefront(int $vw, float $rem): float
{
    if ($vw <= 480) { return 0.75 * $rem; }
    if ($vw <= 640) { return 1.00 * $rem; }
    return 1.25 * $rem;
}

function width_storefront(int $vw): float
{
    $rem = root_px($vw);
    $container = match (true) {
        $vw >= 3000 => 1760.0,
        $vw >= 2200 => 1560.0,
        $vw >= 1600 => 1400.0,
        default      => 1240.0,
    };
    return min((float) $vw, $container) - 2 * pad_storefront($vw, $rem);
}

function width_admin(int $vw): float
{
    $rem = root_px($vw);
    $avail = $vw <= 1024 ? (float) $vw - 2 * $rem : $vw - 16.5 * $rem;
    return min($avail, 1760.0) - 2 * (1.5 * $rem);
}

function parse_grids(string $css): array
{
    $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;
    $out  = [];
    if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER)) {
        return $out;
    }
    foreach ($m as $set) {
        $sel = trim($set[1]);
        if ($sel === '' || strpos($sel, '@') === 0) { continue; }
        $body = $set[2];
        if (!preg_match('/grid-template-columns\s*:\s*repeat\(\s*(auto-fit|auto-fill)\s*,\s*minmax\(\s*([\d.]+)(px|rem)\s*,/i', $body, $g)) {
            continue;
        }
        $gap = 16.0;
        if (preg_match('/gap\s*:\s*var\(--sp-(\d+)\)/', $body, $gg)) {
            $gap = 0.25 * (float) $gg[1];
        } elseif (preg_match('/gap\s*:\s*([\d.]+)px/', $body, $gg)) {
            $gap = (float) $gg[1];
        }
        $key = null;
        foreach (explode(',', $sel) as $branch) {
            $b = trim($branch);
            if ($b !== '' && !preg_match('/[\s>+~]/', $b)) { $key = $b; break; }
        }
        $out[] = [
            'sel'  => $sel, 'key' => $key, 'min' => (float) $g[2],
            'unit' => strtolower($g[3]), 'kind' => strtolower($g[1]), 'gap' => $gap,
        ];
    }
    return $out;
}

$pages = [
    'storefront' => ['/public/home.php', '/public/products.php', '/public/cart.php',
                     '/public/checkout.php', '/public/orders.php', '/public/contact.php',
                     '/public/about.php', '/public/discounted_products.php'],
    'admin'      => ['/admin/pages/dashboard.php', '/admin/pages/products.php',
                     '/admin/pages/inventory.php', '/admin/pages/placed_orders.php',
                     '/admin/pages/product_archive.php', '/admin/pages/messages.php'],
];

post($base . '/admin/pages/admin_login.php', ['name' => 'admin', 'pass' => 'password123'], $jar);

$items = [];

foreach ($pages as $label => $paths) {
    foreach ($paths as $p) {
        $html = get($base . $p, $jar);
        if (trim($html) === '') { continue; }
        $prev = libxml_use_internal_errors(true);
        $doc  = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new DOMXPath($doc);
        foreach ($xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " ")]') as $el) {
            $key = '.' . $el->getAttribute('class');
            $n = 0;
            foreach ($el->childNodes as $c) {
                if ($c->nodeType === XML_ELEMENT_NODE) { $n++; }
            }
            $items[$label][$key] = max($items[$label][$key] ?? 0, $n);
        }
    }
}

@unlink($jar);

$bundles = ['storefront', 'admin'];

$MIN_CARD = 120.0;
$MAX_COLS = 10;
$fail = 0;
$checked = 0;

foreach ($bundles as $label) {
    $grids = parse_grids(css_bundle_text($label, $root));
    foreach ($viewports as $vw) {
        $avail = $label === 'storefront' ? width_storefront($vw) : width_admin($vw);
        $avail = max(0.0, $avail);
        foreach ($grids as $g) {
            $minCol = $g['unit'] === 'rem' ? $g['min'] * root_px($vw) : $g['min'];
            if ($minCol <= 0) { continue; }
            $cols = max(1, (int) floor(($avail + $g['gap']) / ($minCol + $g['gap'])));

            $have = $items[$label][$g['key']] ?? null;
            if ($g['kind'] === 'auto-fit' && $have !== null && $have > 0) {
                $cols = min($cols, $have);
            }
            $cols  = max(1, $cols);
            $card  = ($avail - $g['gap'] * ($cols - 1)) / $cols;
            $checked++;

            $bad = $cols > $MAX_COLS || $card < $MIN_CARD;
            if ($bad) {
                printf("  FAIL  %-10s %5dpx  %-28s %2d cols, %3.0fpx each%s\n",
                    $label, $vw, $g['sel'], $cols, $card,
                    $have === null ? '  (item count unknown)' : "  (has $have items)");
                $fail++;
            }
        }
    }
}

printf("  checked %d grid/viewport pairs across %d viewports (%dpx - %dpx)\n",
    $checked, count($viewports), min($viewports), max($viewports));

if ($fail === 0) {
    echo "  ok    every auto grid stays usable at every tested width\n";
    exit(0);
}
printf("  %d unusable combination(s).\n", $fail);
exit(1);