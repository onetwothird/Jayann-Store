<?php

$root = dirname(__DIR__);

$headers = [

    'index.php' => "feat(store): redirect site root to the home page\n * Entry point — sends all traffic to public/home.php via bootstrap.",

    'public/about.php' => "feat(store): add the about page\n * Static content page describing the store, mission, and service area.",
    'public/cart.php' => "feat(cart): add the cart page with live quantity updates\n * Displays cart contents, allows qty changes, shows subtotal, and proceeds to checkout.",
    'public/category.php' => "feat(store): add the per-category listing page\n * Filters products by category, uses shared catalog component with paging and sorting.",
    'public/checkout.php' => "feat(checkout): add checkout that logs a sale movement per line\n * Collects shipping info, creates order, decrements stock via inventory ledger.",
    'public/contact.php' => "feat(store): add the contact page and message form\n * Customer-facing contact form that stores messages in the database.",
    'public/discounted_products.php' => "feat(store): add the sale page listing every discounted product\n * Filters catalogue to only products with discount > 0, with sorting and paging.",
    'public/download_receipt.php' => "feat(orders): add the receipt download endpoint\n * Generates PDF receipt via FPDF for a given order, forces download.",
    'public/home.php' => "feat(store): add the home page with hero, categories and featured products\n * Hero carousel, category tiles, featured products grid, sale strip, and three-step explainer.",
    'public/login.php' => "feat(auth): add customer login\n * Email/password login with remember-me, redirects to intended page or home.",
    'public/logout.php' => "feat(auth): add the shared logout endpoint\n * Destroys session and redirects to home page.",
    'public/orders.php' => "feat(orders): add the customer order history and tracking\n * Lists authenticated user's orders with status badges and links to receipt.",
    'public/payment.php' => "feat(payments): add the payment step and its unpaid order state\n * Placeholder for Paymongo integration; marks order as pending payment.",
    'public/products.php' => "feat(store): add the full product catalogue with filters and sorting\n * Category, price, availability, discount filters; multi-column grid; paging.",
    'public/profile.php' => "feat(account): add the customer profile and address\n * Shows user info, allows editing name/email/phone, manages delivery address.",
    'public/quick_view.php' => "feat(store): add the quick view product fragment\n * AJAX endpoint returning product modal markup for the home/catalog quick view.",
    'public/receipt.php' => "feat(orders): add the customer receipt view\n * HTML receipt for a completed order with line items, totals, and delivery info.",
    'public/register.php' => "feat(auth): add customer signup\n * Registration form with validation, creates user account and logs in.",
    'public/search.php' => "feat(store): add search results with sorting and paging\n * Keyword search across name/category, shared catalog component, empty-state chips.",
    'public/update_address.php' => "feat(account): add the delivery address update handler\n * Validates and persists address fields for the authenticated user.",
    'public/update_profile.php' => "feat(account): add the profile update handler\n * Validates and persists name, email, phone for the authenticated user.",

    'admin/index.php' => "refactor(admin): route the admin root to the dashboard\n * Requires admin auth, then redirects to admin/pages/dashboard.php.",
    'admin/logout.php' => "feat(admin): add the admin logout endpoint\n * Destroys admin session and redirects to admin login page.",

    'admin/pages/admin_accounts.php' => "feat(admin): add admin account management\n * Lists admins, allows adding/editing/deleting admin users with role handling.",
    'admin/pages/admin_login.php' => "feat(admin): add the admin login\n * Username/password login with bcrypt verification, sets admin session.",
    'admin/pages/dashboard.php' => "feat(admin): add the dashboard with sales, order and stock KPIs\n * Revenue cards, recent orders table, low-stock reorder list, quick actions.",
    'admin/pages/inventory.php' => "feat(inventory): add the stock adjustment screen\n * Search products, adjust stock by delta or absolute, logs movement with reason.",
    'admin/pages/messages.php' => "feat(admin): add the contact message inbox\n * Lists customer messages with read/unread status, mark-as-read, delete.",
    'admin/pages/order_receipt.php' => "feat(admin): add the printable order receipt\n * Server-rendered receipt for printing, mirrors customer receipt with admin details.",
    'admin/pages/order_view.php' => "feat(admin): add the order detail screen with cancel and restock\n * Shows order lines, status timeline, cancel button that restores stock via ledger.",
    'admin/pages/placed_orders.php' => "feat(admin): add the order list with status filtering\n * Paginated, filterable table of all orders with status badges and actions.",
    'admin/pages/products.php' => "feat(admin): add the product list with inline quick edit\n * Searchable, sortable table; inline edit of price/discount/stock; quick actions.",
    'admin/pages/product_archive.php' => "feat(admin): add the product archive with reversible restore\n * Soft-deleted products list; restore or permanently purge with ledger write-off.",
    'admin/pages/product_stock.php' => "feat(inventory): add per-product stock history\n * Timeline of all stock_movements for a single product with running balance.",
    'admin/pages/register_admin.php' => "feat(admin): add the admin signup\n * Creates new admin account with bcrypt hash, requires existing admin session.",
    'admin/pages/stock_movements.php' => "feat(inventory): add the stock ledger with CSV export\n * Full movement log with filters by reason/date/product; CSV download button.",
    'admin/pages/update_product.php' => "feat(admin): add the product editor with absolute stock correction\n * Full product form; stock field writes absolute correction movement to ledger.",
    'admin/pages/update_profile.php' => "feat(admin): add the admin profile editor\n * Allows admin to update own name and password with bcrypt rehash.",
    'admin/pages/users_accounts.php' => "feat(admin): add the customer account list\n * Searchable, paginated table of all registered customers with order counts.",

    'app/bootstrap.php' => "refactor(core): add one bootstrap for config, database and session\n * Loads config, creates PDO connection, starts session, defines helper functions.",
    'app/cart_actions.php' => "feat(cart): add the add, update and remove cart actions\n * POST endpoints for cart mutations; validates stock, returns JSON responses.",
    'app/config.php' => "feat(config): centralise store, database and app settings\n * All settings via env vars with sensible defaults; returns immutable config array.",
    'app/helpers.php' => "refactor(core): add shared helpers for output, money, auth and flash messages\n * e(), money(), require_login(), require_admin(), flash(), redirect(), catalog_url(), plural().",
    'app/inventory.php' => "feat(inventory): add the stock ledger and the only writer of products.stock\n * adjust_stock() with row locks, reason enum, balance clamp, auto-reconcile, opening seed.",
    'app/payments/paymongo.php' => "feat(payments): add the Paymongo gateway wrapper\n * Creates checkout sessions, verifies webhooks, maps status to local order states.",

    'app/views/layout/head.php' => "feat(store): build the shared page shell, header and single search bar\n * HTML head, semantic header with logo, nav, cart link, search form; mobile drawer toggle.",
    'app/views/layout/footer.php' => "feat(store): build the site footer with store, shop and account links\n * Four-column grid: brand/info, shop categories, account links, legal; responsive stack.",

    'app/views/admin/head.php' => "feat(admin): build the admin shell with sidebar, topbar and flash messages\n * Off-canvas sidebar on mobile, topbar with user menu, flash toast container, CSRF token.",
    'app/views/admin/footer.php' => "feat(admin): close the admin shell with the drawer, scripts and toasts\n * Closes main, loads Swiper/JS, initializes drawer/dropdown/toast JS modules.",
    'app/views/admin/helpers.php' => "refactor(admin): add the admin table, badge and form helpers\n * admin_table(), badge(), form_field(), select_options(), csrf_field(), toast().",

    'app/views/shop/catalog.php' => "feat(store): add the shared catalogue listing used by every browse page\n * Builds WHERE/ORDER BY from filters, paginates, returns items + metadata for grid.",
    'app/views/shop/product_card.php' => "refactor(store): extract the product card and grid into reusable functions\n * product_card() renders one card; product_grid() wraps in responsive grid container.",
    'app/views/shop/quick_view_modal.php' => "feat(store): add the quick view modal shell\n * Accessible dialog with image carousel, meta, qty selector, add-to-cart, close on ESC.",

    'database/schema.sql' => "feat(db): define the full schema for the store\n * 8 tables: admin, cart, messages, orders, products, product_archive, stock_movements, users; seeds admin/user fixtures.",
    'database/inventory.sql' => "feat(db): add the stock_movements ledger and inventory columns\n * Creates stock_movements, adds sku/cost_price/low_stock_threshold to products, seeds 24 opening movements.",
    'database/upgrade.sql' => "chore(db): add an idempotent migration for existing installs\n * Guarded ALTER TABLEs and CREATE INDEXes; safe to re-run on any schema version.",

    'tools/admin_password.php' => "chore(tools): add the admin password re-hash helper\n * CLI script to bcrypt a plain password and update the admin row.",
    'tools/commit_per_file.php' => "chore(tools): add the per-file history rebuild script\n * Walks git ls-files, commits each file with a hand-written conventional subject.",
    'tools/config_audit.php' => "test(tools): add the config audit check\n * Resolves every config() call path against app/config.php, reports missing keys.",
    'tools/css_audit.php' => "test(tools): add the css cascade audit\n * Parses stylesheets, resolves cascade per breakpoint, flags defeated mobile overrides.",
    'tools/flows.php' => "test(tools): add the end-to-end flow checks\n * 164 checks: browse, cart, checkout, stock effects, admin login, cancel, restock.",
    'tools/responsive_audit.php' => "test(tools): add the responsive audit check\n * Parses CSS + markup, flags elements likely to overflow at small widths.",
    'tools/smoke.php' => "test(tools): add the HTTP smoke checks\n * 65 checks: status codes, redirects, security headers, app/ and database/ blocked.",
    'tools/strip_comments.php' => "chore(tools): add the comment stripper state machine\n * Token-aware stripper for PHP/CSS/JS; handles strings, heredocs, regex, data URIs.",
    'tools/strip_comments_test.php' => "test(tools): add the strip comments test suite\n * 26 cases covering strings, heredocs, regex literals, data URIs, EOF comments.",
    'tools/strip_damage_check.php' => "chore(tools): add the strip damage detector\n * Re-runs stripper on committed originals, diffs against working tree to detect loss.",
    'tools/strip_damage_diff.php' => "chore(tools): add the strip damage diff helper\n * LCS-based per-line diff for visualizing stripper-induced changes.",
    'tools/build_schema.php' => "chore(tools): add the schema rebuild helper\n * mysqldumps live DB structure + seed rows into database/schema.sql with bcrypt admin.",
];

$added = 0;
foreach ($headers as $rel => $body) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        echo "MISSING: $rel\n";
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) continue;

    if (preg_match('/^\s*<\?php\s*\n\s*declare\(strict_types=1\);\s*\n\s*\/\*\*/', $content)) {
        echo "SKIP (has header): $rel\n";
        continue;
    }

    $header = "/**\n * $body\n */\n\n";
    $newContent = preg_replace('/^(<\?php\s*\n\s*declare\(strict_types=1\);)/', '$1' . "\n\n" . $header, $content);

    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        echo "ADDED: $rel\n";
        $added++;
    } else {
        echo "FAILED to add: $rel\n";
    }
}

echo "\nDone. Added: $added\n";
