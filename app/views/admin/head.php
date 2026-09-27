<?php declare(strict_types=1);

if (!defined('JAYANN_ADMIN_HEAD')) {
    define('JAYANN_ADMIN_HEAD', true);
}

require_once __DIR__ . '/helpers.php';

$admin_page = $admin_page ?? '';
$page_title = $page_title ?? "Jayann's Store Admin";
$page_sub   = $page_sub   ?? '';

$admin_user = current_admin() ?? ['name' => 'Admin'];

$admin_nav = [
    [
        'key'   => 'dashboard',
        'file'  => 'dashboard.php',
        'icon'  => 'fa-chart-line',
        'label' => 'Dashboard',
    ],
    [
        'key'   => 'products',
        'file'  => 'products.php',
        'icon'  => 'fa-basket-shopping',
        'label' => 'Products',
        'count' => (int) ($db->value('SELECT COUNT(*) FROM products') ?? 0),
    ],
    [
        'key'   => 'inventory',
        'file'  => 'inventory.php',
        'icon'  => 'fa-boxes-stacked',
        'label' => 'Inventory',
        'count' => inventory_needs_restock_count(),
    ],
    [
        'key'   => 'stock',
        'file'  => 'stock_movements.php',
        'icon'  => 'fa-clock-rotate-left',
        'label' => 'Stock log',
    ],
    [
        'key'   => 'orders',
        'file'  => 'placed_orders.php',
        'icon'  => 'fa-receipt',
        'label' => 'Orders',
        'count' => (int) ($db->value('SELECT COUNT(*) FROM orders') ?? 0),
    ],
    [
        'key'   => 'archive',
        'file'  => 'product_archive.php',
        'icon'  => 'fa-box-archive',
        'label' => 'Archive',
    ],
    [
        'key'   => 'users',
        'file'  => 'users_accounts.php',
        'icon'  => 'fa-users',
        'label' => 'Customers',
    ],
    [
        'key'   => 'messages',
        'file'  => 'contact.php',
        'icon'  => 'fa-envelope',
        'label' => 'Messages',
        'count' => (int) ($db->value('SELECT COUNT(*) FROM messages') ?? 0),
    ],
];

$admin_nav_groups = [
    'Store'  => ['dashboard', 'products', 'orders', 'archive'],
    'Stock'  => ['inventory', 'stock'],
    'People' => ['users', 'messages', 'admins'],
];

$admin_extra_nav = [
    'key'   => 'admins',
    'file'  => 'admin_accounts.php',
    'icon'  => 'fa-user-shield',
    'label' => 'Admins',
    'count' => (int) ($db->value('SELECT COUNT(*) FROM admin') ?? 0),
];

$admin_all_nav = array_merge($admin_nav, [$admin_extra_nav]);

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?> &middot; <?= e($config['store']['legal']) ?></title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/storenijayann.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<?= render_css('admin') ?>
</head>
<body>

<?php  ?>
<?php foreach ($flashes as $f): ?>
<div data-flash="<?= e($f['message']) ?>" data-flash-type="<?= e($f['type']) ?>" hidden></div>
<?php endforeach; ?>

<div class="admin">

    <aside class="sidebar" data-sidebar aria-label="Admin navigation">
        <a class="sidebar__brand" href="dashboard.php">
            <img src="<?= BASE_URL ?>assets/img/storenijayann.png" alt="">
            <span>
                <strong><?= e($config['store']['legal']) ?></strong>
                <small>Admin panel</small>
            </span>
        </a>

        <?php foreach ($admin_nav_groups as $group_label => $keys): ?>
        <nav class="sidebar__nav" aria-label="<?= e($group_label) ?>">
            <p class="sidebar__label"><?= e($group_label) ?></p>
            <?php foreach ($admin_all_nav as $item): ?>
                <?php if (!in_array($item['key'], $keys, true)) continue; ?>
                <a class="sidebar__link<?= $admin_page === $item['key'] ? ' is-active' : '' ?>"
                   href="<?= e($item['file']) ?>"
                   <?= $admin_page === $item['key'] ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e($item['label']) ?></span>
                    <?php if (!empty($item['count'])): ?>
                        <span class="sidebar__count"><?= e(number_format($item['count'])) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endforeach; ?>

        <div class="sidebar__foot">
            <div class="sidebar__user">
                <span class="sidebar__avatar" aria-hidden="true"><?= e(strtoupper(substr((string) $admin_user['name'], 0, 1))) ?></span>
                <span>
                    <strong><?= e($admin_user['name']) ?></strong>
                    <small>Administrator</small>
                </span>
            </div>
            <a class="sidebar__link" href="update_profile.php">
                <i class="fa-solid fa-user-pen" aria-hidden="true"></i><span>My profile</span>
            </a>
            <a class="sidebar__link" href="../../../Jayann_Store/public/home.php" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>View store</span>
            </a>
            <a class="sidebar__logout" href="logout.php">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Sign out</span>
            </a>
        </div>
    </aside>

    <div class="scrim" data-scrim hidden></div>

    <div class="admin__main">
        <header class="topbar">
            <div class="topbar__inner">
                <button class="iconbtn topbar__burger" type="button" data-sidebar-open aria-expanded="false" aria-label="Open navigation">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <div>
                    <p class="topbar__title"><?= e($page_title) ?></p>
                    <?php if ($page_sub !== ''): ?>
                        <p class="topbar__sub"><?= e($page_sub) ?></p>
                    <?php endif; ?>
                </div>
                <div class="topbar__right">
                    <a class="iconbtn" href="messages.php" title="Messages" aria-label="Messages">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    </a>
                    <a class="iconbtn" href="update_profile.php" title="My profile" aria-label="My profile">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </header>

        <main class="admin__body">

