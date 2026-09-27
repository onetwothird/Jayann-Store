/**
 * feat(db): add the stock_movements ledger and inventory columns
 * Creates stock_movements, adds sku/cost_price/low_stock_threshold to products, seeds 24 opening movements.
 */

/* ==========================================================================
   Inventory system
   --------------------------------------------------------------------------
   Two parts:

   1. products gains the fields an inventory system needs on top of `stock`:
        low_stock_threshold  when to raise a reorder alert
        cost_price           unit cost, so stock can be valued
        sku                  a human-facing stock code
        supplier             who we buy it from
        last_restocked_on    when stock last went up

   2. stock_movements is the ledger. Every change to any product's stock is
      appended here with the reason, who did it and the balance it produced.
      The `stock` column on products stays as the fast-read balance; the
      ledger is the audit trail behind it.

   Written so it can be re-run safely: every change is guarded by an
   information_schema check, so applying it twice is a no-op.
   ========================================================================== */

/* ---- 1. Products: inventory columns ------------------------------------ */
SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE products
            ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 5 AFTER stock,
            ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER low_stock_threshold,
            ADD COLUMN sku VARCHAR(40) NOT NULL DEFAULT '''' AFTER cost_price,
            ADD COLUMN supplier VARCHAR(120) NOT NULL DEFAULT '''' AFTER sku,
            ADD COLUMN last_restocked_on DATETIME NULL AFTER supplier',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'low_stock_threshold'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE products
            ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER low_stock_threshold,
            ADD COLUMN sku VARCHAR(40) NOT NULL DEFAULT '''' AFTER cost_price,
            ADD COLUMN supplier VARCHAR(120) NOT NULL DEFAULT '''' AFTER sku,
            ADD COLUMN last_restocked_on DATETIME NULL AFTER supplier',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'cost_price'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE products ADD COLUMN sku VARCHAR(40) NOT NULL DEFAULT '''' AFTER cost_price',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'sku'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE products ADD COLUMN supplier VARCHAR(120) NOT NULL DEFAULT '''' AFTER sku',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'supplier'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE products ADD COLUMN last_restocked_on DATETIME NULL AFTER supplier',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'last_restocked_on'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- orders gains one flag. Cancelling an order puts the units back on the shelf,
-- and without a record of that having happened, cancelling the same order twice
-- would quietly double the stock.
SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE orders ADD COLUMN stock_returned TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_status',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'stock_returned'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- product_archive keeps the inventory settings too, so archiving and later
-- restoring a product does not quietly reset its reorder threshold, unit cost
-- and stock code. Without these, the round trip loses real data.
SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE product_archive ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 5 AFTER stock',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_archive' AND COLUMN_NAME = 'low_stock_threshold'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE product_archive ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER low_stock_threshold',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_archive' AND COLUMN_NAME = 'cost_price'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE product_archive ADD COLUMN sku VARCHAR(40) NULL AFTER cost_price',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_archive' AND COLUMN_NAME = 'sku'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE product_archive ADD COLUMN supplier VARCHAR(120) NULL AFTER sku',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_archive' AND COLUMN_NAME = 'supplier'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---- 2. The movement ledger -------------------------------------------- */
CREATE TABLE IF NOT EXISTS stock_movements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    /* Snapshotted so the ledger still reads sensibly after a product is
       renamed or archived and the original row is gone. */
    product_name    VARCHAR(100) NOT NULL DEFAULT '',
    /* in = goods received, out = goods left, or a signed correction. */
    direction       ENUM('in', 'out') NOT NULL DEFAULT 'out',
    /* Always positive; `direction` carries the sign. */
    quantity        INT NOT NULL DEFAULT 0,
    /* Signed delta that was applied, e.g. -2 for a sale of two units.
       Named qty_change because CHANGE is a reserved word in MySQL. */
    qty_change      INT NOT NULL DEFAULT 0,
    /* Running balance on the product after this row was applied. */
    balance_after   INT NOT NULL DEFAULT 0,
    /* purchase | sale | return | damage | correction | opening | archive */
    reason          VARCHAR(20) NOT NULL DEFAULT 'correction',
    note            VARCHAR(255) NOT NULL DEFAULT '',
    /* Order ref, purchase reference, or anything else to tie back to. */
    reference       VARCHAR(60) NOT NULL DEFAULT '',
    /* Admin username. */
    actor           VARCHAR(80) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_movements_product (product_id, id),
    KEY idx_movements_created (created_at),
    KEY idx_movements_reason  (reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/* ---- 3. Give every existing product a stock code ---------------------- */
UPDATE products SET sku = CONCAT('JYS-', LPAD(id, 4, '0')) WHERE sku = '' OR sku IS NULL;

/* ---- 4. Open with an opening-balance row per product ------------------ */
/* Only for products that have never had a movement logged, so re-running
   this cannot duplicate the opening balance. */
INSERT INTO stock_movements (product_id, product_name, direction, quantity, qty_change, balance_after, reason, note, actor)
SELECT p.id, p.name, 'in', p.stock, p.stock, p.stock, 'opening', 'Opening balance when the inventory system was up', 'system'
FROM products p
WHERE NOT EXISTS (SELECT 1 FROM stock_movements m WHERE m.product_id = p.id);

/* ---- 5. A starting unit cost, so stock valuation is not always zero ----
   A typical corner-store reseller lands around 60-70% of retail. This only
   fills rows that are still 0.00, so once you enter a real cost_price in the
   inventory screen it is never overwritten by re-running this file.

   To reset everything back to zero and start from your real figures:
       UPDATE products SET cost_price = 0.00;                              */
UPDATE products
SET cost_price = ROUND(price * 0.60, 2)
WHERE cost_price = 0.00 AND price > 0;

/* ---- 6. Reconcile the ledger against the shelf -------------------------
   Orders placed before this system existed took units off products.stock
   without leaving a trace, so the two legitimately disagree by a unit or two.

   The fix is NOT to rewrite history: that is exactly the kind of silent edit
   that makes a ledger untrustworthy. Instead we record the difference as an
   explicit `reconcile` movement, which is what a real stocktake does. The
   books then agree with the shelf, and the row explains why they used to
   differ.

   Idempotent: once the two agree this SELECT matches nothing, so re-running
   adds no second reconciliation row.                                        */
INSERT INTO stock_movements
    (product_id, product_name, direction, quantity, qty_change, balance_after,
     reason, note, reference, actor)
SELECT p.id,
       p.name,
       CASE WHEN p.stock - COALESCE(s.ledger, 0) >= 0 THEN 'in' ELSE 'out' END,
       ABS(p.stock - COALESCE(s.ledger, 0)),
       p.stock - COALESCE(s.ledger, 0),
       p.stock,
       'reconcile',
       'Reconciled against the physical count when the inventory system was installed',
       'MIGRATION',
       'system'
FROM products p
LEFT JOIN (
    SELECT product_id, SUM(qty_change) AS ledger
    FROM stock_movements
    GROUP BY product_id
) s ON s.product_id = p.id
WHERE p.stock <> COALESCE(s.ledger, 0);
