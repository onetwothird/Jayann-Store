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

/**
 * Site root as the browser sees it, worked out at runtime.
 *
 * The same checkout runs from a Laragon subfolder and from the document root
 * of a shared host, and those need different asset prefixes:
 *
 *   Laragon      http://localhost/Jayann_Store/public/home.php  -> /Jayann_Store/
 *   InfinityFree https://jayann-store.free.nf/public/home.php   -> /
 *
 * Hardcoding either one breaks the other, and a wrong BASE_URL takes every
 * stylesheet, script and product photo down at once — which is exactly what
 * happened. Derive it instead. Set JAYANN_BASE_URL to override by hand.
 */
if (!function_exists('detect_base_url')) {
    function detect_base_url(): string
    {
        $configured = getenv('JAYANN_BASE_URL');
        if ($configured !== false && $configured !== '') {
            return '/' . trim($configured, '/') . '/';
        }

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $appDir = basename(ROOT_PATH);
        $needle = '/' . $appDir . '/';
        $at = strpos($script, $needle);

        return $at === false ? '/' : substr($script, 0, $at + strlen($needle));
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', detect_base_url());
}

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        $env = getenv($key);
        return ($env === false || $env === '') ? $default : $env;
    }
}

/**
 * Which deployment is this request running on?
 *
 * Laragon and InfinityFree need different database credentials, and no single
 * committed value can satisfy both. Rather than ask whoever deploys to
 * remember to create a second config file, the target is worked out from the
 * hostname, so the same checkout runs correctly in both places untouched:
 *
 *   localhost / 127.0.0.1 / *.local   -> the local MySQL in Laragon
 *   anything else                     -> the shared host
 *
 * Set JAYANN_TARGET to 'local' or 'live' to override the guess.
 */
if (!function_exists('deployment_target')) {
    function deployment_target(): string
    {
        $forced = getenv('JAYANN_TARGET');
        if ($forced === 'local' || $forced === 'live') {
            return $forced;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));

        // HTTP_HOST carries an optional port, and the port separator is a colon,
        // which is also what separates the hextets of an IPv6 address. The two
        // shapes have to be told apart or "[::1]:8080" loses its brackets and
        // a bare "::1" loses its last hextet.
        if (str_starts_with($host, '[')) {
            // [::1] or [::1]:8080 — the authority is inside the brackets.
            $close = strpos($host, ']');
            $host = $close === false ? $host : substr($host, 1, $close - 1);
        } elseif (substr_count($host, ':') === 1) {
            // A single colon can only be a port separator.
            $host = (string) strstr($host, ':', true);
        }

        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return 'local';
        }

        // .local and .test are mDNS/hypervisor names used for local development.
        if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return 'local';
        }

        return 'live';
    }
}

/**
 * Database credentials per deployment.
 *
 * The host, database and user are all published in the InfinityFree control
 * panel, so they belong here. The password is the one secret, which is why it
 * sits on its own line below and in its own block: paste it there once and the
 * live site works with no other setup.
 *
 * Leaving it empty costs nothing locally: $dbLocal carries its own empty
 * password and is selected independently, so the Laragon checkout keeps
 * working while the live block waits for the secret. app/bootstrap.php turns
 * the empty live password into a clear message instead of letting MySQL fail
 * with a misleading "Access denied".
 */
$dbLocal = [
    'host'     => 'localhost',
    'port'     => '3306',
    'name'     => 'jayann_store',
    'user'     => 'root',
    'password' => '',
];

$dbLive = [
    'host'     => 'sql308.infinityfree.com',
    'port'     => '3306',
    'name'     => 'if0_43025784_jayann_store',
    'user'     => 'if0_43025784',
    'password' => '', // <-- paste the InfinityFree database password here
];

$db = deployment_target() === 'live' ? $dbLive : $dbLocal;

$config = [

    'db' => [
        // Environment variables still win, so a CI job or a one-off CLI call
        // can point anywhere without editing this file.
        'host'     => config('JAYANN_DB_HOST', $db['host']),
        'port'     => config('JAYANN_DB_PORT', $db['port']),
        'name'     => config('JAYANN_DB_NAME', $db['name']),
        'user'     => config('JAYANN_DB_USER', $db['user']),
        'password' => config('JAYANN_DB_PASSWORD', $db['password']),
        'charset'  => 'utf8mb4',
    ],

    'store' => [
        'name'      => "Jayann's Store",
        'legal'     => 'Jayann Store',
        'tagline'   => 'Your one-stop shop',
        'email'     => 'contact@jayannstore.com',
        'phone'     => '+63 938 510 0460',
        'phone_raw' => '09385100460',
        'address'   => 'Blk 57, Lot 14, Hyacinth Residence',
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

/**
 * Per-machine overrides, merged last so they win over everything above.
 *
 * This is no longer required for either deployment: deployment_target() picks
 * the right database automatically. It remains for the cases that cannot be
 * detected from the hostname, such as preview deploys or a second local
 * database, and is the tidier place to keep a secret you would rather not
 * commit.
 *
 * config.local.php is listed in .gitignore and is blocked from being served
 * over HTTP by the RedirectMatch rule for /app/ in the root .htaccess.
 * See config.local.php.example for the shape.
 */
$localOverrides = __DIR__ . '/config.local.php';
if (is_file($localOverrides)) {
    $overrides = require $localOverrides;
    if (is_array($overrides)) {
        $config = array_replace_recursive($config, $overrides);
    }
}

return $config;
