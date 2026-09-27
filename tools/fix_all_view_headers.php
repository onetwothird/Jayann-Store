<?php

$root = dirname(__DIR__);

$viewDirs = [
    'app/views/layout',
    'app/views/admin',
    'app/views/shop',
];

foreach ($viewDirs as $dir) {
    $files = glob($root . '/' . $dir . '/*.php');
    foreach ($files as $path) {
        $content = file_get_contents($path);
        if ($content === false) continue;

        $content = preg_replace(
            '/^(\s*\/\*\*.*?\*\/\s*\n)\s*(<\?php\s*\n\s*declare\(strict_types=1\);)/s',
            '$2' . "\n\n" . '$1',
            $content
        );

        file_put_contents($path, $content);
        echo "FIXED: " . str_replace($root . '/', '', $path) . "\n";
    }
}

echo "\nDone.\n";
