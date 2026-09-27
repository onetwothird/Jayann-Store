<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dir  = $argv[1] ?? '';

ob_start();
require __DIR__ . '/strip_comments.php';
ob_end_clean();

if (!function_exists('strip')) {
    fwrite(STDERR, "could not load strip()\n");
    exit(1);
}

if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "usage: php tools/strip_damage_check.php <dir>\n");
    exit(1);
}

$damaged = 0;
$changed = 0;
$clean   = 0;

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }

    $rel = substr($f->getPathname(), strlen(rtrim($dir, '/\\')) + 1);
    $ext = strtolower($f->getExtension());

    if (!in_array($ext, ['php', 'css', 'js'], true)) {
        continue;
    }

    $original = (string) file_get_contents($f->getPathname());

    if (!is_file("$root/$rel")) {
        printf("  MISSING           %-38s was in the original, gone now\n", $rel);
        $damaged++;
        continue;
    }

    $norm = static fn (string $s): string => str_replace("\r\n", "\n", $s);

    [$expected] = strip($norm($original), $ext, $ext === 'php');
    $expected = $norm(tidy($expected));
    $actual   = $norm((string) file_get_contents("$root/$rel"));

    if ($expected === $actual) {
        $clean++;
        continue;
    }

    [, $stillThere] = strip($actual, $ext, $ext === 'php');

    if ($stillThere > 0) {
        printf(
            "  STRIP-INCOMPLETE  %-38s %d comments still present\n",
            $rel,
            $stillThere
        );
        $damaged++;
        continue;
    }

    printf("  DIFFERS           %-38s (edited since the strip - review)\n", $rel);
    $changed++;
}

printf(
    "\n%d clean, %d differ, %d incomplete, %d files checked\n",
    $clean,
    $changed,
    $damaged,
    $clean + $changed + $damaged
);

exit($damaged > 0 ? 1 : 0);
