<?php

$root = dirname(__DIR__);
$publicFiles = glob($root . '/public/*.php');

$fixed = 0;
foreach ($publicFiles as $path) {
    $content = file_get_contents($path);
    if ($content === false) continue;

    if (strlen($content) >= 3 && ord($content[0]) === 0xEF && ord($content[1]) === 0xBB && ord($content[2]) === 0xBF) {
        $content = substr($content, 3);
    }

    if (preg_match('/^(\s*\/\*\*.*?\*\/\s*\n)\s*(<\?php\s*\n)/s', $content, $m)) {
        $newContent = $m[2] . "\n" . $m[1] . substr($content, strlen($m[0]));
        file_put_contents($path, $newContent);
        echo "FIXED: " . basename($path) . "\n";
        $fixed++;
    }
}

echo "\nFixed: $fixed public files\n";
