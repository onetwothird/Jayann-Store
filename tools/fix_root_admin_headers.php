<?php

$root = dirname(__DIR__);
$files = ['admin/index.php', 'admin/logout.php', 'index.php'];

foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) continue;

    $content = file_get_contents($path);
    if ($content === false) continue;

    $content = str_replace("\xEF\xBB\xBF", '', $content);

    $content = preg_replace(
        '/^(<\?php\s*\n\s*declare\(strict_types=1\);)\s*\n\s*\/\*\*/',
        '$1' . "\n\n/**",
        $content
    );

    file_put_contents($path, $content);
    echo "FIXED: $rel\n";
}

echo "Done.\n";
