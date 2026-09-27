<?php
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://localhost/Jayann_Store', '/');

$expect = [
    '/'                        => [200, 'storefront root redirects to home'],
    '/index.php'               => [200, 'redirect shim to public/home.php'],
    '/public/home.php'         => [200, 'home'],
    '/public/products.php'     => [200, 'all products, no filter'],
    '/public/products.php?page=2'     => [200, 'pagination past the end'],
    '/public/products.php?sort=price_desc' => [200, 'sort + page combo'],
    '/public/products.php?q=soap'     => [200, 'search inside the catalogue'],
    '/public/products.php?filter=sale' => [200, 'sale filter'],
    '/public/products.php?filter=out' => [200, 'out-of-stock filter'],
    '/public/products.php?filter=low' => [200, 'low-stock filter'],
    '/public/discounted_products.php' => [200, 'on-sale listing'],
    '/discounted%20products.php'      => [200, 'legacy URL shim'],
    '/public/category.php'            => [200, 'category page, no filter'],
    '/public/category.php?category=Beverages' => [200, 'category page'],
    '/public/search.php'              => [200, 'search, no query'],
    '/public/search.php?q=soap'       => [200, 'search with a query'],
    '/public/search.php?q=%20'        => [200, 'search, blank query'],
    '/public/quick_view.php?pid=8'    => [200, 'product page'],
    '/public/quick_view.php?pid=99999' => [404, 'missing product'],
    '/public/quick_view.php'          => [404, 'no id at all'],
    '/public/quick_view.php?partial=1&pid=3' => [200, 'quick-view modal fragment'],
    '/public/quick_view.php?partial=1&pid=99999' => [404, 'modal fragment, missing product'],
    '/public/cart.php'                => [200, 'cart (redirects to login when logged out)'],
    '/public/checkout.php'            => [200, 'checkout (redirects to login when logged out)'],
    '/public/orders.php'              => [200, 'orders (redirects to login when logged out)'],
    '/public/receipt.php'             => [200, 'receipt (redirects to login when logged out)'],
    '/public/receipt.php?order=1'     => [200, 'receipt, arbitrary order'],
    '/public/download_receipt.php'    => [400, 'no order given'],
    '/public/download_receipt.php?order=1' => [200, 'receipt download, ownership enforced'],
    '/public/login.php'               => [200, 'sign in'],
    '/public/register.php'            => [200, 'sign up'],
    '/public/profile.php'             => [200, 'profile (redirects to login when logged out)'],
    '/public/update_profile.php'      => [200, 'profile editor'],
    '/public/update_address.php'      => [200, 'address editor'],
    '/public/about.php'               => [200, 'about'],
    '/public/contact.php'             => [200, 'contact'],
    '/assets/css/style.css'           => [200, 'storefront stylesheet'],
    '/assets/css/admin_style.css'     => [200, 'admin stylesheet'],
    '/assets/js/script.js'            => [200, 'storefront script'],
    '/assets/js/admin_script.js'      => [200, 'admin script'],
    '/assets/img/storenijayann.png'   => [200, 'logo'],
    '/assets/img/placeholder.svg'     => [200, 'placeholder art'],
    '/app/config.php'     => [404, 'app code is not web-accessible'],
    '/app/helpers.php'    => [404, 'app code is not web-accessible'],
    '/app/bootstrap.php'  => [404, 'app code is not web-accessible'],
    '/database/schema.sql' => [403, 'the schema is not web-accessible'],
    '/database/upgrade.sql' => [403, 'migrations are not web-accessible'],
    '/admin/index.php'               => [200, 'admin entry point'],
    '/admin/pages/admin_login.php'   => [200, 'admin sign in'],
    '/admin/pages/dashboard.php'     => [200, 'dashboard (redirects to login)'],
    '/admin/pages/products.php'      => [200, 'products (redirects to login)'],
    '/admin/pages/update_product.php?id=1' => [200, 'product editor (redirects to login)'],
    '/admin/pages/placed_orders.php' => [200, 'orders (redirects to login)'],
    '/admin/pages/order_view.php?id=1' => [200, 'order detail (redirects to login)'],
    '/admin/pages/order_receipt.php?id=1' => [200, 'printable order sheet'],
    '/admin/pages/product_archive.php' => [200, 'archive'],
    '/admin/pages/users_accounts.php' => [200, 'customers'],
    '/admin/pages/admin_accounts.php' => [200, 'admins'],
    '/admin/pages/messages.php'      => [200, 'messages'],
    '/admin/pages/update_profile.php' => [200, 'admin profile'],
    '/admin/pages/register_admin.php' => [200, 'add administrator'],
    '/admin/logout.php'              => [200, 'admin sign out'],
];

$needle = '/(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught|Access denied)/i';

$fail   = 0;
$passed = 0;

foreach ($expect as $path => [$want, $note]) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body     = (string) curl_exec($ch);
    $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type     = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $problems = [];

    if ($code !== $want) {
        $problems[] = sprintf('got %d, expected %d', $code, $want);
    }

    if (str_contains($type, 'text/html')) {
        if (preg_match($needle, $body, $m)) {
            $problems[] = 'PHP diagnostic: ' . preg_replace('/\s+/', ' ', $m[0]);
        }
        if (preg_match('/(Fatal error|Parse error|Warning|Notice|Deprecated|Uncaught)[^<]{0,260}/', $body, $mm)) {
            $problems[] = '  at ' . trim(preg_replace('/\s+/', ' ', $mm[0]));
        }
    }

    if ($problems) {
        $fail++;
        printf("FAIL  %-42s %s\n", $path, $note);
        foreach ($problems as $p) {
            echo '        ' . $p . "\n";
        }
    } else {
        $passed++;
        printf("ok    %-42s %3d  %s\n", $path, $code, $note);
    }
}

echo "\n";
if ($fail === 0) {
    echo "All $passed checks passed.\n";
    exit(0);
}

echo "$fail of " . count($expect) . " checks failed.\n";
exit(1);
