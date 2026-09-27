<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir  = $argv[1] ?? '';
array_shift($argv);
array_shift($argv);

ob_start();
require __DIR__ . '/strip_comments.php';
ob_end_clean();

if (!function_exists('strip')) {
    fwrite(STDERR, "could not load strip()\n");
    exit(1);
}

$norm = static fn (string $s): string => str_replace("\r\n", "\n", $s);

foreach ($argv as $rel) {
    $relWin = str_replace('/', '\\', $rel);
    $ext    = strtolower(pathinfo($rel, PATHINFO_EXTENSION));

    $original = (string) file_get_contents("$dir/$relWin");
    $disk     = (string) file_get_contents("$root/$relWin");

    [$expected] = strip($norm($original), $ext, $ext === 'php');
    $expected = explode("\n", $norm(tidy($expected)));
    $actual   = explode("\n", $norm($disk));

    printf("=== %s ===\n", $rel);

    $n = count($expected);
    $m = count($actual);

    $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) {
        for ($j = $m - 1; $j >= 0; $j--) {
            $lcs[$i][$j] = $expected[$i] === $actual[$j]
                ? $lcs[$i + 1][$j + 1] + 1
                : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
        }
    }

    $i = 0;
    $j = 0;
    $shown = 0;
    while ($i < $n && $j < $m) {
        if ($expected[$i] === $actual[$j]) {
            $i++;
            $j++;
            continue;
        }
        if ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
            printf("  -%4d  %s\n", $i + 1, trim($expected[$i]));
            $i++;
        } else {
            printf("  +      %s\n", trim($actual[$j]));
            $j++;
        }
        $shown++;
    }
    while ($i < $n) {
        printf("  -%4d  %s\n", $i + 1, trim($expected[$i]));
        $i++;
        $shown++;
    }
    while ($j < $m) {
        printf("  +      %s\n", trim($actual[$j]));
        $j++;
        $shown++;
    }

    printf("  (%d differing lines)\n\n", $shown);
}
