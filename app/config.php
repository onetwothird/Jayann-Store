<?php

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', ROOT_PATH . '/uploads/products');
}

if (!defined('ASSET_PATH')) {
    define('ASSET_PATH', ROOT_PATH . '/assets');
}

function config(string $key, $default = null)
{
    $env = getenv($key);
    return ($env === false || $env === '') ? $default : $env;
}

return [

    'db' => [
        'host'     => config('JAYANN_DB_HOST', 'localhost'),
        'port'     => config('JAYANN_DB_PORT', '3306'),
        'name'     => config('JAYANN_DB_NAME', 'jayann_store'),
        'user'     => config('JAYANN_DB_USER', 'root'),
        'password' => config('JAYANN_DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
    ],

    'store' => [
        'name'      => "Jayann's Store",
        'legal'     => 'Jayann Store',
        'tagline'   => 'Your one-stop shop',
        'email'     => 'contact@jayannstore.com',
        'phone'     => '+63 938 510 0460',
        'phone_raw' => '09385100460',
        'address'   => 'Sapang I, Ternate, Cavite',
        'hours'     => 'Mon–Sat, 8:00 AM – 8:00 PM',

        'facebook'  => 'https://www.facebook.com/angelo.decatoria.5',
        'instagram' => '',
        'x'         => '',

        'service_area' => 'Cavite, and nearby provinces',
        'returns_days'  => 7,

        'currency'  => '₱',
    ],

    'order' => [

        'shipping_fee' => 50,
        'free_shipping_over' => 1000,
        'max_qty_per_item' => 99,
    ],

    'paymongo' => [
        'secret_key'  => config('PAYMONGO_SECRET_KEY', ''),
        'public_key'  => config('PAYMONGO_PUBLIC_KEY', ''),
        'livemode'    => (bool) config('PAYMONGO_LIVEMODE', false),
        'webhook_secret' => config('PAYMONGO_WEBHOOK_SECRET', ''),

        'session_ttl' => 14400,
    ],
];
