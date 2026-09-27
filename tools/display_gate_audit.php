<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$base = 'http://localhost/Jayann_Store';
$jar  = tempnam(sys_get_temp_dir(), 'dga');

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

function scan(string $css, array &$out, int $base_line = 1, int $depth = 0): void
{
    $len = strlen($css);
    $i   = 0;
    $buf = '';
    $line = $base_line;

    while ($i < $len) {
        $ch = $css[$i];

        if ($ch === '{') {
            $d = 1;
            $j = $i + 1;
            $inner = '';
            while ($j < $len && $d > 0) {
                if ($css[$j] === '{') { $d++; }
                elseif ($css[$j] === '}') { $d--; }
                $inner .= $css[$j];
                $j++;
            }
            $body = substr($inner, 0, max(0, strlen($inner) - 1));
            $sel  = trim($buf);
            $buf  = '';

            if ($sel !== '' && strpos($sel, '@') === 0) {
                if (stripos($sel, 'media') !== false || stripos($sel, 'supports') !== false) {
                    scan($body, $out, $line + substr_count($css, "\n", 0, $i) - substr_count($body, "\n"), $depth + 1);
                }
            } else {
                $m = [];
                if (preg_match('/(?:^|;)\s*display\s*:\s*([^;]+)/i', $body, $m)) {
                    $out[] = [
                        'sel'   => $sel,
                        'value' => strtolower(trim($m[1])),
                        'line'  => $line + substr_count($css, "\n", 0, $i),
                        'scoped'=> $depth > 0,
                    ];
                }
            }

            $i = $j;
            continue;
        }

        if ($ch === "\n") { $line++; }
        $buf .= $ch;
        $i++;
    }
}

post($base . '/admin/pages/admin_login.php', ['name' => 'admin', 'pass' => 'password123'], $jar);

$sets = [
    ['storefront', 'storefront', [
        '/public/home.php', '/public/products.php', '/public/discounted_products.php',
        '/public/category.php?category=Beverages', '/public/search.php?q=soap',
        '/public/about.php', '/public/contact.php', '/public/login.php', '/public/register.php',
    ], null],
    ['admin', 'admin', [
        '/admin/pages/dashboard.php', '/admin/pages/products.php', '/admin/pages/inventory.php',
        '/admin/pages/placed_orders.php', '/admin/pages/product_archive.php',
        '/admin/pages/stock_movements.php', '/admin/pages/admin_accounts.php',
        '/admin/pages/users_accounts.php', '/admin/pages/messages.php',
        '/admin/pages/update_profile.php', '/admin/pages/register_admin.php',
    ], 'data-sidebar'],
];

$problems = 0;
$stale    = 0;

foreach ($sets as [$label, $bundle, $urls, $sentinel]) {
    $raw = css_bundle_text($bundle, $root);
    $raw = preg_replace('~/\*.*?\*/~s', '', $raw) ?? $raw;
    $rules = [];
    scan($raw, $rules);

    $elements = [];
    $fetched  = 0;

    foreach ($urls as $path) {
        $html = get($base . $path, $jar);
        if (trim($html) === '') { continue; }
        $fetched++;
        if ($sentinel !== null && strpos($html, $sentinel) === false) {
            printf("  stale %-11s %-36s missing `%s` - not authenticated\n", $label, $path, $sentinel);
            $stale++;
            continue;
        }
        if (preg_match_all('/<([a-z][\w-]*)\b[^>]*\bclass="([^"]+)"/i', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $classes = preg_split('/\s+/', trim($hit[2])) ?: [];
                $elements[$hit[1] . '.' . implode('.', $classes)] = $classes;
            }
        }
    }

    printf("  %-11s %2d pages, %3d elements, %d top-level display rules\n",
        $label, $fetched, count($elements), count(array_filter($rules, fn($r) => !$r['scoped'])));

    foreach ($elements as $key => $classes) {
        foreach ($classes as $a) {
            foreach ($classes as $b) {
                if ($a === $b) { continue; }
                foreach ($rules as $ra) {
                    if ($ra['value'] !== 'none' || $ra['scoped']) { continue; }
                    if (!preg_match('/^\.' . preg_quote($a, '/') . '$/', trim($ra['sel']))) { continue; }
                    foreach ($rules as $rb) {
                        if ($rb['value'] === 'none' || $rb['line'] <= $ra['line']) { continue; }
                        if (!preg_match('/^\.' . preg_quote($b, '/') . '$/', trim($rb['sel']))) { continue; }
                        printf(
                            "  FAIL  %s / %s:%d  `.%s { display: %s }` overrides "
                            . "`.%s { display: none }` (line %d) - `%s` is never hidden "
                            . "on desktop\n",
                            $label, $cssFile, $rb['line'], $b, $rb['value'], $a, $ra['line'], $key
                        );
                        $problems++;
                    }
                }
            }
        }
    }
}

@unlink($jar);

if ($stale > 0) {
    printf("  %d page(s) unusable - fix before trusting this audit.\n", $stale);
    exit(2);
}
if ($problems === 0) {
    echo "  ok    no display-gate ordering conflicts in either stylesheet\n";
    exit(0);
}
printf("  %d display-gate problem(s) found.\n", $problems);
exit(1);