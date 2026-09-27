<?php

$root = dirname(__DIR__);
$publicFiles = glob($root . '/public/*.php');

$headers = [
    'about.php' => "feat(store): add the about page\n * Static content page describing the store, mission, and service area.",
    'cart.php' => "feat(cart): add the cart page with live quantity updates\n * Displays cart contents, allows qty changes, shows subtotal, and proceeds to checkout.",
    'category.php' => "feat(store): add the per-category listing page\n * Filters products by category, uses shared catalog component with paging and sorting.",
    'checkout.php' => "feat(checkout): add checkout that logs a sale movement per line\n * Collects shipping info, creates order, decrements stock via inventory ledger.",
    'contact.php' => "feat(store): add the contact page and message form\n * Customer-facing contact form that stores messages in the database.",
    'discounted_products.php' => "feat(store): add the sale page listing every discounted product\n * Filters catalogue to only products with discount > 0, with sorting and paging.",
    'download_receipt.php' => "feat(orders): add the receipt download endpoint\n * Generates PDF receipt via FPDF for a given order, forces download.",
    'home.php' => "feat(store): add the home page with hero, categories and featured products\n * Hero carousel, category tiles, featured products grid, sale strip, and three-step explainer.",
    'login.php' => "feat(auth): add customer login\n * Email/password login with remember-me, redirects to intended page or home.",
    'logout.php' => "feat(auth): add the shared logout endpoint\n * Destroys session and redirects to home page.",
    'orders.php' => "feat(orders): add the customer order history and tracking\n * Lists authenticated user's orders with status badges and links to receipt.",
    'payment.php' => "feat(payments): add the payment step and its unpaid order state\n * Placeholder for Paymongo integration; marks order as pending payment.",
    'products.php' => "feat(store): add the full product catalogue with filters and sorting\n * Category, price, availability, discount filters; multi-column grid; paging.",
    'profile.php' => "feat(account): add the customer profile and address\n * Shows user info, allows editing name/email/phone, manages delivery address.",
    'quick_view.php' => "feat(store): add the quick view product fragment\n * AJAX endpoint returning product modal markup for the home/catalog quick view.",
    'receipt.php' => "feat(orders): add the customer receipt view\n * HTML receipt for a completed order with line items, totals, and delivery info.",
    'register.php' => "feat(auth): add customer signup\n * Registration form with validation, creates user account and logs in.",
    'search.php' => "feat(store): add search results with sorting and paging\n * Keyword search across name/category, shared catalog component, empty-state chips.",
    'update_address.php' => "feat(account): add the delivery address update handler\n * Validates and persists address fields for the authenticated user.",
    'update_profile.php' => "feat(account): add the profile update handler\n * Validates and persists name, email, phone for the authenticated user.",
];

foreach ($publicFiles as $path) {
    $base = basename($path);
    if (!isset($headers[$base])) continue;

    $content = file_get_contents($path);
    if ($content === false) continue;

    if (strlen($content) >= 3 && ord($content[0]) === 0xEF && ord($content[1]) === 0xBB && ord($content[2]) === 0xBF) {
        $content = substr($content, 3);
    }

    $content = preg_replace('/^(?:(?:\s*\/\*\*.*?\*\/\s*)|(?:\s*<\?php\s*)|(?:\s*\?>\s*)|(?:\s*declare\(strict_types=1\);))*+/si', '', $content);

    $content = ltrim($content);
    if (!str_starts_with($content, '<?php')) {
        $content = '<?php' . "\n" . $content;
    }

    $header = "/**\n * " . $headers[$base] . "\n */\n\n";
    $content = preg_replace('/^(<\?php\s*\n)/', '$1' . $header, $content);

    if (!preg_match('/declare\(strict_types=1\);/', $content)) {
        $content = preg_replace('/^(<\?php\s*\n\s*\/\*\*.*?\*\/\s*\n)/s', '$1' . 'declare(strict_types=1);' . "\n\n", $content);
    }

    file_put_contents($path, $content);
    echo "REBUILT: $base\n";
}

echo "\nDone.\n";
