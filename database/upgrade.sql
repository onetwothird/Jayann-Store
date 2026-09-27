/**
 * chore(db): add an idempotent migration for existing installs
 * Guarded ALTER TABLEs and CREATE INDEXes; safe to re-run on any schema version.
 */

-- =============================================================================
-- Jayann's Store — schema upgrade
-- -----------------------------------------------------------------------------
-- Safe to run more than once (every statement is guarded).
-- Fixes the columns the rebuilt UI needs:
--   • number columns were varchar(10) — too small for an 11-digit PH mobile
--   • money columns were int — no centavos allowed
--   • orders had no human-readable reference or shipping/s subtotal breakdown
--   • no indexes on the columns every page filters by
-- =============================================================================

-- ------------------------------------------------------------------ products
-- Money needs decimals (₱12.50 is a normal price).
ALTER TABLE `products`
  MODIFY `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  MODIFY `discount_price` decimal(10,2) NOT NULL DEFAULT 0.00;

-- Rows 23 & 24 shipped with discount = 0 but discount_price = 0, which the
-- storefront would render as "₱0.00". Copy the list price back in.
UPDATE `products` SET `discount_price` = `price` WHERE `discount_price` IS NULL OR `discount_price` = 0;

-- Sanity: a percentage discount must never push the price below zero.
UPDATE `products` SET `discount_price` = 0 WHERE `discount_price` < 0;

-- ---------------------------------------------------------------------- cart
ALTER TABLE `cart`
  MODIFY `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  MODIFY `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  MODIFY `user_id` int(100) UNSIGNED NOT NULL,
  MODIFY `pid` int(100) UNSIGNED NOT NULL;

-- --------------------------------------------------------------------- users
ALTER TABLE `users`
  MODIFY `number` varchar(20) NOT NULL DEFAULT '',
  MODIFY `email` varchar(120) NOT NULL DEFAULT '',
  MODIFY `address` varchar(500) NOT NULL DEFAULT '',
  MODIFY `password` varchar(255) NOT NULL DEFAULT '';

-- -------------------------------------------------------------------- orders
ALTER TABLE `orders`
  MODIFY `number` varchar(20) NOT NULL DEFAULT '',
  MODIFY `email` varchar(120) NOT NULL DEFAULT '',
  MODIFY `method` varchar(30) NOT NULL DEFAULT 'cod',
  MODIFY `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  MODIFY `total_products` text NOT NULL,
  MODIFY `payment_status` varchar(20) NOT NULL DEFAULT 'pending';

-- Guarded so re-running the file is a no-op.
SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `orders` ADD COLUMN `order_ref` varchar(24) NOT NULL DEFAULT '''' AFTER `id`', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_ref');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `orders` ADD COLUMN `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `total_price`', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'subtotal');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `orders` ADD COLUMN `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_fee');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `orders` ADD COLUMN `payment_reference` varchar(120) DEFAULT NULL AFTER `payment_details`', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_reference');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill a reference for anything already in the table.
UPDATE `orders`
SET `order_ref` = CONCAT('JYS-', LPAD(`id`, 6, '0'))
WHERE `order_ref` = '' OR `order_ref` IS NULL;

-- ----------------------------------------------------------------- messages
ALTER TABLE `messages`
  MODIFY `number` varchar(20) NOT NULL DEFAULT '',
  MODIFY `email` varchar(120) NOT NULL DEFAULT '';

-- --------------------------------------------------------------------- admin
-- The original column was varchar(50), too short for a bcrypt hash (60 chars).
ALTER TABLE `admin`
  MODIFY `name` varchar(60) NOT NULL DEFAULT '',
  MODIFY `password` varchar(255) NOT NULL DEFAULT '';

-- ----------------------------------------------------------------- archive
-- product_archive was written by the original admin panel; make sure it can
-- hold a decimal price so an archived row still shows a sane number.
ALTER TABLE `product_archive`
  MODIFY `name` varchar(255) NOT NULL DEFAULT '',
  MODIFY `category` varchar(255) NOT NULL DEFAULT '',
  MODIFY `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  MODIFY `image` varchar(255) NOT NULL DEFAULT '',
  MODIFY `stock` int(11) NOT NULL DEFAULT 0;

-- ------------------------------------------------------------------- indexes
-- Added individually so re-running the file never errors out.
SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `products` ADD INDEX `idx_products_category` (`category`)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_category');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `cart` ADD INDEX `idx_cart_user` (`user_id`)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cart' AND INDEX_NAME = 'idx_cart_user');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `cart` ADD UNIQUE INDEX `uq_cart_user_pid` (`user_id`, `pid`)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cart' AND INDEX_NAME = 'uq_cart_user_pid');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `orders` ADD INDEX `idx_orders_user` (`user_id`)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_user');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `orders` ADD INDEX `idx_orders_ref` (`order_ref`)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_ref');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `orders` ADD INDEX `idx_orders_status` (`payment_status`)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_status');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD UNIQUE INDEX `uq_users_email` (`email`)', 'DO 0') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_email');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------------ results
SELECT 'products' AS tbl, COUNT(*) AS rows_total, SUM(discount_price = 0) AS bad_price FROM products
UNION ALL SELECT 'cart',      COUNT(*), 0 FROM cart
UNION ALL SELECT 'orders',    COUNT(*), SUM(order_ref = '') FROM orders
UNION ALL SELECT 'users',     COUNT(*), 0 FROM users;
