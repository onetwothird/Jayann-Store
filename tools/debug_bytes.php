<?php

$path = "C:/xampp/htdocs/Jayann_Store/public/home.php";
$content = file_get_contents($path);
$pos = strpos($content, 'include');
if ($pos !== false) {
    echo "Found at $pos\n";
    echo substr($content, $pos-30, 80) . "\n";
    echo "---\n";

    $bytes = array_slice(unpack('C*', $content), $pos-30, 80);
    echo implode(' ', $bytes) . "\n";
}
