<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$dbConfig = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['name'],
    $dbConfig['charset']
);

$options = [

    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

try {
    $conn = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
} catch (PDOException $e) {

    error_log('Jayann Store DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        exit('Database connection failed. Is MySQL running, and is the database name in app/config.php correct?');
    }
    exit('<!DOCTYPE html><meta charset="utf-8">'
        . '<div style="font:16px/1.6 system-ui;max-width:38rem;margin:15vh auto;padding:2rem;'
        . 'border:1px solid #e5e7eb;border-radius:14px;text-align:center">'
        . '<h1 style="margin:0 0 .5rem">We could not reach the store</h1>'
        . '<p style="color:#6b7684;margin:0">Please try again in a few moments.</p>'
        . '</div>');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/inventory.php';
require_once __DIR__ . '/payments/paymongo.php';
require_once __DIR__ . '/views/shop/product_card.php';

class Db
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = [])
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }
}

$db = new Db($conn);
