<?php

$root = dirname(__DIR__);

$files = glob($root . '/admin/pages/*.php');
foreach ($files as $path) {
    $content = file_get_contents($path);
    if ($content === false) continue;

    $content = str_replace("\xEF\xBB\xBF", '', $content);

    $content = preg_replace(
        '/^(<\?php\s*\n\s*declare\(strict_types=1\);)\s*\n\s*\/\*\*/',
        '$1' . "\n\n/**",
        $content
    );

    file_put_contents($path, $content);
    echo "FIXED: " . basename($path) . "\n";
}

echo "Done.\n";
