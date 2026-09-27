<?php

$path = dirname(__DIR__) . '/tools/smoke.php';
$content = file_get_contents($path);

$replacements = [

    "'/home.php'" => "'/public/home.php'",
    "'/products.php'" => "'/public/products.php'",
    "'/products.php?page=2'" => "'/public/products.php?page=2'",
    "'/products.php?sort=price_desc'" => "'/public/products.php?sort=price_desc'",
    "'/products.php?q=soap'" => "'/public/products.php?q=soap'",
    "'/products.php?filter=sale'" => "'/public/products.php?filter=sale'",
    "'/products.php?filter=out'" => "'/public/products.php?filter=out'",
    "'/products.php?filter=low'" => "'/public/products.php?filter=low'",
    "'/discounted_products.php'" => "'/public/discounted_products.php'",
    "'/discounted%20products.php'" => "'/public/discounted%20products.php'",
    "'/category.php'" => "'/public/category.php'",
    "'/category.php?category=Beverages'" => "'/public/category.php?category=Beverages'",
    "'/search.php'" => "'/public/search.php'",
    "'/search.php?q=soap'" => "'/public/search.php?q=soap'",
    "'/search.php?q=%20'" => "'/public/search.php?q=%20'",
    "'/quick_view.php?pid=8'" => "'/public/quick_view.php?pid=8'",
    "'/quick_view.php?pid=99999'" => "'/public/quick_view.php?pid=99999'",
    "'/quick_view.php'" => "'/public/quick_view.php'",
    "'/quick_view.php?partial=1&pid=3'" => "'/public/quick_view.php?partial=1&pid=3'",
    "'/quick_view.php?partial=1&pid=99999'" => "'/public/quick_view.php?partial=1&pid=99999'",
    "'/cart.php'" => "'/public/cart.php'",
    "'/checkout.php'" => "'/public/checkout.php'",
    "'/orders.php'" => "'/public/orders.php'",
    "'/receipt.php'" => "'/public/receipt.php'",
    "'/receipt.php?order=1'" => "'/public/receipt.php?order=1'",
    "'/download_receipt.php'" => "'/public/download_receipt.php'",
    "'/download_receipt.php?order=1'" => "'/public/download_receipt.php?order=1'",
    "'/login.php'" => "'/public/login.php'",
    "'/register.php'" => "'/public/register.php'",
    "'/profile.php'" => "'/public/profile.php'",
    "'/update_profile.php'" => "'/public/update_profile.php'",
    "'/update_address.php'" => "'/public/update_address.php'",
    "'/about.php'" => "'/public/about.php'",
    "'/contact.php'" => "'/public/contact.php'",
];

foreach ($replacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

file_put_contents($path, $content);
echo "Fixed smoke.php URLs\n";
