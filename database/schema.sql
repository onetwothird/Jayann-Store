/**
 * feat(db): define the full schema for the store
 * 8 tables: admin, cart, messages, orders, products, product_archive, stock_movements, users; seeds admin/user fixtures.
 */

-- Jayann's Store - database schema
-- Host: 127.0.0.1
-- Server: MariaDB 10.4 / MySQL 5.7+
--
-- Import into an empty database, then load database/inventory.sql for the
-- stock ledger seed:
--
--   mysql -u root -e "CREATE DATABASE jayann_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root jayann_store < database/schema.sql
--   mysql -u root jayann_store < database/inventory.sql
--
-- The admin row is seeded with a bcrypt hash of "password123". Rotate it
-- before exposing this anywhere public.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
--
-- Database: `jayann_store`
--


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `user_id` int(100) unsigned NOT NULL,
  `pid` int(100) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `image` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_user_pid` (`user_id`,`pid`),
  KEY `idx_cart_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL DEFAULT '',
  `number` varchar(20) NOT NULL DEFAULT '',
  `message` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `orders`
--

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
  `placed_on` date NOT NULL DEFAULT current_timestamp(),
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `stock_returned` tinyint(1) NOT NULL DEFAULT 0,
  `order_date` date NOT NULL DEFAULT curdate(),
  `completed_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_ref` (`order_ref`),
  KEY `idx_orders_status` (`payment_status`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `product_archive`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `products`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `stock_movements`
--

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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_movements_product` (`product_id`,`id`),
  KEY `idx_movements_created` (`created_at`),
  KEY `idx_movements_reason` (`reason`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- --------------------------------------------------------
--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `email` varchar(120) NOT NULL DEFAULT '',
  `number` varchar(20) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `name`, `password`) VALUES (1,'admin','$2y$10$Pq/sc.TaE7DyYlbOGgGcA.1yLqST4twxDOKqD2CuQ3/ru4iqYxISS');
--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `price`, `discount`, `discount_price`, `image`, `stock`, `low_stock_threshold`, `cost_price`, `sku`, `supplier`, `last_restocked_on`) VALUES (1,'Kopiko 3in1 Coffee - Blanca Twin Pack 58g x 10 sachets','Snacks',12.00,0,12.00,'coffee.jpeg',0,5,7.20,'JYS-0001','',NULL),(2,'Summit Natural Drinking Water - 350ml','Beverages',27.00,0,27.00,'1734357572_summit.webp',222,5,16.20,'JYS-0002','',NULL),(3,'Buko Pandan Rice Green Sack - 25kg','Essentials',1351.00,2,1324.00,'2004895934-1.png',30,5,810.60,'JYS-0003','',NULL),(4,'ORAL B Soft 3D White Whitening Manual Toothbrush 3 Pieces','Personal Care',20.00,0,20.00,'H103397_1b88.png',200,5,12.00,'JYS-0004','',NULL),(5,'M.Y. San Sky Flakes Crackers Original - 25g x 10&#39;s','Snacks',200.00,0,200.00,'skyflakes.webp',5,5,120.00,'JYS-0005','',NULL),(6,'Colgate Maximum Cavity Protection  Toothpaste - 214g','Personal Care',20.00,0,20.00,'colgate-mcp-great-regular-flavor-toohpaste-214g-box.jpg',23,5,12.00,'JYS-0006','',NULL),(7,'Jack &#39;n Jill V Cut Potato Chips Spicy BBQ - 162g','Snacks',30.00,0,30.00,'1735896902_vcut.png',200,5,18.00,'JYS-0007','',NULL),(8,'Regent Cheese Ring Snacks 1 pcs - 60g','Snacks',20.00,5,19.00,'1735897083_cheeserings.webp',50,5,12.00,'JYS-0008','',NULL),(9,'SILKA Whitening Herbal Soap Green Papaya 135g','Personal Care',20.00,6,19.00,'1735898564_SILKA_Whitening_Herbal_Soap_Green_Papaya_135g.png.webp',100,5,12.00,'JYS-0009','',NULL),(10,'COCA-COLA Regular Mismo 290ml','Beverages',23.00,0,23.00,'1735898673_4801981118502COKE295MLP13.75_800x_1_-removebg-preview.png.webp',100,5,13.80,'JYS-0010','',NULL),(11,'PIATTOS Cheese Flavored Potato Chips - 40g','Snacks',20.00,5,19.00,'1735898720_Piattos-Cheese-40g.png.webp',19,5,12.00,'JYS-0011','',NULL),(12,'LUDY&#39;S SALABAT Ginger Brew Classic 8g 1&#39;s','Essentials',9.00,0,9.00,'1735898846_image_05b1afaa-7d73-48ac-b341-185a34257307_1_-removebg-preview.png.webp',20,5,5.40,'JYS-0012','',NULL),(13,'STING Energy Drink Strawberry Flavor 290ml 1&#39;s','Beverages',22.00,5,21.00,'1735898903_S733e34095ba64f44bd04c11ec53798634-removebg-preview.png.webp',99,5,13.20,'JYS-0013','',NULL),(14,'DR. S. WONG&#39;S SULFUR SOAP (Yellow) Soap - 135g','Personal Care',55.00,0,55.00,'1735898962_51vD7lOzp_L.jpg.webp',0,5,33.00,'JYS-0014','',NULL),(15,'MILCU Underarm and Foot Deodorant Powder 40g','Personal Care',57.00,0,57.00,'1735899037_10107001_milcu-underarm-and-foot-deo-pdr-40g_1_-removebg-preview.png.webp',57,5,34.20,'JYS-0015','',NULL),(16,'LISTERINE Mouthwash 250mL Cool Mint','Personal Care',155.00,0,155.00,'1735899097_81wkfQI6rnL.jpg.webp',0,5,93.00,'JYS-0016','',NULL),(17,'FEMME Bathroom Tissue 2 Ply 150 Pulls 300 Sheets 1&#39;s','Personal Care',12.00,0,12.00,'1735899146_4806502359754_1024x-removebg-preview[1].png.webp',100,5,7.20,'JYS-0017','',NULL),(18,'SISTERS Night Plus Heavy Flow with Wings 8 Pads','Personal Care',28.00,5,27.00,'1735899214_ezgif-4-cc15e1839f-removebg-preview[1].png.webp',49,5,16.80,'JYS-0018','',NULL),(19,'NESTOGEN 1 Infant Milk Formula For 0-6 Months 135g','Essentials',82.00,7,76.00,'1735899283_c5292204540082a4dc45aa231b2a5915.jpg.webp',100,5,49.20,'JYS-0019','',NULL),(20,'BEAR BRAND Junior 1+ Milk 1-3 years old 400g','Essentials',196.00,10,176.00,'1735899359_Bear_Brand_Junior_1__400g.png.webp',100,5,117.60,'JYS-0020','',NULL),(21,'BENCH FIX Professional Clay Doh -  25g','Personal Care',58.00,0,58.00,'1735899503_tcr1025e_fl_45_za_1-removebg-preview[1].png.webp',10,5,34.80,'JYS-0021','','2026-09-26 18:06:18'),(22,'DEL MONTE 100% Pineapple Juice Drink with A-C-E 220ml 1&#39;s','Beverages',35.00,0,35.00,'del.webp',100,5,21.00,'JYS-0022','',NULL),(23,'LUCKY ME Go Cup Batchoy Cup Noodles 40g','Snacks',26.00,0,26.00,'1735898790_lucky_me_go_cup_batchoy.jpg.webp',5,5,15.60,'JYS-0023','',NULL),(24,'Safeguard Family Germ Protection Soap','Personal Care',30.00,0,30.00,'safeguard.jpg',2,5,18.00,'JYS-0024','',NULL);
--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `number`, `password`, `address`) VALUES (1,'Angelito Decatoria','angelitodecatoriaa@gmail.com','23','5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8',''),(2,'Angelito P. Decatoria III','angelitodecatoria@gmail.com','09385100460','$2y$10$iUMSRAFDo2hvjxWw4Mj2euLSyZJmn7q.AF.AZOahG87B5onyp//LK','Blk 57 Lot 14, Hyacinth Residence');
