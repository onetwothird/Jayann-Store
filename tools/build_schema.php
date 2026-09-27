<?php

declare(strict_types=1);

$root   = dirname(__DIR__);
$target = $root . '/database/schema.sql';
$dump   = 'C:/xampp/mysql/bin/mysqldump.exe';

function dump(string $exe, string $args): string
{
    $cmd = escapeshellarg($exe) . ' ' . $args . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    if ($code !== 0) {
        fwrite(STDERR, "mysqldump failed ($code):\n" . implode("\n", $out) . "\n");
        exit(1);
    }

    return implode("\n", $out) . "\n";
}

$struct = dump($dump, '-u root --no-data --add-drop-table --skip-comments jayann_store');
$seed   = dump($dump, '-u root --no-create-info --complete-insert --skip-comments --compact jayann_store admin products users');

$struct = preg_replace(
    '/^(CREATE TABLE `(\w+)`)/m',
    "-- --------------------------------------------------------\n--\n-- Table structure for table `$2`\n--\n\n$1",
    $struct
) ?? $struct;

$seed = preg_replace(
    "/^INSERT INTO `(\w+)`/m",
    "--\n-- Dumping data for table `$1`\n--\n\nINSERT INTO `$1`",
    $seed
) ?? $seed;

$header = <<<'SQL'
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

SQL;

$sql = $header
    . "\n"
    . rtrim($struct)
    . "\n\n"
    . rtrim($seed)
    . "\n";

file_put_contents($target, $sql);

printf("wrote %s (%d bytes)\n", $target, strlen($sql));

$tables = preg_match_all('/CREATE TABLE `(\w+)`/', $sql, $m) ? $m[1] : [];
printf("tables: %s\n", implode(', ', $tables));
printf("seed inserts: %d\n", preg_match_all('/^INSERT INTO/m', $sql));
printf("admin hash is bcrypt: %s\n", str_contains($sql, "\$2y\$") ? 'yes' : 'NO');
