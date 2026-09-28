/**
 * Jayann's Store - Complete Database Schema
 * Single-file import for phpMyAdmin / MySQL / MariaDB
 * 
 * Tables: admin, users, products, product_archive, cart, orders, messages, stock_movements
 * Includes: schema, indexes, foreign keys, seed data, inventory opening balances
 * 
 * Usage:
 *   1. Open phpMyAdmin
 *   2. Create database: jayann_store (utf8mb4_unicode_ci)
 *   3. Import this file
 *   4. Done - no additional files needed
 */

-- ============================================================================
-- DATABASE SETUP
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `jayann_store`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `jayann_store`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================================
-- TABLE: admin
-- ============================================================================

DROP TABLE IF EXISTS `admin`;

CREATE TABLE `admin` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed: admin / password123 (bcrypt hash)
INSERT INTO `admin` (`id`, `name`, `password`) VALUES
(1, 'admin', '$2y$10$Pq/sc.TaE7DyYlbOGgGcA.1yLqST4twxDOKqD2CuQ3/ru4iqYxISS');

-- ============================================================================
-- TABLE: users
-- ============================================================================

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `email` varchar(120) NOT NULL DEFAULT '',
  `number` varchar(20) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed users
INSERT INTO `users` (`id`, `name`, `email`, `number`, `password`, `address`) VALUES
(1, 'Angelito Decatoria', 'angelitodecatoriaa@gmail.com', '23', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', ''),
(2, 'Angelito P. Decatoria III', 'angelitodecatoria@gmail.com', '09385100460', '$2y$10$iUMSRAFDo2hvjxWw4Mj2euLSyZJmn7q.AF.AZOahG87B5onyp//LK', 'Blk 57 Lot 14, Hyacinth Residence');

-- ============================================================================
-- TABLE: products
-- ============================================================================

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` int(3) NOT NULL DEFAULT 0,
  `discount_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(100) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 5,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sku` varchar(40) NOT NULL DEFAULT '',
  `supplier` varchar(120) NOT NULL DEFAULT '',
  `last_restocked_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed products (24 items)
INSERT INTO `products` (`id`, `name`, `category`, `price`, `discount`, `discount_price`, `image`, `stock`, `low_stock_threshold`, `cost_price`, `sku`, `supplier`, `last_restocked_on`) VALUES
(1, 'Kopiko 3in1 Coffee - Blanca Twin Pack 58g x 10 sachets', 'Snacks', 12.00, 0, 12.00, 'coffee.jpeg', 0, 5, 7.20, 'JYS-0001', '', NULL),
(2, 'Summit Natural Drinking Water - 350ml', 'Beverages', 27.00, 0, 27.00, '1734357572_summit.webp', 222, 5, 16.20, 'JYS-0002', '', NULL),
(3, 'Buko Pandan Rice Green Sack - 25kg', 'Essentials', 1351.00, 2, 1324.00, '2004895934-1.png', 30, 5, 810.60, 'JYS-0003', '', NULL),
(4, 'ORAL B Soft 3D White Whitening Manual Toothbrush 3 Pieces', 'Personal Care', 20.00, 0, 20.00, 'H103397_1b88.png', 200, 5, 12.00, 'JYS-0004', '', NULL),
(5, 'M.Y. San Sky Flakes Crackers Original - 25g x 10\'s', 'Snacks', 200.00, 0, 200.00, 'skyflakes.webp', 5, 5, 120.00, 'JYS-0005', '', NULL),
(6, 'Colgate Maximum Cavity Protection Toothpaste - 214g', 'Personal Care', 20.00, 0, 20.00, 'colgate-mcp-great-regular-flavor-toohpaste-214g-box.jpg', 23, 5, 12.00, 'JYS-0006', '', NULL),
(7, 'Jack \'n Jill V Cut Potato Chips Spicy BBQ - 162g', 'Snacks', 30.00, 0, 30.00, '1735896902_vcut.png', 200, 5, 18.00, 'JYS-0007', '', NULL),
(8, 'Regent Cheese Ring Snacks 1 pcs - 60g', 'Snacks', 20.00, 5, 19.00, '1735897083_cheeserings.webp', 50, 5, 12.00, 'JYS-0008', '', NULL),
(9, 'SILKA Whitening Herbal Soap Green Papaya 135g', 'Personal Care', 20.00, 6, 19.00, '1735898564_SILKA_Whitening_Herbal_Soap_Green_Papaya_135g.png.webp', 100, 5, 12.00, 'JYS-0009', '', NULL),
(10, 'COCA-COLA Regular Mismo 290ml', 'Beverages', 23.00, 0, 23.00, '1735898673_4801981118502COKE295MLP13.75_800x_1_-removebg-preview.png.webp', 100, 5, 13.80, 'JYS-0010', '', NULL),
(11, 'PIATTOS Cheese Flavored Potato Chips - 40g', 'Snacks', 20.00, 5, 19.00, '1735898720_Piattos-Cheese-40g.png.webp', 19, 5, 12.00, 'JYS-0011', '', NULL),
(12, 'LUDY\'S SALABAT Ginger Brew Classic 8g 1\'s', 'Essentials', 9.00, 0, 9.00, '1735898846_image_05b1afaa-7d73-48ac-b341-185a34257307_1_-removebg-preview.png.webp', 20, 5, 5.40, 'JYS-0012', '', NULL),
(13, 'STING Energy Drink Strawberry Flavor 290ml 1\'s', 'Beverages', 22.00, 5, 21.00, '1735898903_S733e34095ba64f44bd04c11ec53798634-removebg-preview.png.webp', 99, 5, 13.20, 'JYS-0013', '', NULL),
(14, 'DR. S. WONG\'S SULFUR SOAP (Yellow) Soap - 135g', 'Personal Care', 55.00, 0, 55.00, '1735898962_51vD7lOzp_L.jpg.webp', 0, 5, 33.00, 'JYS-0014', '', NULL),
(15, 'MILCU Underarm and Foot Deodorant Powder 40g', 'Personal Care', 57.00, 0, 57.00, '1735899037_10107001_milcu-underarm-and-foot-deo-pdr-40g_1_-removebg-preview.png.webp', 57, 5, 34.20, 'JYS-0015', '', NULL),
(16, 'LISTERINE Mouthwash 250mL Cool Mint', 'Personal Care', 155.00, 0, 155.00, '1735899097_81wkfQI6rnL.jpg.webp', 0, 5, 93.00, 'JYS-0016', '', NULL),
(17, 'FEMME Bathroom Tissue 2 Ply 150 Pulls 300 Sheets 1\'s', 'Personal Care', 12.00, 0, 12.00, '1735899146_4806502359754_1024x-removebg-preview[1].png.webp', 100, 5, 7.20, 'JYS-0017', '', NULL),
(18, 'SISTERS Night Plus Heavy Flow with Wings 8 Pads', 'Personal Care', 28.00, 5, 27.00, '1735899214_ezgif-4-cc15e1839f-removebg-preview[1].png.webp', 49, 5, 16.80, 'JYS-0018', '', NULL),
(19, 'NESTOGEN 1 Infant Milk Formula For 0-6 Months 135g', 'Essentials', 82.00, 7, 76.00, '1735899283_c5292204540082a4dc45aa231b2a5915.jpg.webp', 100, 5, 49.20, 'JYS-0019', '', NULL),
(20, 'BEAR BRAND Junior 1+ Milk 1-3 years old 400g', 'Essentials', 196.00, 10, 176.00, '1735899359_Bear_Brand_Junior_1__400g.png.webp', 100, 5, 117.60, 'JYS-0020', '', NULL),
(21, 'BENCH FIX Professional Clay Doh - 25g', 'Personal Care', 58.00, 0, 58.00, '1735899503_tcr1025e_fl_45_za_1-removebg-preview[1].png.webp', 10, 5, 34.80, 'JYS-0021', '', '2026-09-26 18:06:18'),
(22, 'DEL MONTE 100% Pineapple Juice Drink with A-C-E 220ml 1\'s', 'Beverages', 35.00, 0, 35.00, 'del.webp', 100, 5, 21.00, 'JYS-0022', '', NULL),
(23, 'LUCKY ME Go Cup Batchoy Cup Noodles 40g', 'Snacks', 26.00, 0, 26.00, '1735898790_lucky_me_go_cup_batchoy.jpg.webp', 5, 5, 15.60, 'JYS-0023', '', NULL),
(24, 'Safeguard Family Germ Protection Soap', 'Personal Care', 30.00, 0, 30.00, 'safeguard.jpg', 2, 5, 18.00, 'JYS-0024', '', NULL);

-- Fix discount_price = 0 to match price (for rows where discount is 0)
UPDATE `products` SET `discount_price` = `price` WHERE `discount_price` = 0 AND `discount` = 0;
-- Ensure cost_price is set (60% of price as default)
UPDATE `products` SET `cost_price` = ROUND(`price` * 0.60, 2) WHERE `cost_price` = 0.00 AND `price` > 0;

-- ============================================================================
-- TABLE: product_archive
-- ============================================================================

DROP TABLE IF EXISTS `product_archive`;

CREATE TABLE `product_archive` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `category` varchar(255) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) NOT NULL DEFAULT '',
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 5,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sku` varchar(40) DEFAULT NULL,
  `supplier` varchar(120) DEFAULT NULL,
  `date_deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- TABLE: cart
-- ============================================================================

DROP TABLE IF EXISTS `cart`;

CREATE TABLE `cart` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `user_id` int(100) UNSIGNED NOT NULL,
  `pid` int(100) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `image` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_user_pid` (`user_id`, `pid`),
  KEY `idx_cart_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- TABLE: orders
-- ============================================================================

DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `order_ref` varchar(24) NOT NULL DEFAULT '',
  `user_id` int(100) NOT NULL,
  `name` varchar(30) NOT NULL,
  `number` varchar(20) NOT NULL DEFAULT '',
  `email` varchar(120) NOT NULL DEFAULT '',
  `method` varchar(30) NOT NULL DEFAULT 'cod',
  `address` varchar(500) NOT NULL,
  `total_products` text NOT NULL,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_details` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(120) DEFAULT NULL,
  `placed_on` date NOT NULL DEFAULT (CURRENT_DATE),
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `stock_returned` tinyint(1) NOT NULL DEFAULT 0,
  `order_date` date NOT NULL DEFAULT (CURRENT_DATE),
  `completed_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_ref` (`order_ref`),
  KEY `idx_orders_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- TABLE: messages
-- ============================================================================

DROP TABLE IF EXISTS `messages`;

CREATE TABLE `messages` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL DEFAULT '',
  `number` varchar(20) NOT NULL DEFAULT '',
  `message` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- TABLE: stock_movements
-- ============================================================================

DROP TABLE IF EXISTS `stock_movements`;

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL DEFAULT '',
  `direction` enum('in','out') NOT NULL DEFAULT 'out',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `qty_change` int(11) NOT NULL DEFAULT 0,
  `balance_after` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(20) NOT NULL DEFAULT 'correction',
  `note` varchar(255) NOT NULL DEFAULT '',
  `reference` varchar(60) NOT NULL DEFAULT '',
  `actor` varchar(80) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movements_product` (`product_id`, `id`),
  KEY `idx_movements_created` (`created_at`),
  KEY `idx_movements_reason` (`reason`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- INVENTORY OPENING BALANCES (stock_movements seed)
-- One 'opening' row per product, only if no movements exist yet
-- ============================================================================

INSERT INTO `stock_movements` (`product_id`, `product_name`, `direction`, `quantity`, `qty_change`, `balance_after`, `reason`, `note`, `actor`)
SELECT p.`id`, p.`name`, 'in', p.`stock`, p.`stock`, p.`stock`, 'opening', 'Opening balance when the inventory system was up', 'system'
FROM `products` p
WHERE NOT EXISTS (SELECT 1 FROM `stock_movements` m WHERE m.`product_id` = p.`id`);

-- Reconciliation: if any product's stock differs from ledger sum, add a 'reconcile' movement
INSERT INTO `stock_movements` (`product_id`, `product_name`, `direction`, `quantity`, `qty_change`, `balance_after`, `reason`, `note`, `reference`, `actor`)
SELECT p.`id`,
       p.`name`,
       CASE WHEN p.`stock` - COALESCE(s.`ledger`, 0) >= 0 THEN 'in' ELSE 'out' END,
       ABS(p.`stock` - COALESCE(s.`ledger`, 0)),
       p.`stock` - COALESCE(s.`ledger`, 0),
       p.`stock`,
       'reconcile',
       'Reconciled against the physical count when the inventory system was installed',
       'MIGRATION',
       'system'
FROM `products` p
LEFT JOIN (
  SELECT `product_id`, SUM(`qty_change`) AS `ledger`
  FROM `stock_movements`
  GROUP BY `product_id`
) s ON s.`product_id` = p.`id`
WHERE p.`stock` <> COALESCE(s.`ledger`, 0);

-- ============================================================================
-- VERIFICATION QUERIES (run after import to confirm)
-- ============================================================================

-- SELECT 'products' AS `table`, COUNT(*) AS `rows`, SUM(`discount_price` = 0) AS `zero_price` FROM `products`
-- UNION ALL SELECT 'cart', COUNT(*), 0 FROM `cart`
-- UNION ALL SELECT 'orders', COUNT(*), SUM(`order_ref` = '') FROM `orders`
-- UNION ALL SELECT 'users', COUNT(*), 0 FROM `users`
-- UNION ALL SELECT 'stock_movements', COUNT(*), SUM(`reason` = 'opening') FROM `stock_movements`
-- UNION ALL SELECT 'admin', COUNT(*), 0 FROM `admin`;

-- ============================================================================
-- END OF FILE
-- ============================================================================