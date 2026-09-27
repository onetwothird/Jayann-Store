<?php

declare(strict_types=1);

$base = $argv[1] ?? 'http://localhost/Jayann_Store';

$MIN_VIEWPORT = 320;

require __DIR__ . '/css_manifest.php';

$rootDir = dirname(__DIR__);

$relUrls = static function (array $files) use ($rootDir, $base): array {
    $urls = [];
    foreach ($files as $file) {
        $rel = str_replace('\\', '/', substr($file, strlen($rootDir) + 1));
        $urls[] = $base . '/' . $rel;
    }
    return $urls;
};

$sheets = [
    'storefront' => $relUrls(css_manifest_files('storefront', $rootDir)),
    'admin'      => $relUrls(css_manifest_files('admin', $rootDir)),
];

$pages = [
    'public/home.php'                 => 'storefront',
    'public/products.php'             => 'storefront',
    'public/discounted_products.php'  => 'storefront',
    'public/category.php?category=Beverages' => 'storefront',
    'public/search.php?q=soap'        => 'storefront',
    'public/quick_view.php?pid=8'     => 'storefront',
    'public/cart.php'                 => 'storefront',
    'public/checkout.php'             => 'storefront',
    'public/orders.php'               => 'storefront',
    'public/login.php'                => 'storefront',
    'public/register.php'             => 'storefront',
    'public/profile.php'              => 'storefront',
    'public/update_profile.php'       => 'storefront',
    'public/update_address.php'       => 'storefront',
    'public/about.php'                => 'storefront',
    'public/contact.php'              => 'storefront',
    'admin/pages/admin_login.php'     => 'admin',
];

function strip_comments(string $css): string
{
    return (string) preg_replace('!/\*.*?\*/!s', '', $css);
}

function parse_css(string $css): array
{
    $css  = strip_comments($css);
    $out  = [];
    $depth = 0;
    $stack = [];
    $buf   = '';
    $i     = 0;
    $len   = strlen($css);

    while ($i < $len) {
        $ch = $css[$i];

        if ($ch === '{') {
            $depth++;
            $selector = trim(preg_replace('/\s+/', ' ', $buf));
            $buf = '';

            if ($depth === 1 && str_starts_with($selector, '@')) {
                $stack[] = $selector;
                $i++;
                continue;
            }

            $decls = '';
            $braceDepth = 1;
            $i++;
            while ($i < $len && $braceDepth > 0) {
                if ($css[$i] === '{') {
                    $braceDepth++;
                } elseif ($css[$i] === '}') {
                    $braceDepth--;
                    if ($braceDepth === 0) {
                        break;
                    }
                }
                $decls .= $css[$i];
                $i++;
            }
            $i++;

            $media = $stack ? end($stack) : null;
            foreach (explode(',', $selector) as $single) {
                $single = trim($single);
                if ($single === '' || $single[0] === '@') {
                    continue;
                }
                $out[$single][] = ['decls' => $decls, 'media' => $media];
            }
            $depth--;
            continue;
        }

        if ($ch === '}') {
            if ($stack && $depth === 1) {
                array_pop($stack);
            }
            $depth = max(0, $depth - 1);
            $i++;
            continue;
        }

        $buf .= $ch;
        $i++;
    }

    return $out;
}

function decl(string $decls, string $prop): ?string
{
    if (preg_match('/(?:^|;)\s*' . preg_quote($prop, '/') . '\s*:\s*([^;]+)/i', $decls, $m)) {
        return trim($m[1]);
    }
    return null;
}

function px(string $value): ?int
{
    return preg_match('/^(\d+)px$/', trim($value), $m) ? (int) $m[1] : null;
}

function inside_scroller(string $selector, array $sheet): bool
{

    if (!preg_match('/\.([a-z0-9]+(?:__[a-z0-9-]+)?)/i', $selector, $m)) {
        return false;
    }
    $block = $m[1];
    $parts = explode('__', $block);
    $root  = $parts[0];

    $ancestors = ['.' . $root];
    if (isset($parts[1])) {
        $ancestors[] = '.' . $root . '__inner';
        $ancestors[] = '.' . $root . '__wrap';
    }

    $ancestors[] = '.' . $root;

    foreach ($ancestors as $ancestor) {
        foreach ($sheet[$ancestor] ?? [] as $entry) {
            foreach (['overflow', 'overflow-x'] as $prop) {
                $v = decl($entry['decls'], $prop);
                if ($v !== null && in_array($v, ['auto', 'scroll', 'hidden', 'clip'], true)) {
                    return true;
                }
            }
        }
    }

    return false;
}

$rules = [];
foreach ($sheets as $name => $urls) {
    $css = '';
    foreach ($urls as $url) {
        $part = @file_get_contents($url);
        if ($part === false) {
            fwrite(STDERR, "Could not read $url\n");
            exit(2);
        }
        $css .= $part;
    }
    $rules[$name] = parse_css($css);
    printf("  parsed %-10s %2d files %6d bytes -> %d selectors\n", $name . ':', count($urls), strlen($css), count($rules[$name]));
}

$mediaTouched = [];
foreach ($rules as $set) {
    foreach ($set as $selector => $entries) {
        foreach ($entries as $entry) {
            if ($entry['media'] !== null) {
                $mediaTouched[$selector] = true;
            }
        }
    }
}

$definedSelectors = [];
foreach ($rules as $set) {
    foreach ($set as $selector => $entries) {
        $definedSelectors[$selector] = true;
    }
}

$problems = [];

function flag(string $page, string $kind, string $detail): void
{
    global $problems;
    $problems[] = ['page' => $page, 'kind' => $kind, 'detail' => $detail];
}

foreach ($pages as $page => $sheetName) {
    $html = @file_get_contents($base . '/' . $page);
    if ($html === false) {
        flag($page, 'FETCH', 'could not load the page');
        continue;
    }

    preg_match_all('/class="([^"]+)"/', $html, $m);
    $used = [];
    foreach ($m[1] as $blob) {
        foreach (preg_split('/\s+/', trim($blob)) as $cls) {
            if ($cls !== '') {
                $used[$cls] = true;
            }
        }
    }

    $sheet = $rules[$sheetName];

    foreach (array_keys($used) as $cls) {

        $candidates = ['.' . $cls];
        foreach (array_keys($sheet) as $sel) {
            if (str_contains($sel, '.' . $cls)) {
                $candidates[] = $sel;
            }
        }

        foreach ($candidates as $sel) {
            if (!isset($sheet[$sel])) {
                continue;
            }

            foreach ($sheet[$sel] as $entry) {
                $d = $entry['decls'];

                $w = decl($d, 'width');
                if ($w !== null && $entry['media'] === null) {
                    $n = px($w);
                    if ($n !== null && $n > $MIN_VIEWPORT) {
                        $override = isset($mediaTouched[$sel]);
                        if (!$override) {
                            flag($page, 'FIXED-WIDTH', "$sel is {$n}px wide with no media-query override");
                        }
                    }
                }

                $mw = decl($d, 'min-width');
                if ($mw !== null && $entry['media'] === null) {
                    $n = px($mw);
                    if ($n !== null && $n > $MIN_VIEWPORT && !isset($mediaTouched[$sel])) {
                        flag($page, 'FIXED-MIN-WIDTH', "$sel has min-width {$n}px with no media-query override");
                    }
                }

                $ws = decl($d, 'white-space');
                if ($ws === 'nowrap' && $entry['media'] === null) {
                    $hasEscape = decl($d, 'overflow') !== null
                        || decl($d, 'overflow-x') !== null
                        || decl($d, 'text-overflow') !== null
                        || decl($d, 'display') === 'flex'
                        || isset($mediaTouched[$sel])
                        || inside_scroller($sel, $sheet);

                    if (!$hasEscape) {
                        flag($page, 'NOWRAP', "$sel is white-space:nowrap with nothing to clip or scroll it");
                    }
                }

                $gtc = decl($d, 'grid-template-columns');
                if ($gtc !== null && $entry['media'] === null) {
                    if (preg_match('/^repeat\((\d+)\s*,/', $gtc, $gm) && (int) $gm[1] > 2) {
                        if (!isset($mediaTouched[$sel])) {
                            flag($page, 'FIXED-COLUMNS', "$sel uses {$gm[1]} fixed columns with no media-query override");
                        }
                    }
                    if (preg_match('/minmax\(\s*(\d+)px/', $gtc, $mm) && (int) $mm[1] > $MIN_VIEWPORT) {
                        if (!isset($mediaTouched[$sel])) {
                            flag($page, 'WIDE-MINMAX', "$sel has minmax({$mm[1]}px, ...) with no media-query override");
                        }
                    }
                }
            }
        }
    }
}

echo "\n";

if ($problems === []) {
    echo "  No responsive risks found across " . count($pages) . " pages.\n\n";
    exit(0);
}

$byKind = [];
foreach ($problems as $p) {
    $byKind[$p['kind']][] = $p;
}
ksort($byKind);

foreach ($byKind as $kind => $items) {
    echo '  ' . $kind . ' (' . count($items) . ")\n";
    $seen = [];
    foreach ($items as $p) {
        $key = $p['detail'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $pagesWithIt = [];
        foreach ($items as $q) {
            if ($q['detail'] === $key) {
                $pagesWithIt[] = $q['page'];
            }
        }
        echo '    - ' . $key . "\n";
        echo '      on: ' . implode(', ', array_slice($pagesWithIt, 0, 5))
            . (count($pagesWithIt) > 5 ? ' (+' . (count($pagesWithIt) - 5) . ' more)' : '') . "\n";
    }
}

echo "\n  " . count($problems) . " issue(s) across " . count($pages) . " pages.\n\n";
exit(1);
