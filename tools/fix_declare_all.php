<?php

$root = dirname(__DIR__);

$files = array_merge(
    glob($root . '/public/*.php'),
    glob($root . '/admin/*.php'),
    glob($root . '/admin/pages/*.php'),
    [$root . '/index.php']
);

foreach ($files as $path) {
    if (!is_file($path)) continue;
    $content = file_get_contents($path);
    if ($content === false) continue;

    $content = str_replace("\xEF\xBB\xBF", '', $content);

    $content = preg_replace('/^(<\?php)\s*\n\s*(declare\(strict_types=1\);)/', '$1' . "\n" . '$2', $content);

    file_put_contents($path, $content);
    echo "FIXED: " . str_replace($root . '/', '', $path) . "\n";
}

echo "Done.\n";
