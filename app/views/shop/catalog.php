<?php declare(strict_types=1);

$sortOptions = [
    'popular'  => 'Most popular',
    'newest'   => 'Newest arrivals',
    'price_asc'=> 'Price: low to high',
    'price_desc' => 'Price: high to low',
    'name'     => 'Name: A to Z',
    'discount' => 'Biggest discount',
];

$searchQ   = trim((string) ($_GET['q'] ?? ''));
$category  = trim((string) ($_GET['category'] ?? ''));
$minPrice  = isset($_GET['min']) && $_GET['min'] !== '' ? max(0, (float) $_GET['min']) : null;
$maxPrice  = isset($_GET['max']) && $_GET['max'] !== '' ? max(0, (float) $_GET['max']) : null;
$inStock   = !empty($_GET['stock']);
$onSale    = !empty($_GET['sale']);
$sort      = (string) ($_GET['sort'] ?? 'popular');
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 12;

if (!isset($sortOptions[$sort])) {
    $sort = 'popular';
}

if ($category !== '') {
    $known = array_column(product_categories(), 'category');
    if (!in_array($category, $known, true)) {
        $category = '';
    }
}

$where  = [];
$params = [];

if ($searchQ !== '') {
    $where[] = '(p.name LIKE ? OR p.category LIKE ?)';
    $like = '%' . $searchQ . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($category !== '') {
    $where[] = 'p.category = ?';
    $params[] = $category;
}

if ($minPrice !== null) {
    $where[] = 'COALESCE(NULLIF(p.discount_price, 0), p.price) >= ?';
    $params[] = $minPrice;
}

if ($maxPrice !== null) {
    $where[] = 'COALESCE(NULLIF(p.discount_price, 0), p.price) <= ?';
    $params[] = $maxPrice;
}

if ($inStock) {
    $where[] = 'p.stock > 0';
}

if ($onSale) {
    $where[] = 'p.discount > 0 AND p.discount_price > 0 AND p.discount_price < p.price';
}

$orderBy = match ($sort) {
    'newest'     => 'p.id DESC',
    'price_asc'  => 'COALESCE(NULLIF(p.discount_price, 0), p.price) ASC, p.name ASC',
    'price_desc' => 'COALESCE(NULLIF(p.discount_price, 0), p.price) DESC, p.name ASC',
    'name'       => 'p.name ASC',
    'discount'   => '(p.price - COALESCE(NULLIF(p.discount_price, 0), p.price)) / NULLIF(p.price, 0) DESC, p.id DESC',

    default      => '(p.discount > 0 AND p.discount_price < p.price) DESC, p.stock DESC, p.id DESC',
};

$whereSql = 'WHERE ' . ($where ? implode(' AND ', $where) : '1=1');

$total = (int) $db->value("SELECT COUNT(*) FROM products p $whereSql", $params);
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$items = $db->all(
    "SELECT p.* FROM products p $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset",
    $params
);

$filters = [
    'q'        => $searchQ,
    'category' => $category,
    'min'      => $minPrice,
    'max'      => $maxPrice,
    'stock'    => $inStock,
    'sale'     => $onSale,
];

function catalog_url(array $overrides = []): string
{
    global $filters, $sort, $page;

    $query = array_filter([
        'q'        => $filters['q'],
        'category' => $filters['category'],
        'min'      => $filters['min'],
        'max'      => $filters['max'],
        'stock'    => $filters['stock'] ?: null,
        'sale'     => $filters['sale'] ?: null,
        'sort'     => $sort !== 'popular' ? $sort : null,
        'page'     => $page > 1 ? $page : null,
    ], static fn($v) => $v !== null && $v !== '');

    if (isset($overrides['page'])) {
        $query['page'] = $overrides['page'] > 1 ? (int) $overrides['page'] : null;
    }
    foreach ($overrides as $key => $value) {
        if ($key === 'page') {
            continue;
        }
        $query[$key] = ($value === null || $value === '') ? null : $value;
    }

    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');

    $base = basename($_SERVER['SCRIPT_NAME'] ?? 'products.php');
    return $query ? $base . '?' . http_build_query($query) : $base;
}

