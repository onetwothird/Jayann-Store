<?php

$root = dirname(__DIR__);

$files = glob($root . '/public/*.php');
foreach ($files as $path) {
    $content = file_get_contents($path);
    if ($content === false) continue;

    $content = str_replace("\xEF\xBB\xBF", '', $content);

    $content = preg_replace('/^(<\?php)\s*\n\s*(declare\(strict_types=1\);)/', '$1' . "\n" . '$2', $content);

    file_put_contents($path, $content);
    echo "FIXED: " . basename($path) . "\n";
}

echo "Done.\n";
