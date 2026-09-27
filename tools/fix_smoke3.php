<?php

$path = dirname(__DIR__) . '/tools/smoke.php';
$content = file_get_contents($path);

$content = str_replace(
    "'/public/discounted%20products.php' => [200, 'legacy URL shim']",
    "'/discounted%20products.php' => [200, 'legacy URL shim']",
    $content
);

file_put_contents($path, $content);
echo "Fixed\n";