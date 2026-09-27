<?php

$root = dirname(__DIR__);

$fileData = [
    'about.php' => [
        'header' => "feat(store): add the about page\n * Static content page describing the store, mission, and service area.",
        'code_start' => 'include'
    ],
    'cart.php' => [
        'header' => "feat(cart): add the cart page with live quantity updates\n * Displays cart contents, allows qty changes, shows subtotal, and proceeds to checkout.",
        'code_start' => 'include'
    ],
    'category.php' => [
        'header' => "feat(store): add the per-category listing page\n * Filters products by category, uses shared catalog component with paging and sorting.",
        'code_start' => 'include'
    ],
    'checkout.php' => [
        'header' => "feat(checkout): add checkout that logs a sale movement per line\n * Collects shipping info, creates order, decrements stock via inventory ledger.",
        'code_start' => 'include'
    ],
    'contact.php' => [
        'header' => "feat(store): add the contact page and message form\n * Customer-facing contact form that stores messages in the database.",
        'code_start' => 'include'
    ],
    'discounted_products.php' => [
        'header' => "feat(store): add the sale page listing every discounted product\n * Filters catalogue to only products with discount > 0, with sorting and paging.",
        'code_start' => 'include'
    ],
    'download_receipt.php' => [
        'header' => "feat(orders): add the receipt download endpoint\n * Generates PDF receipt via FPDF for a given order, forces download.",
        'code_start' => 'include'
    ],
    'home.php' => [
        'header' => "feat(store): add the home page with hero, categories and featured products\n * Hero carousel, category tiles, featured products grid, sale strip, and three-step explainer.",
        'code_start' => 'include'
    ],
    'login.php' => [
        'header' => "feat(auth): add customer login\n * Email/password login with remember-me, redirects to intended page or home.",
        'code_start' => 'include'
    ],
    'logout.php' => [
        'header' => "feat(auth): add the shared logout endpoint\n * Destroys session and redirects to home page.",
        'code_start' => 'include'
    ],
    'orders.php' => [
        'header' => "feat(orders): add the customer order history and tracking\n * Lists authenticated user's orders with status badges and links to receipt.",
        'code_start' => 'include'
    ],
    'payment.php' => [
        'header' => "feat(payments): add the payment step and its unpaid order state\n * Placeholder for Paymongo integration; marks order as pending payment.",
        'code_start' => 'include'
    ],
    'products.php' => [
        'header' => "feat(store): add the full product catalogue with filters and sorting\n * Category, price, availability, discount filters; multi-column grid; paging.",
        'code_start' => 'include'
    ],
    'profile.php' => [
        'header' => "feat(account): add the customer profile and address\n * Shows user info, allows editing name/email/phone, manages delivery address.",
        'code_start' => 'include'
    ],
    'quick_view.php' => [
        'header' => "feat(store): add the quick view product fragment\n * AJAX endpoint returning product modal markup for the home/catalog quick view.",
        'code_start' => 'include'
    ],
    'receipt.php' => [
        'header' => "feat(orders): add the customer receipt view\n * HTML receipt for a completed order with line items, totals, and delivery info.",
        'code_start' => 'include'
    ],
    'register.php' => [
        'header' => "feat(auth): add customer signup\n * Registration form with validation, creates user account and logs in.",
        'code_start' => 'include'
    ],
    'search.php' => [
        'header' => "feat(store): add search results with sorting and paging\n * Keyword search across name/category, shared catalog component, empty-state chips.",
        'code_start' => 'include'
    ],
    'update_address.php' => [
        'header' => "feat(account): add the delivery address update handler\n * Validates and persists address fields for the authenticated user.",
        'code_start' => 'include'
    ],
    'update_profile.php' => [
        'header' => "feat(account): add the profile update handler\n * Validates and persists name, email, phone for the authenticated user.",
        'code_start' => 'include'
    ],
];

foreach ($fileData as $base => $data) {
    $path = $root . '/public/' . $base;
    $content = file_get_contents($path);
    if ($content === false) {
        echo "MISSING: $base\n";
        continue;
    }

    if (strlen($content) >= 3 && ord($content[0]) === 0xEF && ord($content[1]) === 0xBB && ord($content[2]) === 0xBF) {
        $content = substr($content, 3);
    }

    $code = preg_replace('/\/\*.*?\*\//s', '', $content);

    $code = preg_replace('/<\?php|\?>/', '', $code);

    $code = trim($code);

    $header = "/**\n * " . $data['header'] . "\n */\n\n";
    $newContent = '<?php' . "\n\n" . $header . 'declare(strict_types=1);' . "\n\n" . $code;

    file_put_contents($path, $newContent);
    echo "REBUILT: $base\n";
}

echo "\nDone.\n";
