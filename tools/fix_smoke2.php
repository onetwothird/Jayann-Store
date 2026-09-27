<?php

$path = dirname(__DIR__) . '/tools/smoke.php';
$content = file_get_contents($path);

$replacements = [

    "'/admin/'" => "'/admin/index.php'",
    "'/admin/index.php'" => "'/admin/index.php'",
    "'/admin/admin_login.php'" => "'/admin/pages/admin_login.php'",
    "'/admin/dashboard.php'" => "'/admin/pages/dashboard.php'",
    "'/admin/products.php'" => "'/admin/pages/products.php'",
    "'/admin/update_product.php?id=1'" => "'/admin/pages/update_product.php?id=1'",
    "'/admin/placed_orders.php'" => "'/admin/pages/placed_orders.php'",
    "'/admin/order_view.php?id=1'" => "'/admin/pages/order_view.php?id=1'",
    "'/admin/order_receipt.php?id=1'" => "'/admin/pages/order_receipt.php?id=1'",
    "'/admin/product_archive.php'" => "'/admin/pages/product_archive.php'",
    "'/admin/users_accounts.php'" => "'/admin/pages/users_accounts.php'",
    "'/admin/admin_accounts.php'" => "'/admin/pages/admin_accounts.php'",
    "'/admin/messages.php'" => "'/admin/pages/messages.php'",
    "'/admin/update_profile.php'" => "'/admin/pages/update_profile.php'",
    "'/admin/register_admin.php'" => "'/admin/pages/register_admin.php'",
    "'/admin/logout.php'" => "'/admin/logout.php'",

    "'/logout.php'" => "'/public/logout.php'",
    "'/payment.php'" => "'/public/payment.php'",
];

foreach ($replacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

$content = str_replace(
    "'/'                        => [200, 'storefront root']",
    "'/'                        => [302, 'storefront root redirects to home']",
    $content
);
$content = str_replace(
    "'/index.php'               => [200, 'redirect shim']",
    "'/index.php'               => [302, 'redirect shim to public/home.php']",
    $content
);

file_put_contents($path, $content);
echo "Fixed smoke.php admin URLs\n";
