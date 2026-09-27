<?php

declare(strict_types=1);

if (!function_exists('inventory_is_installed')) {

    function inventory_is_installed(): bool
    {
        static $installed = null;

        if ($installed !== null) {
            return $installed;
        }

        global $db;
        try {
            $installed = (bool) $db->value(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
                   AND COLUMN_NAME = 'low_stock_threshold'"
            );
        } catch (Throwable $e) {
            $installed = false;
        }

        return $installed;
    }
}

if (!function_exists('inventory_needs_restock_count')) {

    function inventory_needs_restock_count(): int
    {
        global $db;

        if (!inventory_is_installed()) {
            return 0;
        }

        return (int) ($db->value(
            'SELECT COUNT(*) FROM products
             WHERE stock <= 0
                OR (low_stock_threshold > 0 AND stock <= low_stock_threshold)'
        ) ?? 0);
    }
}

if (!function_exists('inventory_reasons')) {

    function inventory_reasons(): array
    {
        return [
            'purchase'    => ['label' => 'Stock received',   'icon' => 'fa-truck-ramp-box',    'direction' => 'in'],
            'sale'        => ['label' => 'Sold',             'icon' => 'fa-bag-shopping',       'direction' => 'out'],
            'return'      => ['label' => 'Customer return',  'icon' => 'fa-rotate-left',        'direction' => 'in'],
            'damage'      => ['label' => 'Damaged / expired','icon' => 'fa-triangle-exclamation','direction' => 'out'],
            'correction'  => ['label' => 'Stocktake count',  'icon' => 'fa-calculator',         'direction' => 'out'],
            'reconcile'   => ['label' => 'Books reconciled', 'icon' => 'fa-scale-balanced',    'direction' => 'in'],
            'opening'     => ['label' => 'Opening balance',  'icon' => 'fa-flag',               'direction' => 'in'],
            'archive'     => ['label' => 'Archived',         'icon' => 'fa-box-archive',        'direction' => 'out'],
        ];
    }

    function inventory_reason_meta(string $reason): array
    {
        $all = inventory_reasons();
        return $all[$reason] ?? ['label' => ucfirst($reason), 'icon' => 'fa-circle-info', 'direction' => 'out'];
    }

    function is_inventory_reason(string $reason): bool
    {
        return array_key_exists($reason, inventory_reasons());
    }
}

if (!function_exists('stock_status')) {

    function stock_status(int $stock, int $threshold = 0): array
    {
        if ($stock <= 0) {
            return ['key' => 'out', 'label' => 'Out of stock', 'tag' => 'danger', 'icon' => 'fa-circle-xmark'];
        }
        if ($threshold > 0 && $stock <= $threshold) {
            return [
                'key'   => 'low',
                'label' => 'Low · reorder',
                'tag'   => 'warn',
                'icon'  => 'fa-triangle-exclamation',
            ];
        }
        return ['key' => 'ok', 'label' => 'In stock', 'tag' => 'success', 'icon' => 'fa-circle-check'];
    }
}

if (!function_exists('adjust_stock')) {

    function adjust_stock(int $productId, int $change, string $reason, array $opts = []): array
    {
        global $db;

        if ($productId <= 0) {
            return ['ok' => false, 'message' => 'No product was selected.', 'balance' => 0, 'applied' => 0, 'product' => null];
        }
        if (!is_inventory_reason($reason)) {
            $reason = 'correction';
        }
        if ($change === 0) {
            return ['ok' => false, 'message' => 'The quantity was zero, so nothing changed.', 'balance' => 0, 'applied' => 0, 'product' => null];
        }

        $note      = trim((string) ($opts['note'] ?? ''));
        $reference = trim((string) ($opts['reference'] ?? ''));
        $actor     = trim((string) ($opts['actor'] ?? 'system'));
        if ($actor === '') {
            $actor = 'system';
        }
        if (mb_strlen($note) > 255) {
            $note = mb_substr($note, 0, 255);
        }
        if (mb_strlen($reference) > 60) {
            $reference = mb_substr($reference, 0, 60);
        }
        if (mb_strlen($actor) > 80) {
            $actor = mb_substr($actor, 0, 80);
        }

        $product = $db->one('SELECT * FROM products WHERE id = ? FOR UPDATE', [$productId]);

        if (!$product) {
            return [
                'ok'      => false,
                'message' => 'That product no longer exists.',
                'balance' => 0,
                'applied' => 0,
                'product' => null,
            ];
        }

        $before  = (int) $product['stock'];
        $after   = $before + $change;
        $floor   = !empty($opts['allowNegative']) ? PHP_INT_MIN : 0;

        if ($after < $floor) {
            $after = 0;
        }
        $applied = $after - $before;

        if ($applied === 0) {
            return [
                'ok'      => false,
                'message' => $change < 0
                    ? '"' . $product['name'] . '" only has ' . $before . ' in stock, so none were taken out.'
                    : 'The quantity was zero, so nothing changed.',
                'balance' => $before,
                'applied' => 0,
                'product' => $product,
            ];
        }

        $db->run('UPDATE products SET stock = ? WHERE id = ?', [$after, $productId]);
        if ($applied > 0) {
            $db->run('UPDATE products SET last_restocked_on = NOW() WHERE id = ?', [$productId]);
        }

        $db->run(
            'INSERT INTO stock_movements
                (product_id, product_name, direction, quantity, qty_change, balance_after, reason, note, reference, actor)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $productId,
                (string) $product['name'],
                $applied > 0 ? 'in' : 'out',
                abs($applied),
                $applied,
                $after,
                $reason,
                $note,
                $reference,
                $actor,
            ]
        );

        return [
            'ok'      => true,
            'message' => sprintf(
                '%s is now at %d %s.',
                $product['name'],
                $after,
                $after === 1 ? 'unit' : 'units'
            ),
            'balance' => $after,
            'applied' => $applied,
            'product' => $product,
        ];
    }
}

if (!function_exists('stock_set_absolute')) {

    function stock_set_absolute(int $productId, int $counted, string $reason = 'correction', array $opts = []): array
    {
        global $db;

        if ($counted < 0) {
            $counted = 0;
        }

        $current = $db->value('SELECT stock FROM products WHERE id = ?', [$productId]);
        if ($current === null) {
            return ['ok' => false, 'message' => 'That product no longer exists.', 'balance' => 0, 'applied' => 0, 'product' => null];
        }

        $delta = $counted - (int) $current;
        if ($delta === 0) {
            return [
                'ok'      => false,
                'message' => 'The counted quantity already matches the recorded stock, so nothing changed.',
                'balance' => (int) $current,
                'applied' => 0,
                'product' => null,
            ];
        }

        return adjust_stock($productId, $delta, $reason, $opts + ['allowNegative' => true]);
    }
}

if (!function_exists('open_stock_balance')) {

    function open_stock_balance(int $productId, array $opts = []): bool
    {
        global $db;

        if ($productId <= 0 || !inventory_is_installed()) {
            return false;
        }

        $product = $db->one('SELECT id, name, stock FROM products WHERE id = ?', [$productId]);
        if (!$product) {
            return false;
        }

        $stock = (int) $product['stock'];
        if ($stock <= 0) {
            return false;
        }

        $exists = (int) ($db->value(
            "SELECT COUNT(*) FROM stock_movements WHERE product_id = ? AND reason = 'opening'",
            [$productId]
        ) ?? 0);
        if ($exists > 0) {
            return false;
        }

        $note  = trim((string) ($opts['note'] ?? 'Opening balance'));
        $actor = trim((string) ($opts['actor'] ?? 'system')) ?: 'system';

        $db->run(
            "INSERT INTO stock_movements
                (product_id, product_name, direction, quantity, qty_change, balance_after, reason, note, actor)
             VALUES (?, ?, 'in', ?, ?, ?, 'opening', ?, ?)",
            [$productId, (string) $product['name'], $stock, $stock, $stock, $note, $actor]
        );

        return true;
    }
}

if (!function_exists('restock_cancelled_order')) {

    function restock_cancelled_order(array $order, string $actor = 'admin'): array
    {
        global $db;

        $ref       = (string) ($order['order_ref'] ?? ('#' . (int) ($order['id'] ?? 0)));
        $lines     = order_lines($order);
        $restored  = 0;
        $handled   = 0;
        $skipped   = [];

        foreach ($lines as $line) {
            $pid = (int) ($line['pid'] ?? 0);

            if ($pid <= 0) {

                $pid = (int) ($db->value(
                    'SELECT id FROM products WHERE name = ? ORDER BY id LIMIT 1',
                    [(string) $line['name']]
                ) ?? 0);
            }

            if ($pid <= 0) {
                $skipped[] = (string) $line['name'];
                continue;
            }

            $result = adjust_stock($pid, (int) $line['quantity'], 'return', [
                'note'      => 'Returned to stock - order ' . $ref . ' was cancelled',
                'reference' => $ref,
                'actor'     => $actor,
            ]);

            if ($result['ok']) {
                $restored += (int) $line['quantity'];
                $handled++;
            } else {
                $skipped[] = (string) $line['name'];
            }
        }

        $db->run(
            "UPDATE orders SET stock_returned = 1 WHERE id = ?",
            [(int) ($order['id'] ?? 0)]
        );

        $message = $handled > 0
            ? number_format($restored) . ' ' . plural($restored, 'unit') . ' returned to stock.'
            : 'No stock needed returning.';

        if ($skipped !== []) {
            $message .= ' Could not match: ' . implode(', ', $skipped) . '.';
        }

        return [
            'restored' => $restored,
            'lines'    => $handled,
            'skipped'  => $skipped,
            'message'  => $message,
        ];
    }
}

if (!function_exists('inventory_summary')) {

    function inventory_summary(): array
    {
        global $db;

        $row = $db->one(
            "SELECT
                COUNT(*)                                            AS sku_count,
                COALESCE(SUM(stock), 0)                             AS unit_count,
                COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END), 0) AS out_count,
                COALESCE(SUM(CASE WHEN stock > 0 AND low_stock_threshold > 0
                                   AND stock <= low_stock_threshold THEN 1 ELSE 0 END), 0) AS low_count,
                COALESCE(SUM(CASE WHEN stock > 0
                                   AND (low_stock_threshold <= 0 OR stock > low_stock_threshold)
                                   THEN 1 ELSE 0 END), 0)           AS ok_count,
                COALESCE(SUM(stock * cost_price), 0)                AS value_cost,
                COALESCE(SUM(stock * price), 0)                     AS value_retail,
                COALESCE(SUM(stock * (price - cost_price)), 0)       AS margin_value
             FROM products"
        ) ?? [];

        return [
            'skus'         => (int) ($row['sku_count'] ?? 0),
            'units'        => (int) ($row['unit_count'] ?? 0),
            'out'          => (int) ($row['out_count'] ?? 0),
            'low'          => (int) ($row['low_count'] ?? 0),
            'ok'           => (int) ($row['ok_count'] ?? 0),
            'value_cost'   => (float) ($row['value_cost'] ?? 0),
            'value_retail' => (float) ($row['value_retail'] ?? 0),
            'stock_cost'   => (float) ($row['margin_value'] ?? 0),
        ];
    }
}

if (!function_exists('inventory_reorder_list')) {

    function inventory_reorder_list(int $limit = 50): array
    {
        global $db;

        $limit = max(1, $limit);
        return $db->all(
            "SELECT p.*,
                    GREATEST(p.low_stock_threshold - p.stock, 0) AS suggested_order
             FROM products p
             WHERE p.stock <= 0
                OR (p.low_stock_threshold > 0 AND p.stock <= p.low_stock_threshold)
             ORDER BY (p.stock <= 0) DESC, p.stock ASC, p.name ASC
             LIMIT $limit"
        );
    }
}

if (!function_exists('inventory_last_movements')) {

    function inventory_last_movements(int $limit = 15): array
    {
        global $db;

        $limit = max(1, $limit);
        return $db->all(
            "SELECT m.* FROM stock_movements m
             ORDER BY m.id DESC
             LIMIT $limit"
        );
    }
}

if (!function_exists('inventory_product_movements')) {

    function inventory_product_movements(int $productId, int $limit = 100): array
    {
        global $db;

        $limit = max(1, $limit);
        return $db->all(
            'SELECT * FROM stock_movements WHERE product_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$productId]
        );
    }
}

if (!function_exists('inventory_movement_totals')) {

    function inventory_movement_totals(int $days = 30): array
    {
        global $db;

        $days = max(1, min($days, 365));

        $rows = $db->all(
            "SELECT reason,
                    COALESCE(SUM(quantity), 0) AS unit_count,
                    COUNT(*)                  AS entry_count
             FROM stock_movements
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
             GROUP BY reason
             ORDER BY unit_count DESC"
        );

        $out = [];
        foreach ($rows as $row) {
            $reason = (string) $row['reason'];
            $meta   = inventory_reason_meta($reason);
            $out[]  = [
                'reason' => $reason,
                'label'  => $meta['label'],
                'icon'   => $meta['icon'],
                'units'  => (int) $row['unit_count'],
                'rows'   => (int) $row['entry_count'],
            ];
        }
        return $out;
    }
}
