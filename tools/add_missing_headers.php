<?php

$root = dirname(__DIR__);

$files = [
    'app/views/admin/footer.php' => "feat(admin): close the admin shell with the drawer, scripts and toasts\n * Closes main, loads Swiper/JS, initializes drawer/dropdown/toast JS modules.",
    'database/schema.sql' => "feat(db): define the full schema for the store\n * 8 tables: admin, cart, messages, orders, products, product_archive, stock_movements, users; seeds admin/user fixtures.",
    'database/inventory.sql' => "feat(db): add the stock_movements ledger and inventory columns\n * Creates stock_movements, adds sku/cost_price/low_stock_threshold to products, seeds 24 opening movements.",
    'database/upgrade.sql' => "chore(db): add an idempotent migration for existing installs\n * Guarded ALTER TABLEs and CREATE INDEXes; safe to re-run on any schema version.",
];

foreach ($files as $rel => $body) {
    $path = $root . '/' . $rel;
    $content = file_get_contents($path);
    if ($content === false) {
        echo "MISSING: $rel\n";
        continue;
    }

    if (preg_match('/^\s*\/\*\*/', $content)) {
        echo "SKIP (has header): $rel\n";
        continue;
    }

    $header = "/**\n * $body\n */\n\n";
    $newContent = $header . $content;
    file_put_contents($path, $newContent);
    echo "ADDED: $rel\n";
}

echo "Done.\n";