<?php

declare(strict_types=1);

$root  = dirname(__DIR__);
$files = [
    $root . '/assets/css/style.css',
    $root . '/assets/css/admin_style.css',
];

function spec_of(string $selector): int
{

    $selector = preg_replace('/::?[a-z-]+(\([^)]*\))?/i', '.x', $selector) ?? $selector;

    $ids      = preg_match_all('/#[\w-]+/', $selector);
    $classes  = preg_match_all('/(?:\.[\w-]+|\[[^\]]*\])/', $selector);
    $elements = preg_match_all('/(?:^|[\s>+~,])([a-z][\w-]*)/i', $selector);

    return ($ids * 100) + ($classes * 10) + $elements;
}

function subjects_of(string $selector): ?array
{

    $branches = [];
    $depth    = 0;
    $current  = '';
    $len      = strlen($selector);

    for ($i = 0; $i < $len; $i++) {
        $ch = $selector[$i];
        if ($ch === '(' || $ch === '[') {
            $depth++;
        } elseif ($ch === ')' || $ch === ']') {
            $depth--;
        }
        if ($ch === ',' && $depth === 0) {
            $branches[] = $current;
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    $branches[] = $current;

    $out = [];
    foreach ($branches as $branch) {
        $branch = trim($branch);
        if ($branch === '') {
            continue;
        }

        $parts = preg_split('/\s*[>+~]\s*|\s+/', $branch) ?: [];
        $last  = trim((string) end($parts));
        if ($last === '') {
            continue;
        }

        $classes = [];
        if (preg_match_all('/\.([\w-]+)/', $last, $m)) {
            $classes = $m[1];
        }

        $element = preg_replace(
            '/\.[\w-]+|:{1,2}[\w-]+(\([^)]*\))?|#[\w-]+|\[[^\]]*\]/',
            '',
            $last
        ) ?? '';
        $element = trim($element);

        if ($element === '' && $classes === []) {
            continue;
        }
        if ($classes === []) {
            return null;
        }

        sort($classes);

        $out[] = [
            'element' => $element,
            'classes' => $classes,
            'branch'  => $branch,
        ];
    }

    return $out === [] ? null : $out;
}

function competes(?array $a, ?array $b, string $selector): bool
{
    if ($a === null || $b === null) {
        return false;
    }
    if ($a['element'] !== '' || $b['element'] !== '') {
        return false;
    }
    if ($a['classes'] === [] || $b['classes'] === []) {
        return false;
    }

    if (str_contains($selector, ':')) {
        return false;
    }

    $short = count($a['classes']) <= count($b['classes']) ? $a['classes'] : $b['classes'];
    $long  = count($a['classes']) <= count($b['classes']) ? $b['classes'] : $a['classes'];

    foreach ($short as $class) {
        if (!in_array($class, $long, true)) {
            return false;
        }
    }

    return true;
}

function media_range(string $prelude): ?array
{
    $min = 0;
    $max = PHP_INT_MAX;

    if (preg_match('/min-width:\s*(\d+)/', $prelude, $m)) {
        $min = (int) $m[1];
    }
    if (preg_match('/max-width:\s*(\d+)/', $prelude, $m)) {
        $max = (int) $m[1];
    }

    if ($min === 0 && $max === PHP_INT_MAX) {
        return null;
    }

    if (preg_match('/hover|pointer|orientation|prefers-|update/', $prelude)) {
        return null;
    }

    return ['min' => $min, 'max' => $max];
}

function walk_block(
    string $body,
    array $ranges,
    int $depth,
    string $src,
    int $base,
    array &$plain,
    array &$scoped,
    array &$device,
    int &$order
): void {
    $len = strlen($body);
    $i   = 0;
    $buf = '';
    $bufAt = 0;

    while ($i < $len) {
        $ch = $body[$i];

        if ($ch === '}') {
            $i++;
            continue;
        }

        if ($ch === '{') {
            $prelude = trim(preg_replace('/\s+/', ' ', $buf) ?? '');
            $buf     = '';

            $braceDepth = 1;
            $j          = $i + 1;
            while ($j < $len && $braceDepth > 0) {
                if ($body[$j] === '{') {
                    $braceDepth++;
                } elseif ($body[$j] === '}') {
                    $braceDepth--;
                }
                $j++;
            }
            $inner = substr($body, $i + 1, max(0, $j - $i - 2));
            $at    = strtolower($prelude);

            if (str_starts_with($at, '@')) {
                $head = trim(explode(' ', $at)[0] ?? '');

                if ($head === '@media' && preg_match('/\bprint\b/i', $prelude)) {

                    $i = $j;
                    $buf = '';
                    continue;
                }

                $range = media_range($prelude);

                $innerBase = $base + $i + 1;

                if (($head === '@media' || $head === '@supports' || $head === '@container')
                    && $range !== null
                ) {
                    walk_block(
                        $inner,
                        array_merge($ranges, [$range]),
                        $depth + 1,
                        $src,
                        $innerBase,
                        $plain,
                        $scoped,
                        $device,
                        $order
                    );
                } elseif ($head === '@media' || $head === '@supports' || $head === '@container') {

                    walk_block($inner, $ranges, $depth + 1, $src, $innerBase, $plain, $scoped, $device, $order);
                }

            } elseif (!preg_match('/^(from|to|\d+%)$/i', $prelude)) {

                $branches = subjects_of($prelude);

                if ($branches !== null
                    && preg_match_all('/([\w-]+)\s*:\s*([^;{}]+)/', $inner, $decls, PREG_SET_ORDER)
                ) {
                    $line = substr_count($src, "\n", 0, $base + $bufAt) + 1;

                    foreach ($branches as $subject) {

                        $spec = spec_of($subject['branch']);

                        foreach ($decls as $decl) {
                            $entry = [
                                'subj'   => $subject,
                                'prop'   => trim($decl[1]),
                                'value'  => trim($decl[2]),
                                'spec'   => $spec,
                                'line'   => $line,
                                'sel'    => $prelude,
                                'ranges' => $ranges,
                            ];
                            $order++;
                            $entry['order'] = $order;

                            $entry['kind'] = $ranges !== []
                                ? 'scoped'
                                : ($depth === 0 ? 'plain' : 'device');

                            if ($entry['kind'] === 'plain') {
                                $plain[] = $entry;
                            } elseif ($entry['kind'] === 'device') {
                                $device[] = $entry;
                            } else {
                                $scoped[] = $entry;
                            }
                        }
                    }
                }
            }

            $i   = $j;
            $buf = '';
            while ($i < $len && ctype_space($body[$i])) {
                $i++;
            }
            $bufAt = $i;
            continue;
        }

        if ($ch === ';' && trim($buf) === '') {
            $i++;
            while ($i < $len && ctype_space($body[$i])) {
                $i++;
            }
            $bufAt = $i;
            continue;
        }

        if ($buf === '' && ctype_space($ch)) {
            $i++;
            continue;
        }
        if ($buf === '') {
            $bufAt = $i;
        }
        $buf .= $ch;
        $i++;
    }
}

function winner_at(array $entries, int $width): ?array
{
    $best = null;

    foreach ($entries as $e) {
        $applies = true;
        foreach ($e['ranges'] as $r) {
            if ($width < $r['min'] || $width > $r['max']) {
                $applies = false;
                break;
            }
        }
        if (!$applies) {
            continue;
        }

        if ($best === null
            || $e['spec'] > $best['spec']
            || ($e['spec'] === $best['spec'] && $e['order'] > $best['order'])
        ) {
            $best = $e;
        }
    }

    return $best;
}

$fail = 0;

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }

    $rel = str_replace($root . '/', '', $file);
    $css = (string) file_get_contents($file);

    $src = preg_replace_callback(
        '#/\*.*?\*/#s',
        static fn (array $m): string => str_repeat(' ', strlen($m[0])),
        $css
    ) ?? $css;

    $open  = substr_count($src, '{');
    $close = substr_count($src, '}');
    if ($open !== $close) {
        echo "  FAIL  $rel: unbalanced braces, $open '{' vs $close '}'\n";
        $fail++;
    }

    $plain  = [];
    $scoped = [];
    $device = [];
    $order  = 0;
    walk_block($src, [], 0, $src, 0, $plain, $scoped, $device, $order);

    if ($plain === [] && $scoped === [] && $device === []) {
        echo "  FAIL  $rel: no rules parsed, the reader is broken\n";
        $fail++;
        continue;
    }

    $cases = [];

    $byProp = [];
    foreach (array_merge($plain, $scoped, $device) as $e) {
        $byProp[$e['prop']][] = $e;
    }

    foreach ($scoped as $s) {
        $widths = [];
        foreach ($s['ranges'] as $r) {
            if ($r['max'] === PHP_INT_MAX) {
                continue;
            }
            $widths[] = $r['max'];
        }
        if ($widths === []) {
            continue;
        }

        foreach (array_unique($widths) as $width) {

            $rivals = [];
            foreach ($byProp[$s['prop']] ?? [] as $e) {
                if (competes($e['subj'], $s['subj'], $e['sel'])) {
                    $rivals[] = $e;
                }
            }
            if ($rivals === []) {
                continue;
            }

            $winner = winner_at($rivals, $width);

            if ($winner === null || $winner['order'] === $s['order']) {
                continue;
            }
            if ($winner['value'] === $s['value']) {
                continue;
            }
            if ($winner['kind'] === 'scoped') {
                continue;
            }

            $key = $s['line'] . '|' . $s['prop'] . '|' . $width;
            $cases[$key] = ['mine' => $s, 'rival' => $winner, 'width' => $width];
        }
    }

    $broken = array_filter(
        $cases,
        static fn (array $c): bool => $c['rival']['kind'] === 'plain'
    );
    $review = array_filter(
        $cases,
        static fn (array $c): bool => $c['rival']['kind'] !== 'plain'
    );

    foreach ($review as $case) {
        $m = $case['mine'];
        $v = $case['rival'];
        printf(
            "  note  %s:%d  at <=%dpx the device rule `%s { %s: %s }` overrides "
            . "the mobile rule `%s { %s }` - confirm that is intended\n",
            $rel,
            $v['line'],
            $case['width'],
            $v['sel'],
            $v['prop'],
            $v['value'],
            $m['sel'],
            $m['prop']
        );
    }

    if ($broken === [] && $review === []) {
        printf(
            "  ok    %s: braces balanced, %d unconditional + %d width-scoped + "
            . "%d device-scoped declarations, every mobile override wins its cascade\n",
            $rel,
            count($plain),
            count($scoped),
            count($device)
        );
        continue;
    }

    if ($broken === []) {
        printf(
            "  ok    %s: braces balanced, %d unconditional + %d width-scoped + "
            . "%d device-scoped declarations, no mobile override is defeated\n",
            $rel,
            count($plain),
            count($scoped),
            count($device)
        );
        continue;
    }

    foreach ($broken as $case) {
        $m = $case['mine'];
        $v = $case['rival'];
        printf(
            "  FAIL  %s:%d  at <=%dpx the mobile rule `%s { %s: %s }` loses to the "
            . "unconditional `%s { %s: %s }` (specificity %d vs %d)\n",
            $rel,
            $m['line'],
            $case['width'],
            $m['sel'],
            $m['prop'],
            $m['value'],
            $v['sel'],
            $v['prop'],
            $v['value'],
            $v['spec'],
            $m['spec']
        );
        $fail++;
    }
}

echo $fail === 0
    ? "\nStylesheet audit clean.\n"
    : "\n$fail stylesheet problem(s) found.\n";

exit($fail === 0 ? 0 : 1);
