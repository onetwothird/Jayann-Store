<?php

$root = dirname(__DIR__);

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if (!$file->isFile()) continue;
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, ['php', 'js', 'css', 'sql'])) continue;
    if (str_contains($file->getPathname(), 'libs' . DIRECTORY_SEPARATOR)) continue;

    $content = file_get_contents($file->getPathname());
    if ($content === false) continue;

    if (!preg_match('/^\s*\/\*\*/', $content)) continue;

    if ($ext === 'php') {
        if (preg_match('/^\s*\/\*\*.*?\*\/\s*\n\s*(declare\(strict_types=1\);)/s', $content, $m)) {

            continue;
        }
        if (preg_match('/^(\s*\/\*\*.*?\*\/\s*\n)\s*(declare\(strict_types=1\);)/s', $content, $m)) {

            $newContent = $m[2] . "\n\n" . $m[1] . substr($content, strlen($m[0]));
            file_put_contents($file->getPathname(), $newContent);
            echo "FIXED (strict_types): " . str_replace($root . '/', '', $file->getPathname()) . "\n";
        }
    }
}

echo "\nDone.\n";
