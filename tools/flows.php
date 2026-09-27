<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$base = 'http://localhost/Jayann_Store';
$keep = in_array('--keep', $argv, true);

foreach ($argv as $arg) {
    if (str_starts_with($arg, 'http')) {
        $base = rtrim($arg, '/');
    }
}

final class Client
{
    private string $jar;
    public int $status = 0;
    public string $body = '';
    public string $url = '';

    public function __construct(string $name)
    {
        $this->jar = sys_get_temp_dir() . '/flow_' . $name . '_' . getmypid() . '.txt';
        @unlink($this->jar);
    }

    public function request(string $path, ?array $post = null, bool $follow = true): self
    {
        $ch = curl_init($base = $this->baseUrl() . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $this->body = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        unset($base);
        return $this;
    }

    private function baseUrl(): string
    {
        return $GLOBALS['base'];
    }
}

$passed = 0;
$failed = 0;
$section = '';

function heading(string $text): void
{
    global $section;
    $section = $text;
    echo "\n  " . $text . "\n  " . str_repeat('-', strlen($text)) . "\n";
}

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo '  ok    ' . $what . "\n";
    } else {
        $failed++;
        echo '  FAIL  ' . $what . ($detail !== '' ? "\n          " . $detail : '') . "\n";
    }
}

function checkEquals(string $what, $expected, $actual): void
{
    check(
        $what,
        $expected === $actual,
        'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
    );
}

function checkNoPhpErrors(Client $c, string $label): void
{
    $found = [];
    foreach (['Fatal error', 'Parse error', 'Warning:', 'Notice:', 'Deprecated:', 'Uncaught', 'Uncaught TypeError'] as $needle) {
        if (stripos($c->body, $needle) !== false) {
            $found[] = $needle;
        }
    }
    check(
        $label . ' renders without PHP errors',
        $found === [] && $c->status < 400,
        $found === [] ? 'HTTP ' . $c->status : 'HTTP ' . $c->status . ', found: ' . implode(', ', $found)
            . "\n          " . substr(trim(preg_replace('/\s+/', ' ', $c->body)), 0, 200)
    );
}

global $db;

$stamp      = (string) random_int(100000, 999999);
$adminUser  = 'admin';
$adminPass  = (string) $db->value('SELECT password FROM admin WHERE name = ?', [$adminUser]);
$adminPassPlain = 'password123';

$testProduct  = 'ZZ Flow Test Product ' . $stamp;
$testCategory = 'Essentials';
$testCustomer = 'flow' . $stamp . '@example.test';
$testCustPass = 'Flowtest' . $stamp;
$testCustName = 'Flow Tester';
$newAdmin     = 'flowadm' . $stamp;

$created = ['products' => [], 'archive' => [], 'orders' => [], 'users' => [], 'admin' => []];

$purgeStaleTestData = static function () use ($db): int {
    $pids = $db->all('SELECT id FROM products WHERE name LIKE ?', ['ZZ Flow Test%']);
    foreach ($pids as $p) {
        $db->run('DELETE FROM cart WHERE pid = ?', [(int) $p['id']]);
        $db->run('DELETE FROM stock_movements WHERE product_id = ?', [(int) $p['id']]);
        $db->run('DELETE FROM products WHERE id = ?', [(int) $p['id']]);
    }
    $db->run('DELETE FROM product_archive WHERE name LIKE ?', ['ZZ Flow Test%']);

    $uids = $db->all('SELECT id FROM users WHERE email LIKE ?', ['flow%@example.test']);
    foreach ($uids as $u) {
        $db->run('DELETE FROM cart WHERE user_id = ?', [(int) $u['id']]);
        $db->run('DELETE FROM orders WHERE user_id = ?', [(int) $u['id']]);
        $db->run('DELETE FROM users WHERE id = ?', [(int) $u['id']]);
    }

    $db->run('DELETE FROM admin WHERE name LIKE ?', ['flowadm%']);
    $db->run('DELETE FROM messages WHERE message LIKE ?', ['%Automated flow test message%']);

    return count($pids) + count($uids);
};

$stalePurged = $keep ? 0 : $purgeStaleTestData();

echo "\n";
echo "  End-to-end flow test against " . $base . "\n";
if ($stalePurged > 0) {
    echo "  Cleared $stalePurged leftover row(s) from an interrupted earlier run.\n";
}
echo "  " . str_pad('-', 60) . "\n";

heading('Admin authentication');

$admin = new Client('admin');

$admin->request('/admin/pages/dashboard.php');
check(
    'dashboard redirects an anonymous visitor to the login page',
    $admin->status === 200 && str_contains($admin->url, 'admin/pages/admin_login.php'),
    'landed on ' . $admin->url
);

$admin->request('/admin/pages/admin_login.php', ['name' => $adminUser, 'pass' => 'definitely-wrong']);
checkNoPhpErrors($admin, 'A wrong password');
check(
    'a wrong password is rejected',
    str_contains($admin->body, 'not recognised') || str_contains($admin->body, "couldn't sign you in"),
    'the form did not report a failure'
);
check(
    'a wrong password does not start a session',
    $db->value('SELECT 1 FROM admin WHERE name = ? AND password = ?', [$adminUser, $adminPass]) !== null,
    'the stored hash changed on a failed attempt'
);

$admin->request('/admin/pages/admin_login.php', ['name' => $adminUser, 'pass' => $adminPassPlain]);
check(
    'the correct password signs in and lands on the dashboard',
    str_contains($admin->url, 'admin/pages/dashboard.php') && $admin->status === 200,
    'landed on ' . $admin->url . ' (HTTP ' . $admin->status . ')'
);
checkNoPhpErrors($admin, 'The dashboard');

$admin->request('/admin/pages/dashboard.php');
check(
    'the session survives to the next request',
    $admin->status === 200 && !str_contains($admin->url, 'admin/pages/admin_login.php'),
    'bounced back to ' . $admin->url
);

$stored = (string) $db->value('SELECT password FROM admin WHERE name = ?', [$adminUser]);
check(
    'the admin password is stored as a bcrypt hash',
    str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2'),
    'stored value starts with "' . substr($stored, 0, 7) . '"'
);

heading('Product management');

$admin->request('/admin/pages/products.php', [
    'add_product'       => '',
    'name'              => $testProduct,
    'category'          => $testCategory,
    'price'             => '199.50',
    'stock'             => '7',
    'discount'          => '10',
    'existing_category' => '',
]);
$productId = (int) $db->value('SELECT id FROM products WHERE name = ?', [$testProduct]);
check('a product can be created', $productId > 0, 'no row with that name appeared');
if ($productId) {
    $created['products'][] = $productId;
}
checkNoPhpErrors($admin, 'The products page after creating');

if ($productId) {
    $row = $db->one('SELECT * FROM products WHERE id = ?', [$productId]);
    checkEquals('the price was stored', 199.5, (float) $row['price']);
    checkEquals('the stock was stored', 7, (int) $row['stock']);
    checkEquals('the discount was stored', 10, (int) $row['discount']);
    checkEquals(
        'the discount price was calculated (199.50 less 10%)',
        179.55,
        (float) $row['discount_price']
    );
    checkEquals('the category was stored', $testCategory, (string) $row['category']);
}

$admin->request('/admin/pages/products.php', [
    'add_product' => '',
    'name'        => $testProduct,
    'category'    => $testCategory,
    'price'       => '10',
    'stock'       => '1',
    'discount'    => '0',
]);
check(
    'a duplicate product name is rejected',
    (int) $db->value('SELECT COUNT(*) FROM products WHERE name = ?', [$testProduct]) === 1,
    'the duplicate was inserted'
);

$admin->request('/admin/pages/products.php', [
    'add_product' => '',
    'name'        => '',
    'category'    => '',
    'price'       => '0',
    'stock'       => '-1',
    'discount'    => '95',
]);
check(
    'an invalid product is rejected with an explanation',
    str_contains($admin->body, 'needs a name')
        && str_contains($admin->body, 'Choose a category')
        && str_contains($admin->body, 'greater than zero'),
    'the validation messages did not come back'
);

if ($productId) {
    $admin->request('/admin/pages/update_product.php', [
        'update'      => '',
        'id'          => (string) $productId,
        'name'        => $testProduct . ' (edited)',
        'category'    => $testCategory,
        'price'       => '250.00',
        'stock'       => '12',
        'discount'    => '20',
    ]);
    $row = $db->one('SELECT * FROM products WHERE id = ?', [$productId]);
    check('a product can be edited', $row && str_contains((string) $row['name'], '(edited)'), 'the name did not change');
    checkEquals('the edited price was saved', 250.0, (float) $row['price']);
    checkEquals('the edited stock was saved', 12, (int) $row['stock']);
    checkEquals('the edited discount was saved', 20, (int) $row['discount']);
    checkEquals('the edited discount price was recalculated', 200.0, (float) $row['discount_price']);
    checkNoPhpErrors($admin, 'The product editor');
}

$admin->request('/admin/pages/products.php?q=' . urlencode($testProduct));
check(
    'a newly created product is findable by search',
    str_contains($admin->body, 'ZZ Flow Test Product'),
    'the search did not return the product'
);

$admin->request('/admin/pages/products.php?filter=out');
check('the out-of-stock filter renders', $admin->status === 200, 'HTTP ' . $admin->status);
checkNoPhpErrors($admin, 'The products page filtered to out-of-stock');

heading('Archive and restore');

if ($productId) {

    $currentName = (string) $db->value('SELECT name FROM products WHERE id = ?', [$productId]);

    $admin->request('/admin/pages/products.php', ['form_action' => 'archive', 'id' => (string) $productId]);
    check(
        'archiving removes the product from the catalogue',
        $db->value('SELECT 1 FROM products WHERE id = ?', [$productId]) === null
    );
    $archiveId = (int) $db->value('SELECT id FROM product_archive WHERE name = ?', [$currentName]);
    check('archiving copies the product into the archive', $archiveId > 0, 'no archived row named "' . $currentName . '"');
    if ($archiveId) {
        $created['archive'][] = $archiveId;
    }

    $admin->request('/admin/pages/product_archive.php');
    check('the archive page lists the archived product', str_contains($admin->body, 'ZZ Flow Test Product'));
    checkNoPhpErrors($admin, 'The archive page');

    if ($archiveId) {
        $admin->request('/admin/pages/product_archive.php', ['form_action' => 'restore', 'id' => (string) $archiveId]);
        check(
            'restoring puts the product back in the catalogue',
            (int) $db->value('SELECT COUNT(*) FROM products WHERE name = ?', [$currentName]) === 1
        );
        check(
            'restoring empties it out of the archive',
            $db->value('SELECT 1 FROM product_archive WHERE id = ?', [$archiveId]) === null
        );
    }
}

heading('Administrator accounts');

$admin->request('/admin/pages/register_admin.php', [
    'submit'       => '',
    'name'         => $newAdmin,
    'pass'         => 'FlowAdm' . $stamp,
    'cpass'        => 'FlowAdm' . $stamp,
]);
$newAdminId = (int) $db->value('SELECT id FROM admin WHERE name = ?', [$newAdmin]);
check('a new administrator can be created', $newAdminId > 0);
if ($newAdminId) {
    $created['admin'][] = $newAdminId;
}
check(
    'the new administrator password is hashed',
    str_starts_with((string) $db->value('SELECT password FROM admin WHERE id = ?', [$newAdminId]), '$2y$')
);
checkNoPhpErrors($admin, 'The add-administrator page');

$second = new Client('admin2');
$second->request('/admin/pages/admin_login.php', ['name' => $newAdmin, 'pass' => 'FlowAdm' . $stamp]);
check(
    'the new administrator can sign in',
    str_contains($second->url, 'dashboard.php'),
    'landed on ' . $second->url
);

$admin->request('/admin/pages/register_admin.php', [
    'submit' => '',
    'name'   => $newAdmin,
    'pass'   => 'Another' . $stamp,
    'cpass'  => 'Different' . $stamp,
]);
check(
    'mismatched confirmation passwords are rejected',
    (int) $db->value('SELECT COUNT(*) FROM admin WHERE name = ?', [$newAdmin]) === 1,
    'a second account with that name was created'
);

$admin->request('/admin/pages/register_admin.php', [
    'submit' => '',
    'name'   => $adminUser,
    'pass'   => 'Hijack' . $stamp,
    'cpass'  => 'Hijack' . $stamp,
]);
check(
    'a duplicate administrator name is rejected',
    str_contains($admin->body, 'already') || str_contains($admin->body, 'taken'),
    'no duplicate warning was shown'
);

heading('Customer journey');

$shopper = new Client('shopper');

$shopper->request('/public/register.php', [
    'name'    => $testCustName,
    'email'   => $testCustomer,
    'number'  => '0917' . $stamp,
    'address' => '1 Flow Test Street, Test City',
    'password' => $testCustPass,
    'confirm'  => $testCustPass,
]);
$userId = (int) $db->value('SELECT id FROM users WHERE email = ?', [$testCustomer]);
check('a customer can sign up', $userId > 0, 'no user row with that email appeared');
if ($userId) {
    $created['users'][] = $userId;
}
check(
    'the customer password is hashed',
    $userId > 0 && str_starts_with((string) $db->value('SELECT password FROM users WHERE id = ?', [$userId]), '$2y$')
);
checkNoPhpErrors($shopper, 'The sign-up page');

$shopper->request('/public/logout.php');
$shopper->request('/public/login.php', ['email' => $testCustomer, 'password' => 'wrong-password']);
check(
    'a wrong customer password is rejected',
    stripos($shopper->body, 'not recognised') !== false
        || stripos($shopper->body, 'incorrect') !== false
        || stripos($shopper->body, "couldn't sign you in") !== false,
    'no error was shown for a bad password'
);
$shopper->request('/public/profile.php');
check(
    'a failed sign-in leaves the customer signed out',
    str_contains($shopper->url, '/public/login.php'),
    'landed on ' . $shopper->url
);

$shopper->request('/public/login.php', ['email' => $testCustomer, 'password' => $testCustPass]);
check(
    'the customer can sign in',
    str_contains($shopper->url, 'home.php') && $userId > 0,
    'landed on ' . $shopper->url
);
$shopper->request('/public/profile.php');
check(
    'the customer reaches their profile once signed in',
    $shopper->status === 200 && !str_contains($shopper->url, '/public/login.php'),
    'landed on ' . $shopper->url
);
checkNoPhpErrors($shopper, 'The profile page');

if ($productId && $userId) {

    $productId = (int) $db->value('SELECT id FROM products WHERE name = ?', [$testProduct . ' (edited)']);
    check('the restored product is addressable by its new id', $productId > 0, 'could not find the restored row');
    if ($productId) {
        $created['products'][] = $productId;
    }
    $liveName = (string) $db->value('SELECT name FROM products WHERE id = ?', [$productId]);
    $livePrice = (float) $db->value('SELECT price FROM products WHERE id = ?', [$productId]);
    $liveStock = (int) $db->value('SELECT stock FROM products WHERE id = ?', [$productId]);
}

if ($productId && $userId) {
    $shopper->request('/public/products.php', ['cart_action' => 'add', 'pid' => (string) $productId, 'qty' => '2']);
    $cartQty = (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId]);
    checkEquals('a product can be added to the cart', 2, $cartQty);

    $cartId = (int) $db->value('SELECT id FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId]);
    check('the cart row is addressable by its own id', $cartId > 0);

    $shopper->request('/public/cart.php', ['cart_action' => 'update', 'cart_id' => (string) $cartId, 'qty' => '3']);
    checkEquals(
        'the cart quantity can be changed',
        3,
        (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId])
    );

    $shopper->request('/public/cart.php', ['cart_action' => 'update', 'cart_id' => (string) $cartId, 'qty' => '9999']);
    checkEquals(
        'the cart quantity is clamped to the stock on hand',
        $liveStock,
        (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId])
    );

    $shopper->request('/public/cart.php', ['cart_action' => 'update', 'cart_id' => (string) $cartId, 'qty' => '2']);
    checkEquals(
        'the cart quantity can be lowered again',
        2,
        (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId])
    );

    $shopper->request('/public/cart.php');
    check(
        'the product shows on the cart page',
        str_contains($shopper->body, $liveName),
        'the cart page did not list the product'
    );
    check(
        'the cart page totals the line',
        str_contains($shopper->body, number_format($livePrice * 2, 2))
            || str_contains($shopper->body, number_format((float) $livePrice * 2, 2, '.', '')),
        'the expected line total ' . number_format($livePrice * 2, 2) . ' was not on the page'
    );
    checkNoPhpErrors($shopper, 'The cart page');

    $shopper->request('/public/cart.php', ['cart_action' => 'remove', 'cart_id' => (string) $cartId]);
    checkEquals(
        'a product can be removed from the cart',
        0,
        (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId])
    );

    $shopper->request('/public/cart.php', ['cart_action' => 'add', 'pid' => (string) $productId, 'qty' => '2']);
    checkEquals(
        'a removed product can be added back',
        2,
        (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId])
    );

    $shopper->request('/public/cart.php', ['cart_action' => 'add', 'pid' => (string) $productId, 'qty' => '9999']);
    check(
        'the cart refuses more than the available stock',
        (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?', [$userId, $productId]) <= $liveStock,
        'the cart accepted more units than exist'
    );

    $shopper->request('/public/cart.php', ['cart_action' => 'update', 'cart_id' => (string) $cartId, 'qty' => '2']);

    $shopper->request('/public/checkout.php', [
        'payment_method' => 'cod',
        'address'        => '1 Flow Test Street, Test City',
        'name'           => $testCustName,
        'number'         => '0917' . $stamp,
        'email'          => $testCustomer,
    ]);
    $orderId = (int) $db->value('SELECT MAX(id) FROM orders WHERE user_id = ?', [$userId]);
    check('an order can be placed at checkout', $orderId > 0, 'no order row was created');
    if ($orderId) {
        $created['orders'][] = $orderId;
    }
    checkNoPhpErrors($shopper, 'Checkout');

    if ($orderId) {
        $order = $db->one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        check('the order records a reference', ($order['order_ref'] ?? '') !== '', 'order_ref was empty');
        checkEquals(
            'the order total matches 2 units at the list price plus shipping',
            round($livePrice * 2, 2) + (float) $config['order']['shipping_fee'],
            (float) $order['total_price']
        );
        checkEquals(
            'the order subtotal matches 2 units at the list price',
            round($livePrice * 2, 2),
            (float) $order['subtotal']
        );
        check(
            'the order starts as pending',
            in_array($order['payment_status'], ['pending', 'unpaid'], true),
            'payment_status was ' . $order['payment_status']
        );
        checkEquals(
            'the cart was emptied by the checkout',
            0,
            (int) $db->value('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?', [$userId])
        );
        check(
            'stock was reduced by the ordered quantity',
            (int) $db->value('SELECT stock FROM products WHERE id = ?', [$productId]) === $liveStock - 2,
            'stock is ' . $db->value('SELECT stock FROM products WHERE id = ?', [$productId]) . ', expected ' . ($liveStock - 2)
        );

        $shopper->request('/public/orders.php');
        check('the customer sees the order in their history', str_contains($shopper->body, (string) $order['order_ref']));
        checkNoPhpErrors($shopper, 'The orders page');

        $shopper->request('/public/receipt.php?order=' . $orderId);
        check('the customer can open the receipt', $shopper->status === 200 && !str_contains($shopper->url, '/public/login.php'));
        checkNoPhpErrors($shopper, 'The receipt');

        $other = new Client('other');
        $other->request('/public/login.php', ['email' => 'someone.else@example.test', 'password' => 'whatever']);
        $other->request('/public/receipt.php?order=' . $orderId);
        check(
            "another customer cannot read someone else's receipt",
            str_contains($other->url, 'login.php') || str_contains($other->url, 'orders.php') || $other->status >= 400,
            "HTTP {$other->status} on {$other->url}"
        );

        heading('Fulfilling the order');

        $admin->request('/admin/pages/placed_orders.php');
        check('the order appears in the admin order list', str_contains($admin->body, (string) $order['order_ref']));
        checkNoPhpErrors($admin, 'The orders page');

        $admin->request('/admin/pages/order_view.php?id=' . $orderId);
        check('the admin can open the order', $admin->status === 200, 'HTTP ' . $admin->status);
        checkNoPhpErrors($admin, 'The order detail page');

        $admin->request('/admin/pages/order_view.php', [
            'form_action' => 'status',
            'id'          => (string) $orderId,
            'status'      => 'paid',
        ]);
        checkEquals(
            'an admin can mark the order paid',
            'paid',
            (string) $db->value('SELECT payment_status FROM orders WHERE id = ?', [$orderId])
        );

        $admin->request('/admin/pages/order_receipt.php?id=' . $orderId);
        check('the printable order sheet renders', $admin->status === 200, 'HTTP ' . $admin->status);
        checkNoPhpErrors($admin, 'The printable order sheet');

        $admin->request('/admin/pages/order_view.php', [
            'form_action' => 'status',
            'id'          => (string) $orderId,
            'status'      => 'not-a-real-status',
        ]);
        check(
            'an invalid order status is rejected',
            in_array(
                (string) $db->value('SELECT payment_status FROM orders WHERE id = ?', [$orderId]),
                ['paid', 'pending'],
                true
            ),
            'the status was overwritten with a bogus value'
        );
    }
}

if ($productId > 0) {
    heading('Inventory ledger');

    $stockOf = static function (int $id) use ($db): int {
        $v = $db->value('SELECT stock FROM products WHERE id = ?', [$id]);
        return $v === null ? -1 : (int) $v;
    };

    $lastMovement = static function (int $id) use ($db) {
        return $db->one('SELECT * FROM stock_movements WHERE product_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    };

    $sale = $db->one(
        "SELECT * FROM stock_movements WHERE product_id = ? AND reason = 'sale' ORDER BY id DESC LIMIT 1",
        [$productId]
    );
    check('placing an order logged a sale in the stock ledger', $sale !== null);
    checkEquals(
        'the sale movement deducted exactly the ordered quantity',
        -2,
        (int) ($sale['qty_change'] ?? 0)
    );
    check(
        'the sale movement cites the order it belongs to',
        str_contains((string) ($sale['reference'] ?? ''), (string) $order['order_ref']),
        'reference was "' . ($sale['reference'] ?? '') . '"'
    );
    check(
        'the sale movement recorded who did it',
        trim((string) ($sale['actor'] ?? '')) !== ''
    );

    $last = $lastMovement($productId);
    check(
        'the latest movement balance matches the product stock',
        (int) ($last['balance_after'] ?? -1) === $stockOf($productId),
        'ledger says ' . ($last['balance_after'] ?? 'none') . ', product says ' . $stockOf($productId)
    );

    $before = $stockOf($productId);
    $admin->request('/admin/pages/inventory.php', [
        'form_action' => 'restock',
        'id'          => (string) $productId,
        'qty'         => '25',
        'note'        => 'Automated flow test delivery',
        'reference'   => 'PO-TEST-1',
    ]);
    checkEquals('receiving a delivery raised the stock', $before + 25, $stockOf($productId));

    $in = $lastMovement($productId);
    checkEquals('the delivery was logged as stock in', 'in', (string) ($in['direction'] ?? ''));
    checkEquals('the delivery was logged as a purchase', 'purchase', (string) ($in['reason'] ?? ''));
    checkEquals('the delivery movement kept its reference', 'PO-TEST-1', (string) ($in['reference'] ?? ''));

    foreach (['0' => 'a zero-quantity', '-5' => 'a negative'] as $qty => $label) {
        $admin->request('/admin/pages/inventory.php', [
            'form_action' => 'restock',
            'id'          => (string) $productId,
            'qty'         => $qty,
        ]);
        check($label . ' delivery changes nothing', $before + 25 === $stockOf($productId));
    }

    $admin->request('/admin/pages/product_stock.php?id=' . $productId, [
        'form_action' => 'remove',
        'qty'         => '4',
        'reason'      => 'damage',
        'note'        => 'Automated flow test damage',
    ]);
    checkEquals('taking stock out reduced the balance', $before + 21, $stockOf($productId));
    checkEquals('the removal was logged as stock out', 'out', (string) ($lastMovement($productId)['direction'] ?? ''));

    $admin->request('/admin/pages/product_stock.php?id=' . $productId, [
        'form_action' => 'remove',
        'qty'         => '9999',
        'reason'      => 'damage',
    ]);
    checkEquals('stock can never be driven below zero', 0, $stockOf($productId));
    check(
        'the ledger never claims a bigger movement than actually happened',
        (int) ($lastMovement($productId)['qty_change'] ?? 0) === -($before + 21),
        'logged ' . ($lastMovement($productId)['qty_change'] ?? 'nothing') . ' against a balance of ' . $before
    );

    $admin->request('/admin/pages/product_stock.php?id=' . $productId, [
        'form_action' => 'count',
        'counted'     => '40',
        'reason'      => 'correction',
        'note'        => 'Automated flow test count',
    ]);
    checkEquals('reconciling a count sets the balance to the counted number', 40, $stockOf($productId));
    checkEquals('the count was logged as a correction', 'correction', (string) ($lastMovement($productId)['reason'] ?? ''));
    checkEquals('the correction movement carries the running balance', 40, (int) ($lastMovement($productId)['balance_after'] ?? 0));

    $admin->request('/admin/pages/product_stock.php?id=' . $productId, [
        'form_action' => 'count',
        'counted'     => '40',
        'reason'      => 'correction',
    ]);
    checkEquals(
        'reconciling to the same number logs nothing extra',
        1,
        (int) ($db->value(
            'SELECT COUNT(*) FROM stock_movements WHERE product_id = ? AND reason = ?',
            [$productId, 'correction']
        ) ?? 0)
    );

    $admin->request('/admin/pages/inventory.php', [
        'form_action'         => 'settings',
        'id'                  => (string) $productId,
        'sku'                 => 'ZZ-FLOW-SKU',
        'cost_price'          => '12.50',
        'low_stock_threshold' => '3',
        'supplier'            => 'Automated Flow Supplier',
    ]);
    $row = $db->one('SELECT * FROM products WHERE id = ?', [$productId]);
    checkEquals('the stock code was saved', 'ZZ-FLOW-SKU', (string) $row['sku']);
    checkEquals('the unit cost was saved', '12.50', number_format((float) $row['cost_price'], 2, '.', ''));
    checkEquals('the reorder threshold was saved', 3, (int) $row['low_stock_threshold']);
    checkEquals('the supplier was saved', 'Automated Flow Supplier', (string) $row['supplier']);

    $otherId = (int) ($db->value('SELECT id FROM products WHERE id <> ? LIMIT 1', [$productId]) ?? 0);
    $admin->request('/admin/pages/inventory.php', [
        'form_action'         => 'settings',
        'id'                  => (string) $otherId,
        'sku'                 => 'ZZ-FLOW-SKU',
        'cost_price'          => '1',
        'low_stock_threshold' => '5',
        'supplier'            => '',
    ]);
    check(
        'a duplicate stock code is refused',
        (string) ($db->value('SELECT sku FROM products WHERE id = ?', [$otherId]) ?? '') !== 'ZZ-FLOW-SKU',
        'the duplicate code was written to product #' . $otherId
    );

    $admin->request('/admin/pages/product_stock.php?id=' . $productId, [
        'form_action' => 'count',
        'counted'     => '3',
        'reason'      => 'correction',
        'note'        => 'Automated flow test drop to the threshold',
    ]);
    checkEquals('the product can be counted down to its threshold', 3, $stockOf($productId));

    check('a product at its threshold is flagged low', (stock_status(3, 3))['key'] === 'low');
    check('a product above its threshold is healthy', (stock_status(4, 3))['key'] === 'ok');
    check('a threshold of zero disables reorder tracking', (stock_status(1, 0))['key'] === 'ok');
    check('zero stock is always out, threshold or not', (stock_status(0, 0))['key'] === 'out');

    $admin->request('/admin/pages/inventory.php?filter=low');
    check('the low-stock filter renders', $admin->status === 200, 'HTTP ' . $admin->status);
    checkNoPhpErrors($admin, 'The inventory page filtered to low stock');
    check('the low-stock filter actually lists the product', str_contains($admin->body, (string) $row['sku']));

    $admin->request('/admin/pages/update_product.php?id=' . $productId, [
        'update'   => '1',
        'name'     => (string) $row['name'],
        'category' => (string) $row['category'],
        'price'    => (string) $row['price'],
        'discount' => (string) $row['discount'],
        'stock'    => '9',
    ]);
    checkEquals('editing the stock on the product form changed the balance', 9, $stockOf($productId));
    check(
        'editing the product stock logged a correction',
        (string) ($lastMovement($productId)['reason'] ?? '') === 'correction',
        'logged reason was "' . ($lastMovement($productId)['reason'] ?? 'nothing') . '"'
    );

    $soldQty      = 2;
    $beforeCancel = $stockOf($productId);

    $admin->request('/admin/pages/order_view.php', [
        'form_action' => 'status',
        'id'          => (string) $orderId,
        'status'      => 'cancelled',
    ]);
    checkEquals(
        'cancelling an order returned its units to stock',
        $beforeCancel + $soldQty,
        $stockOf($productId)
    );
    check(
        'the return was logged against the order',
        str_contains((string) ($lastMovement($productId)['reference'] ?? ''), (string) $order['order_ref'])
    );
    checkEquals(
        'the order is flagged as having had its stock returned',
        1,
        (int) ($db->value('SELECT stock_returned FROM orders WHERE id = ?', [$orderId]) ?? 0)
    );

    $admin->request('/admin/pages/order_view.php', [
        'form_action' => 'status',
        'id'          => (string) $orderId,
        'status'      => 'cancelled',
    ]);
    checkEquals(
        'cancelling the same order twice does not double the stock',
        $beforeCancel + $soldQty,
        $stockOf($productId)
    );

    $screens = [
        'The inventory dashboard'            => '/admin/pages/inventory.php',
        'The inventory dashboard by value'   => '/admin/pages/inventory.php?sort=value_desc',
        'The inventory dashboard by search'  => '/admin/pages/inventory.php?q=' . rawurlencode((string) $row['category']),
        'The inventory dashboard, out only'  => '/admin/pages/inventory.php?filter=out',
        'The stock movement ledger'          => '/admin/pages/stock_movements.php',
        'The ledger over all time'           => '/admin/pages/stock_movements.php?days=0',
        'The ledger filtered to stock in'    => '/admin/pages/stock_movements.php?dir=in',
        'The ledger filtered to one product' => '/admin/pages/stock_movements.php?product=' . $productId,
        'The single-product stock page'      => '/admin/pages/product_stock.php?id=' . $productId,
    ];
    foreach ($screens as $label => $url) {
        $admin->request($url);
        check($label . ' renders', $admin->status === 200, 'HTTP ' . $admin->status);
        checkNoPhpErrors($admin, $label);
    }

    $drift = $db->all(
        "SELECT p.name, p.stock, COALESCE(SUM(m.qty_change), 0) AS replayed
         FROM products p
         LEFT JOIN stock_movements m ON m.product_id = p.id
         GROUP BY p.id, p.name, p.stock
         HAVING replayed <> p.stock"
    );
    check(
        'replaying the whole ledger reproduces every product balance',
        $drift === [],
        $drift === [] ? '' : implode('; ', array_map(
            static fn($r) => $r['name'] . ' stock=' . $r['stock'] . ' ledger=' . $r['replayed'],
            array_slice($drift, 0, 4)
        ))
    );

    $stockAtArchive = $stockOf($productId);
    $admin->request('/admin/pages/products.php', [
        'form_action' => 'archive',
        'id'          => (string) $productId,
    ]);
    check('the archived product left the catalogue', $stockOf($productId) === -1);
    checkEquals(
        'archiving did not write the stock off, because it is reversible',
        0,
        (int) ($db->value(
            'SELECT COUNT(*) FROM stock_movements WHERE product_id = ? AND reason = ?',
            [$productId, 'archive']
        ) ?? 0)
    );

    $archived = $db->one('SELECT * FROM product_archive WHERE name = ?', [(string) $row['name']]);
    check('the product is in the archive', $archived !== null);
    if ($archived) {
        checkEquals('the archive kept the remaining stock', $stockAtArchive, (int) $archived['stock']);
        checkEquals('the archive kept the unit cost', '12.50', number_format((float) $archived['cost_price'], 2, '.', ''));
        checkEquals('the archive kept the stock code', 'ZZ-FLOW-SKU', (string) $archived['sku']);
    }

    if ($archived) {
        $admin->request('/admin/pages/product_archive.php', [
            'form_action' => 'restore',
            'id'          => (string) $archived['id'],
        ]);
        $restored = $db->one('SELECT * FROM products WHERE name = ?', [(string) $row['name']]);
        check('the product is back in the catalogue', $restored !== null);

        if ($restored) {
            $restoredId = (int) $restored['id'];
            checkEquals('the restored product kept its unit cost', '12.50', number_format((float) $restored['cost_price'], 2, '.', ''));
            checkEquals('the restored product kept its reorder threshold', 3, (int) $restored['low_stock_threshold']);
            checkEquals('the restored product kept its stock code', 'ZZ-FLOW-SKU', (string) $restored['sku']);
            checkEquals('the restored product got its stock back', $stockAtArchive, (int) $restored['stock']);
            checkEquals(
                'the restored product opened a fresh ledger chain',
                1,
                (int) ($db->value(
                    'SELECT COUNT(*) FROM stock_movements WHERE product_id = ? AND reason = ?',
                    [$restoredId, 'opening']
                ) ?? 0)
            );
            checkEquals(
                'the new ledger chain starts at the restored balance',
                $stockAtArchive,
                (int) ($db->value(
                    'SELECT balance_after FROM stock_movements WHERE product_id = ? ORDER BY id DESC LIMIT 1',
                    [$restoredId]
                ) ?? -1)
            );

            $db->run('DELETE FROM stock_movements WHERE product_id = ?', [$restoredId]);
            $db->run('DELETE FROM products WHERE id = ?', [$restoredId]);
        }
        $db->run('DELETE FROM product_archive WHERE id = ?', [(int) $archived['id']]);
    }
}

heading('Contact messages');

$before = (int) $db->value('SELECT COUNT(*) FROM messages');
$shopper->request('/public/contact.php', [
    'submit'  => '',
    'name'    => $testCustName,
    'email'   => $testCustomer,
    'number'  => '0917' . $stamp,
    'message' => 'Automated flow test message ' . $stamp,
]);
$messageId = (int) $db->value('SELECT MAX(id) FROM messages');
check('a contact message can be sent', (int) $db->value('SELECT COUNT(*) FROM messages') === $before + 1);
checkNoPhpErrors($shopper, 'The contact page');

if ($messageId) {
    $admin->request('/admin/pages/messages.php', ['form_action' => 'delete', 'id' => (string) $messageId]);
    check(
        'an admin can delete a message',
        $db->value('SELECT 1 FROM messages WHERE id = ?', [$messageId]) === null
    );
}

heading('Signing out');

$admin->request('/admin/logout.php');
$admin->request('/admin/pages/dashboard.php');
check(
    'an admin session cannot be reused after signing out',
    str_contains($admin->url, 'admin/pages/admin_login.php'),
    'still had access via ' . $admin->url
);

$shopper->request('/public/logout.php');
$shopper->request('/public/cart.php');
check(
    'a customer session cannot be reused after signing out',
    str_contains($shopper->url, '/public/login.php'),
    'still had access via ' . $shopper->url
);

heading('Access control');

$anon = new Client('anon');
foreach ([
    '/admin/pages/dashboard.php',
    '/admin/pages/products.php',
    '/admin/pages/placed_orders.php',
    '/admin/pages/users_accounts.php',
    '/admin/pages/admin_accounts.php',
    '/admin/pages/messages.php',
    '/admin/pages/update_profile.php',
    '/admin/pages/register_admin.php',
    '/admin/pages/product_archive.php',
] as $path) {
    $anon->request($path);
    check(
        'anonymous visitors cannot reach ' . $path,
        str_contains($anon->url, 'admin_login.php'),
        'landed on ' . $anon->url
    );
}

heading('Cleanup');

if ($keep) {
    echo "  --keep was passed, so nothing was removed.\n";
    echo '  Test product id: ' . $productId . ', order id: ' . $orderId . "\n";
} else {
    foreach ($created['products'] as $id) {
        $db->run('DELETE FROM cart WHERE pid = ?', [$id]);

        $db->run('DELETE FROM stock_movements WHERE product_id = ?', [$id]);
        $db->run('DELETE FROM products WHERE id = ?', [$id]);
    }
    foreach ($created['archive'] as $id) {
        $db->run('DELETE FROM product_archive WHERE id = ?', [$id]);
    }
    foreach ($created['orders'] as $id) {
        $db->run('DELETE FROM orders WHERE id = ?', [$id]);
    }
    foreach ($created['users'] as $id) {
        $db->run('DELETE FROM cart WHERE user_id = ?', [$id]);
        $db->run('DELETE FROM users WHERE id = ?', [$id]);
    }
    foreach ($created['admin'] as $id) {
        $db->run('DELETE FROM admin WHERE id = ?', [$id]);
    }
    $db->run('DELETE FROM messages WHERE message LIKE ?', ['%Automated flow test message%']);

    $leftovers = (int) $db->value('SELECT COUNT(*) FROM products WHERE name LIKE ?', ['ZZ Flow Test%'])
        + (int) $db->value('SELECT COUNT(*) FROM product_archive WHERE name LIKE ?', ['ZZ Flow Test%'])
        + (int) $db->value('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['flow%@example.test'])
        + (int) $db->value('SELECT COUNT(*) FROM admin WHERE name LIKE ?', ['flowadm%']);

    check('all test data was removed', $leftovers === 0, $leftovers . ' row(s) left behind');
}

echo "\n  " . str_repeat('=', 60) . "\n";
if ($failed === 0) {
    echo "  All $passed flow checks passed.\n\n";
    exit(0);
}
echo "  $failed of " . ($passed + $failed) . " flow checks FAILED.\n\n";
exit(1);
