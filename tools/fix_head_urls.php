<?php

$path = dirname(__DIR__) . '/app/views/layout/head.php';
$content = file_get_contents($path);

$replacements = [

    'src="assets/img/storenijayann.png"' => 'src="/Jayann_Store/assets/img/storenijayann.png"',
    'href="home.php"' => 'href="/Jayann_Store/public/home.php"',
    'href="search.php"' => 'href="/Jayann_Store/public/search.php"',
    'href="cart.php"' => 'href="/Jayann_Store/public/cart.php"',
    'href="profile.php"' => 'href="/Jayann_Store/public/profile.php"',
    'href="orders.php"' => 'href="/Jayann_Store/public/orders.php"',
    'href="logout.php"' => 'href="/Jayann_Store/public/logout.php"',
    'href="login.php"' => 'href="/Jayann_Store/public/login.php"',
    'href="register.php"' => 'href="/Jayann_Store/public/register.php"',
    'href="products.php"' => 'href="/Jayann_Store/public/products.php"',
    'href="category.php' => 'href="/Jayann_Store/public/category.php',
    'href="discounted_products.php"' => 'href="/Jayann_Store/public/discounted_products.php"',
    'href="cart.php"' => 'href="/Jayann_Store/public/cart.php"',
];

foreach ($replacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

file_put_contents($path, $content);
echo "Fixed head.php\n";
