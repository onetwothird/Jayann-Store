<?php

$path = dirname(__DIR__) . '/tools/flows.php';
$content = file_get_contents($path);

$replacements = [
    '/admin/dashboard.php' => '/admin/pages/dashboard.php',
    '/admin/admin_login.php' => '/admin/pages/admin_login.php',
    '/admin/products.php' => '/admin/pages/products.php',
    '/admin/update_product.php' => '/admin/pages/update_product.php',
    '/admin/product_archive.php' => '/admin/pages/product_archive.php',
    '/admin/register_admin.php' => '/admin/pages/register_admin.php',
    '/admin/inventory.php' => '/admin/pages/inventory.php',
    '/admin/product_stock.php' => '/admin/pages/product_stock.php',
    '/admin/stock_movements.php' => '/admin/pages/stock_movements.php',
    '/admin/order_view.php' => '/admin/pages/order_view.php',
    '/admin/order_receipt.php' => '/admin/pages/order_receipt.php',
    '/admin/placed_orders.php' => '/admin/pages/placed_orders.php',
    '/admin/messages.php' => '/admin/pages/messages.php',
    '/admin/users_accounts.php' => '/admin/pages/users_accounts.php',
    '/admin/admin_accounts.php' => '/admin/pages/admin_accounts.php',
    '/admin/update_profile.php' => '/admin/pages/update_profile.php',
    '/admin/register_admin.php' => '/admin/pages/register_admin.php',
    '/admin/product_archive.php' => '/admin/pages/product_archive.php',
];

foreach ($replacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

$content = str_replace("str_contains(\$admin->url, 'admin_login.php')", "str_contains(\$admin->url, 'admin/pages/admin_login.php')", $content);
$content = str_replace("str_contains(\$admin->url, 'dashboard.php')", "str_contains(\$admin->url, 'admin/pages/dashboard.php')", $content);

$content = str_replace(
    "'/admin/dashboard.php'",
    "'/admin/pages/dashboard.php'",
    $content
);
$content = str_replace(
    "'/admin/products.php'",
    "'/admin/pages/products.php'",
    $content
);
$content = str_replace(
    "'/admin/placed_orders.php'",
    "'/admin/pages/placed_orders.php'",
    $content
);
$content = str_replace(
    "'/admin/users_accounts.php'",
    "'/admin/pages/users_accounts.php'",
    $content
);
$content = str_replace(
    "'/admin/admin_accounts.php'",
    "'/admin/pages/admin_accounts.php'",
    $content
);
$content = str_replace(
    "'/admin/messages.php'",
    "'/admin/pages/messages.php'",
    $content
);
$content = str_replace(
    "'/admin/update_profile.php'",
    "'/admin/pages/update_profile.php'",
    $content
);
$content = str_replace(
    "'/admin/register_admin.php'",
    "'/admin/pages/register_admin.php'",
    $content
);
$content = str_replace(
    "'/admin/product_archive.php'",
    "'/admin/pages/product_archive.php'",
    $content
);

$content = str_replace(
    "\$shopper->request('/products.php'",
    "\$shopper->request('/public/products.php'",
    $content
);

file_put_contents($path, $content);
echo "Fixed flows.php\n";
