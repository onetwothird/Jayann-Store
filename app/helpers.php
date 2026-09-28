<?php

declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config_value(string $section, string $key, $default = null)
{
    global $config;
    return $config[$section][$key] ?? $default;
}

function peso($amount): string
{
    return number_format((float) $amount, 2);
}

function currency_symbol(): string
{
    global $config;
    return $config['store']['currency'];
}

function money($amount): string
{
    return currency_symbol() . peso($amount);
}

function nice_date(?string $date, bool $withTime = false): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($withTime ? 'd M Y, g:i A' : 'd M Y', $ts) : $date;
}

function json_attr($value): string
{
    return e(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function css_manifest(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $path = dirname(__DIR__) . '/assets/css/manifest.json';
    $raw  = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($raw)) {
        $raw = ['storefront' => ['responsive.css'], 'admin' => ['admin/responsive.css']];
    }
    $cache = $raw;
    return $cache;
}

function css_files(string $bundle): array
{
    $manifest = css_manifest();
    $files    = $manifest[$bundle] ?? [];
    $out      = [];
    foreach ($files as $file) {
        if (is_string($file) && $file !== '') {
            $out[] = BASE_URL . 'assets/css/' . $file;
        }
    }
    return $out;
}

function render_css(string $bundle, string $version = '2.0'): string
{
    $html = '';
    foreach (css_files($bundle) as $file) {
        $html .= '<link rel="stylesheet" href="' . e($file . '?v=' . $version) . '">' . "\n";
    }
    return $html;
}

function redirect(string $url): never
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit;
}

function redirect_if_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        redirect($_SERVER['HTTP_REFERER'] ?? 'home.php');
    }
}

function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['_flash'])) {
        return [];
    }
    $messages = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $messages;
}

function flash_redirect(string $type, string $message, string $url): never
{
    flash($type, $message);
    redirect($url);
}

function boot_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function is_logged_in(): bool
{
    boot_session();
    return !empty($_SESSION['user_id']);
}

function is_admin_logged_in(): bool
{
    boot_session();
    return !empty($_SESSION['admin_id']);
}

function current_user_id(): int
{
    boot_session();
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_admin_id(): int
{
    boot_session();
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function require_login(string $returnTo = ''): void
{
    if (!is_logged_in()) {
        $url = 'login.php';
        if ($returnTo !== '') {
            $url .= '?next=' . urlencode($returnTo);
        }
        redirect($url);
    }
}

function require_admin(string $to = 'admin_login.php'): void
{
    if (!is_admin_logged_in()) {
        redirect($to);
    }
}

function current_user(): ?array
{
    global $db;
    if (!is_logged_in()) {
        return null;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = $db->one('SELECT * FROM users WHERE id = ?', [current_user_id()]) ?? [];
    }
    return $cache ?: null;
}

function current_admin(): ?array
{
    global $db;
    if (!is_admin_logged_in()) {
        return null;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = $db->one('SELECT * FROM admin WHERE id = ?', [current_admin_id()]) ?? [];
    }
    return $cache ?: null;
}

function verify_password(string $plain, string $stored): bool
{
    if ($plain === '' || $stored === '') {
        return false;
    }

    if (str_starts_with($stored, '$')) {
        return password_verify($plain, $stored);
    }

    if (preg_match('/^[a-f0-9]{40}$/i', $stored)) {
        return hash_equals($stored, sha1($plain));
    }

    return hash_equals($stored, $plain);
}

function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

function maybe_upgrade_password_hash(int $userId): void
{
    global $db;
    $stored = (string) $db->value('SELECT password FROM users WHERE id = ?', [$userId]);
    if ($stored !== '' && !str_starts_with($stored, '$')) {

    }
}

function verify_and_rehash(string $table, int $id, string $plain): bool
{
    global $db;

    static $allowed = ['admin', 'users'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('verify_and_rehash(): unknown table ' . $table);
    }

    $stored = (string) $db->value("SELECT password FROM `$table` WHERE id = ?", [$id]);
    if (!verify_password($plain, $stored)) {
        return false;
    }

    $needsUpgrade = !str_starts_with($stored, '$')
        || password_needs_rehash($stored, PASSWORD_DEFAULT);
    if ($needsUpgrade) {
        $db->run("UPDATE `$table` SET password = ? WHERE id = ?", [hash_password($plain), $id]);
    }

    return true;
}

function effective_price(array $product): float
{
    $price = (float) $product['price'];
    $discount = (float) ($product['discount'] ?? 0);

    if ($discount > 0) {
        $discounted = (float) ($product['discount_price'] ?? 0);

        if ($discounted > 0) {
            return $discounted;
        }

        return round($price - ($price * ($discount / 100)), 2);
    }
    return $price;
}

function has_discount(array $product): bool
{
    return (float) ($product['discount'] ?? 0) > 0 && effective_price($product) < (float) $product['price'];
}

function discount_percent(array $product): int
{
    $price = (float) $product['price'];
    if ($price <= 0 || !has_discount($product)) {
        return 0;
    }
    return (int) round((($price - effective_price($product)) / $price) * 100);
}

/**
 * URL for a product photo.
 *
 * Only a bare filename is accepted — no directory separators, no traversal and
 * no scheme — so the value is safe to drop straight into an `src` attribute.
 *
 * The file is deliberately NOT verified with is_file() here. That check made a
 * perfectly reachable image fall back to the placeholder whenever it failed
 * for an unrelated reason (directory permissions, open_basedir, a case
 * mismatch on disk, the database and the folder disagreeing on a name), which
 * is indistinguishable from "the file is missing" on the page. A file that
 * really is absent now 404s and the browser swaps in the placeholder, so the
 * two cases are told apart in the network tab.
 */
function product_image(?string $image, ?string $prefix = null): string
{
    $prefix ??= BASE_URL;
    $image = trim((string) $image);

    // \A and \z, not ^ and $: PCRE lets $ match before a trailing newline, so
    // "photo.png\n" would otherwise slip through the whitelist. The extension
    // is pinned to real image types so a .php name in the database can never
    // become a request for an uploaded script.
    if ($image !== '' && preg_match('#\A[A-Za-z0-9._\[\]\- ]+\.(?:png|jpe?g|gif|webp|avif|svg)\z#i', $image)) {
        $url = $prefix . 'uploads/products/' . rawurlencode($image);

        // The root .htaccess serves images with a 7-day Cache-Control, so
        // replacing a file under the same name keeps showing the old one until
        // the cache expires. Appending the file's mtime busts it. This is only
        // a cache hint: if stat() is unavailable the plain URL is still correct,
        // which is why correctness never depends on it.
        $mtime = @filemtime(UPLOAD_PATH . '/' . $image);
        if ($mtime !== false) {
            $url .= '?v=' . $mtime;
        }

        return $url;
    }

    return $prefix . 'assets/img/placeholder.svg';
}

function cart_items(int $userId): array
{
    global $db;
    if ($userId <= 0) {
        return [];
    }

    return $db->all(
        'SELECT c.id            AS cart_id,
                c.pid           AS pid,
                c.quantity      AS quantity,
                p.name          AS name,
                p.image         AS image,
                p.price         AS price,
                p.discount      AS discount,
                p.discount_price AS discount_price,
                p.stock         AS stock,
                p.category      AS category
         FROM cart c
         INNER JOIN products p ON p.id = c.pid
         WHERE c.user_id = ?
         ORDER BY c.id',
        [$userId]
    );
}

function cart_totals(int $userId): array
{
    global $config;

    $items = cart_items($userId);

    $rows       = [];
    $subtotal   = 0.0;
    $savings    = 0.0;
    $itemCount  = 0;
    $hasIssue   = false;

    foreach ($items as $item) {
        $unit     = effective_price($item);
        $qty      = max(1, (int) $item['quantity']);
        $line     = $unit * $qty;
        $inStock  = (int) $item['stock'] > 0;
        $enough   = $inStock && $qty <= (int) $item['stock'];

        $rows[] = $item + [
            'unit_price'   => $unit,
            'line_total'   => $line,
            'list_price'   => (float) $item['price'],
            'on_sale'      => has_discount($item),
            'save'         => ((float) $item['price'] - $unit) * $qty,
            'in_stock'     => $inStock,
            'stock_ok'     => $enough,
            'problem'      => $enough ? null : ($inStock ? "Only {$item['stock']} left" : 'Out of stock'),
        ];

        $subtotal  += $line;
        $savings   += ((float) $item['price'] - $unit) * $qty;
        $itemCount += $qty;
        $hasIssue   = $hasIssue || !$enough;
    }

    $shipping = ($subtotal > 0 && $subtotal < $config['order']['free_shipping_over'])
        ? (float) $config['order']['shipping_fee']
        : 0.0;

    return [
        'items'         => $rows,
        'subtotal'      => $subtotal,
        'savings'       => $savings,
        'shipping'      => $shipping,
        'total'         => $subtotal + $shipping,
        'item_count'    => $itemCount,
        'line_count'    => count($rows),
        'has_issue'     => $hasIssue,
        'is_empty'      => $rows === [],

        'total_centavos' => (int) round(($subtotal + $shipping) * 100),
    ];
}

function cart_count(int $userId): int
{
    global $db;
    if ($userId <= 0) {
        return 0;
    }
    return (int) $db->value('SELECT COUNT(*) FROM cart WHERE user_id = ?', [$userId]);
}

function product_categories(): array
{
    global $db;
    return $db->all('SELECT DISTINCT category FROM products ORDER BY category');
}

function category_meta(?string $category = null): array
{
    static $map = [
        'Beverages'     => ['icon' => 'fa-martini-glass', 'tone' => 'drinks', 'blurb' => 'Drinks & refreshers'],
        'Essentials'    => ['icon' => 'fa-basket-shopping', 'tone' => 'staples', 'blurb' => 'Everyday staples'],
        'Personal Care' => ['icon' => 'fa-soap', 'tone' => 'care', 'blurb' => 'Hygiene & beauty'],
        'Snacks'        => ['icon' => 'fa-cookie-bite', 'tone' => 'snacks', 'blurb' => 'Chips & treats'],
    ];

    if ($category === null) {
        return $map;
    }

    return $map[$category]
        ?? ['icon' => 'fa-bag-shopping', 'tone' => 'default', 'blurb' => 'Browse range'];
}

function category_icon(?string $category = null): string
{
    $meta = category_meta($category);
    return 'fa-solid ' . $meta['icon'] . ' cats__icon--' . $meta['tone'];
}

function category_blurb(?string $category = null): string
{
    return category_meta($category)['blurb'];
}

function payment_method_label(string $method): string
{
    return match ($method) {
        'cod'              => 'Cash on Delivery',
        'gcash'            => 'GCash',
        'paymaya', 'maya'  => 'Maya',
        'grab_pay'         => 'GrabPay',
        'shopeepay'        => 'ShopeePay',
        'qrph'             => 'QR Ph',
        'card'             => 'Credit / Debit Card',
        'dob'              => 'Online Banking',
        default            => ucwords(str_replace('_', ' ', $method)),
    };
}

function payment_method_icon(string $method): string
{
    return match ($method) {
        'gcash'     => 'fa-mobile-screen',
        'paymaya', 'maya' => 'fa-wallet',
        'grab_pay'  => 'fa-car',
        'shopeepay' => 'fa-bag-shopping',
        'qrph'      => 'fa-qrcode',
        'card'      => 'fa-credit-card',
        'dob'       => 'fa-building-columns',
        default     => 'fa-truck-fast',
    };
}

function order_status_meta(string $status): array
{
    return match ($status) {
        'paid'     => ['class' => 'paid',     'label' => 'Paid'],
        'completed'=> ['class' => 'completed','label' => 'Completed'],
        'failed'   => ['class' => 'failed',   'label' => 'Failed'],
        'cancelled'=> ['class' => 'cancelled','label' => 'Cancelled'],
        default    => ['class' => 'pending',  'label' => 'Pending'],
    };
}

function format_order_items(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }

    if (str_starts_with($raw, '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return implode(', ', array_map(
                static fn($l) => $l['name'] . ' ×' . ($l['quantity'] ?? 1),
                $decoded
            ));
        }
    }

    $parts = array_filter(array_map('trim', explode('-', rtrim($raw, '-'))));
    $out = [];
    foreach ($parts as $part) {
        if (preg_match('/^(.*)\s*\((\d+)\)$/', $part, $m)) {
            $out[] = trim($m[1]) . ' ×' . $m[2];
        } else {
            $out[] = $part;
        }
    }
    return $out ? implode(', ', $out) : $raw;
}

function order_lines(array $order): array
{
    global $db;

    $raw = trim((string) ($order['total_products'] ?? ''));
    if ($raw === '') {
        return [];
    }

    $lines = [];

    if (str_starts_with($raw, '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $entry) {
                if (!is_array($entry) || empty($entry['name'])) {
                    continue;
                }
                $lines[] = [
                    'pid'      => (int) ($entry['pid'] ?? 0),
                    'name'     => (string) $entry['name'],
                    'quantity' => max(1, (int) ($entry['quantity'] ?? 1)),
                    'unit'     => isset($entry['unit']) ? (float) $entry['unit'] : null,
                ];
            }
        }
    }

    if (!$lines) {
        foreach (array_filter(array_map('trim', explode('-', rtrim($raw, '-')))) as $part) {
            if (preg_match('/^(.*?)\s*\((\d+)\)$/u', $part, $m)) {
                $lines[] = ['pid' => 0, 'name' => trim($m[1]), 'quantity' => max(1, (int) $m[2]), 'unit' => null];
            } elseif ($part !== '') {
                $lines[] = ['pid' => 0, 'name' => $part, 'quantity' => 1, 'unit' => null];
            }
        }
    }

    if (!$lines) {
        return [];
    }

    $names = array_column($lines, 'name');
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $images = [];
    foreach ($db->all("SELECT name, image FROM products WHERE name IN ($placeholders)", $names) as $p) {
        $images[(string) $p['name']] = $p['image'];
    }

    foreach ($lines as &$line) {
        $line['image'] = $images[$line['name']] ?? null;
    }
    unset($line);

    return $lines;
}

function available_payment_methods(): array
{
    $methods = [
        [
            'code'  => 'cod',
            'label' => 'Cash on Delivery',
            'note'  => 'Pay the rider in cash when your order arrives.',
            'icon'  => 'fa-money-bill-wave',
        ],
    ];

    if (paymongo_is_enabled()) {
        $notes = [
            'gcash'     => 'Pay with your GCash wallet via PayMongo.',
            'paymaya'   => 'Pay with your Maya wallet via PayMongo.',
            'grab_pay'  => 'Pay with GrabPay via PayMongo.',
            'shopeepay' => 'Pay with ShopeePay via PayMongo.',
            'qrph'      => 'Scan any QR PH app to pay.',
            'card'      => 'Visa or Mastercard, processed securely.',
            'dob'       => 'Pay through your online bank.',
        ];
        foreach (paymongo_payment_methods() as $code) {
            $methods[] = [
                'code'  => $code,
                'label' => payment_method_label($code),
                'note'  => $notes[$code] ?? 'Paid online, confirmed automatically.',
                'icon'  => payment_method_icon($code),
            ];
        }
    }

    return $methods;
}

function is_available_payment_method(string $code): bool
{
    foreach (available_payment_methods() as $m) {
        if ($m['code'] === $code) {
            return true;
        }
    }
    return false;
}

function generate_order_ref(): string
{
    global $db;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $ref = 'JYS-' . date('ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $exists = $db->value('SELECT 1 FROM orders WHERE order_ref = ?', [$ref]);
        if (!$exists) {
            return $ref;
        }
    }
    return 'JYS-' . date('ymd') . '-' . bin2hex(random_bytes(3));
}

function nav_active(string $page, string $current): string
{
    return $page === $current ? ' class="active" aria-current="page"' : '';
}

function plural(int $n, string $singular, string $plural = ''): string
{
    $word = $n === 1 ? $singular : ($plural !== '' ? $plural : $singular . 's');
    return number_format($n) . ' ' . $word;
}
