<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page   = 'dashboard';
$page_title   = 'Dashboard';
$page_sub     = 'Store performance at a glance';
$today        = date('Y-m-d');
$month_start  = date('Y-m-01');

$revenue_today = (float) ($db->value(
    "SELECT COALESCE(SUM(total_price), 0) FROM orders
     WHERE payment_status IN ('paid','completed') AND DATE(COALESCE(completed_on, order_date)) = ?",
    [$today]
) ?? 0);

$revenue_month = (float) ($db->value(
    "SELECT COALESCE(SUM(total_price), 0) FROM orders
     WHERE payment_status IN ('paid','completed')
       AND DATE(COALESCE(completed_on, order_date)) >= ?",
    [$month_start]
) ?? 0);

$order_count   = (int) ($db->value('SELECT COUNT(*) FROM orders') ?? 0);
$pending_count = (int) ($db->value("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'") ?? 0);
$product_count = (int) ($db->value('SELECT COUNT(*) FROM products') ?? 0);
$customer_count = (int) ($db->value('SELECT COUNT(*) FROM users') ?? 0);
$message_count  = (int) ($db->value('SELECT COUNT(*) FROM messages') ?? 0);

$inv          = inventory_summary();
$out_of_stock = $inv['out'];
$low_stock    = $inv['low'];
$stock_value  = $inv['value_cost'];

$avg_order = $order_count > 0
    ? (float) ($db->value("SELECT AVG(total_price) FROM orders WHERE payment_status IN ('paid','completed')") ?? 0)
    : 0.0;

$series = [];
$stmt   = $db->run(
    "SELECT DATE(COALESCE(completed_on, order_date)) AS d, SUM(total_price) AS total
     FROM orders
     WHERE payment_status IN ('paid','completed')
       AND DATE(COALESCE(completed_on, order_date)) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY d",
    []
);
$byDay = [];
foreach ($stmt as $row) {
    $byDay[(string) $row['d']] = (float) $row['total'];
}
for ($i = 13; $i >= 0; $i--) {
    $day     = date('Y-m-d', strtotime("-$i days"));
    $series[] = $byDay[$day] ?? 0.0;
}

$recent_orders = $db->all(
    'SELECT o.*, u.name AS user_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC
     LIMIT 8'
);

$low_products = inventory_reorder_list(8);

$recent_messages = $db->all('SELECT * FROM messages ORDER BY id DESC LIMIT 5');

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <?= e(nice_date($today)) ?>
        </span>
        <h1>Good to see you, <?= e(explode(' ', (string) ($_SESSION['admin_name'] ?? 'admin'))[0]) ?>.</h1>
        <p>Here is how the store is doing right now.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="../index.php" target="_blank" rel="noopener">
            <i class="fa-solid fa-store" aria-hidden="true"></i> View store
        </a>
        <a class="btn" href="products.php">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add product
        </a>
    </div>
</div>

<div class="statgrid">
    <article class="stat">
        <span class="stat__icon" aria-hidden="true"><i class="fa-solid fa-peso-sign"></i></span>
        <div>
            <p class="stat__label">Revenue today</p>
            <p class="stat__value"><?= e(money($revenue_today)) ?></p>
            <p class="stat__note"><?= e(money($revenue_month)) ?> so far this month</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon stat__icon--info" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
        <div>
            <p class="stat__label">Orders</p>
            <p class="stat__value"><?= e(number_format($order_count)) ?></p>
            <p class="stat__note"><?= e(money($avg_order)) ?> average order</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon <?= $pending_count > 0 ? 'stat__icon--warn' : 'stat__icon--success' ?>" aria-hidden="true">
            <i class="fa-solid fa-hourglass-half"></i>
        </span>
        <div>
            <p class="stat__label">Awaiting payment</p>
            <p class="stat__value"><?= e(number_format($pending_count)) ?></p>
            <p class="stat__note"><?= $pending_count > 0 ? 'Needs your attention' : 'All caught up' ?></p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon stat__icon--info" aria-hidden="true">
            <i class="fa-solid fa-warehouse"></i>
        </span>
        <div>
            <p class="stat__label">Stock value</p>
            <p class="stat__value"><?= e(money($stock_value)) ?></p>
            <p class="stat__note"><?= e(number_format($inv['units'])) ?> units on hand</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon <?= $out_of_stock > 0 ? 'stat__icon--danger' : 'stat__icon--success' ?>" aria-hidden="true">
            <i class="fa-solid fa-box"></i>
        </span>
        <div>
            <p class="stat__label">Catalogue</p>
            <p class="stat__value"><?= e(number_format($product_count)) ?></p>
            <p class="stat__note">
                <?php if ($out_of_stock + $low_stock > 0): ?>
                    <a href="inventory.php?filter=low" style="color:inherit;text-decoration:underline">
                        <?= e(number_format($out_of_stock + $low_stock)) ?> need<?= $out_of_stock + $low_stock === 1 ? 's' : '' ?> restocking
                    </a>
                <?php else: ?>
                    Stock levels healthy
                <?php endif; ?>
            </p>
        </div>
    </article>
</div>

<div class="grid-2">

    <section class="card">
        <div class="card__head">
            <h2 class="card__title">Settled revenue</h2>
            <span class="tag">Last 14 days</span>
        </div>
        <div class="card__body">
            <?php
            $peak = max($series);
            $sum  = array_sum($series);
            ?>
            <p class="stat__value" style="font-size:var(--fs-3xl)"><?= e(money($sum)) ?></p>
            <p class="stat__note" style="margin-bottom:var(--sp-4)">
                Best day: <?= e(money($peak)) ?> &middot; average <?= e(money($sum / 14)) ?>
            </p>
            <canvas data-sparkline data-values="<?= e(implode(',', array_map(static fn($v) => round($v, 2), $series))) ?>"
                    width="600" height="120" role="img"
                    aria-label="Settled revenue over the last 14 days"></canvas>
            <?php if ($sum <= 0): ?>
                <p class="field__hint" style="margin-top:var(--sp-3)">
                    Mark an order as <strong>paid</strong> or <strong>completed</strong> and it will appear here.
                </p>
            <?php endif; ?>
        </div>

        <div class="card__head" style="border-top:1px solid var(--border)">
            <h2 class="card__title">Recent orders</h2>
            <a class="btn btn--ghost btn--sm" href="placed_orders.php">View all</a>
        </div>

        <?php if (!$recent_orders): ?>
            <?= admin_empty('fa-receipt', 'No orders yet', 'When a customer checks out, their order will land here.', '<a class="btn btn--soft btn--sm" href="../products.php" target="_blank" rel="noopener">Browse the storefront</a>') ?>
        <?php else: ?>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Placed</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_orders as $o): ?>
                    <?php $meta = order_status_meta((string) $o['payment_status']); ?>
                    <tr>
                        <td>
                            <a class="btn btn--sm btn--soft" href="order_view.php?id=<?= (int) $o['id'] ?>">
                                <?= e($o['order_ref'] ?: ('#' . $o['id'])) ?>
                            </a>
                        </td>
                        <td>
                            <strong><?= e($o['name']) ?></strong>
                            <small style="display:block;color:var(--text-muted)"><?= e($o['email']) ?></small>
                        </td>
                        <td style="white-space:nowrap"><?= e(nice_date((string) $o['order_date'])) ?></td>
                        <td><span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span></td>
                        <td class="num"><strong><?= e(money($o['total_price'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <div>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Needs restocking</h2>
                <a class="btn btn--ghost btn--sm" href="products.php?filter=out">All</a>
            </div>
            <?php if (!$low_products): ?>
                <?= admin_empty('fa-circle-check', 'Stock is healthy', 'No product is at or below its reorder threshold.') ?>
            <?php else: ?>
            <div class="card__body card__body--flush">
                <div class="tablewrap">
                    <table class="table">
                        <tbody>
                        <?php foreach ($low_products as $p): ?>
                            <?php $st = stock_status((int) $p['stock'], (int) $p['low_stock_threshold']); ?>
                            <tr>
                                <td>
                                    <span class="cellproduct">
                                        <img src="../<?= e(product_image($p['image'])) ?>" alt="">
                                        <span>
                                            <strong><?= e($p['name']) ?></strong>
                                            <small>
                                                <?= e($p['sku']) ?> &middot; <?= e($p['category']) ?>
                                                <?php if ((int) $p['low_stock_threshold'] > 0): ?>
                                                    &middot; reorder at <?= (int) $p['low_stock_threshold'] ?>
                                                <?php endif; ?>
                                            </small>
                                        </span>
                                    </span>
                                </td>
                                <td class="num">
                                    <span class="tag tag--<?= e($st['tag']) ?>"><?= e($st['label']) ?></span>
                                    <br>
                                    <a class="btn btn--sm btn--ghost" style="margin-top:var(--sp-2)"
                                       href="product_stock.php?id=<?= (int) $p['id'] ?>">Restock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Latest messages</h2>
                <a class="btn btn--ghost btn--sm" href="messages.php">View all</a>
            </div>
            <?php if (!$recent_messages): ?>
                <?= admin_empty('fa-envelope', 'No messages', 'Contact-form submissions will appear here.') ?>
            <?php else: ?>
            <div class="card__body card__body--flush">
                <div class="tablewrap">
                    <table class="table">
                        <tbody>
                        <?php foreach ($recent_messages as $m): ?>
                            <tr>
                                <td>
                                    <strong><?= e($m['name']) ?></strong>
                                    <small style="display:block;color:var(--text-muted)"><?= e($m['email']) ?></small>
                                </td>
                                <td class="num">
                                    <span style="display:block;max-width:22ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        <?= e($m['message']) ?>
                                    </span>
                                    <a class="btn btn--sm btn--ghost" style="margin-top:var(--sp-2)" href="messages.php">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Shortcuts</h2>
            </div>
            <div class="card__body" style="display:grid;gap:var(--sp-2)">
                <a class="btn btn--ghost btn--block" href="products.php" style="justify-content:flex-start">
                    <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i> Manage products
                    <span class="sidebar__count" style="margin-left:auto"><?= (int) $product_count ?></span>
                </a>
                <a class="btn btn--ghost btn--block" href="users_accounts.php" style="justify-content:flex-start">
                    <i class="fa-solid fa-users" aria-hidden="true"></i> Customers
                    <span class="sidebar__count" style="margin-left:auto"><?= (int) $customer_count ?></span>
                </a>
                <a class="btn btn--ghost btn--block" href="messages.php" style="justify-content:flex-start">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i> Messages
                    <span class="sidebar__count" style="margin-left:auto"><?= (int) $message_count ?></span>
                </a>
                <a class="btn btn--ghost btn--block" href="register_admin.php" style="justify-content:flex-start">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add an administrator
                </a>
            </div>
        </section>

    </div>
</div>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
