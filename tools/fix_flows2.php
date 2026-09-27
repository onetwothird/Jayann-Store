<?php

$path = dirname(__DIR__) . '/tools/flows.php';
$content = file_get_contents($path);

$replacements = [

    "'/register.php'" => "'/public/register.php'",
    "'/login.php'" => "'/public/login.php'",
    "'/profile.php'" => "'/public/profile.php'",
    "'/checkout.php'" => "'/public/checkout.php'",
    "'/contact.php'" => "'/public/contact.php'",
    "'/orders.php'" => "'/public/orders.php'",
    "'/receipt.php'" => "'/public/receipt.php'",
];

foreach ($replacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

$content = str_replace("str_contains(\$shopper->url, 'login.php')", "str_contains(\$shopper->url, '/public/login.php')", $content);
$content = str_replace("str_contains(\$shopper->url, 'orders.php')", "str_contains(\$shopper->url, '/public/orders.php')", $content);

file_put_contents($path, $content);
echo "Fixed customer-facing URLs in flows.php\n";
