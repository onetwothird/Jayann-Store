<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'orders';
$page_title = 'Orders';
$page_sub   = 'Review payments and move orders along';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['form_action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    $order = $id > 0 ? $db->one('SELECT * FROM orders WHERE id = ?', [$id]) : null;
    if (!$order) {
        flash('error', 'That order no longer exists.');
        redirect('placed_orders.php');
    }

    if ($action === 'status') {
        $status = (string) ($_POST['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'paid', 'completed', 'cancelled'], true)) {
            $status = 'pending';
        }

        $db->run(
            'UPDATE orders SET payment_status = ?, completed_on = ? WHERE id = ?',
            [$status, in_array($status, ['paid', 'completed'], true) ? date('Y-m-d H:i:s') : null, $id]
        );

        $meta = order_status_meta($status);
        flash('success', $order['order_ref'] . ' is now marked as ' . strtolower($meta['label']) . '.');
    } elseif ($action === 'delete') {
        $db->run('DELETE FROM orders WHERE id = ?', [$id]);
        flash('success', 'Order ' . $order['order_ref'] . ' was deleted.');
    }

    redirect('placed_orders.php' . (isset($_POST['back_status']) ? '?status=' . urlencode((string) ($_POST['back_status'] ?? '')) : ''));
}

$statusFilter = admin_q('status');
$q            = admin_q('q');
$perPage      = 20;

$where  = [];
$params = [];

if (in_array($statusFilter, ['pending', 'paid', 'completed', 'cancelled'], true)) {
    $where[]  = 'o.payment_status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[]  = '(o.order_ref LIKE ? OR o.name LIKE ? OR o.email LIKE ? OR o.number LIKE ?)';
    $like     = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$counts = [];
foreach ($db->all('SELECT payment_status, COUNT(*) AS n FROM orders GROUP BY payment_status') as $r) {
    $counts[(string) $r['payment_status']] = (int) $r['n'];
}
$counts['all'] = array_sum($counts);

$total = (int) ($db->value("SELECT COUNT(*) FROM orders o $whereSql", $params) ?? 0);
$page  = admin_page_no($total, $perPage);
$offset = ($page - 1) * $perPage;

$orders = $db->all(
    "SELECT o.* FROM orders o $whereSql ORDER BY o.id DESC LIMIT $perPage OFFSET $offset",
    $params
);

$statusTabs = [
    ''          => 'All',
    'pending'   => 'Pending',
    'paid'      => 'Paid',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Orders</h1>
        <p><?= e(plural($total, 'order')) ?> <?= $statusFilter !== '' ? 'with status â€œ' . e($statusFilter) . 'â€' : 'in total' ?>.</p>
    </div>
</div>

<!-- ================================================================ filters -->
<form class="filters" method="get" action="placed_orders.php">
    <div class="searchinline">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input" type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search reference, name, email or phone" aria-label="Search orders" data-search>
    </div>
    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
    <div class="filters__count"><strong><?= e(number_format($total)) ?></strong> shown</div>
</form>

<div class="filters">
    <?php foreach ($statusTabs as $value => $label): ?>
        <?php $n = $value === '' ? ($counts['all'] ?? 0) : ($counts[$value] ?? 0); ?>
        <a class="chip<?= $statusFilter === $value ? ' is-active' : '' ?>"
           href="<?= e(admin_url(['status' => $value ?: null, 'q' => $q ?: null, 'page' => null])) ?>">
            <?= e($label) ?> <span style="opacity:.7"><?= (int) $n ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ================================================================ listing -->
<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$orders): ?>
            <?= admin_empty(
                'fa-receipt',
                'No orders here',
                $q !== '' || $statusFilter !== ''
                    ? 'No order matches the current filter. Try clearing it.'
                    : 'Customer orders will appear here as soon as they check out.',
                '<a class="btn btn--ghost btn--sm" href="placed_orders.php">Clear filters</a>'
            ) ?>
        <?php else: ?>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Items</th>
                        <th scope="col">Payment</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <?php
                    $meta  = order_status_meta((string) $o['payment_status']);
                    $lines = order_lines($o);
                    $items = $lines ? e(format_order_items((string) $o['total_products'])) : 'â€”';
                    ?>
                    <tr>
                        <td>
                            <a href="order_view.php?id=<?= (int) $o['id'] ?>" style="font-weight:600">
                                <?= e($o['order_ref'] ?: ('#' . $o['id'])) ?>
                            </a>
                            <small style="display:block;color:var(--text-muted)"><?= e(nice_date((string) $o['order_date'])) ?></small>
                        </td>
                        <td>
                            <strong><?= e($o['name']) ?></strong>
                            <small style="display:block;color:var(--text-muted)"><?= e($o['email']) ?></small>
                            <small style="display:block;color:var(--text-soft)"><?= e($o['number']) ?></small>
                        </td>
                        <td style="max-width:18rem">
                            <span style="display:block;color:var(--text-muted);font-size:var(--fs-sm)"><?= $items ?></span>
                        </td>
                        <td>
                            <span class="tag">
                                <i class="fa-solid <?= e(payment_method_icon((string) $o['method'])) ?>" aria-hidden="true"></i>
                                <?= e(payment_method_label((string) $o['method'])) ?>
                            </span>
                        </td>
                        <td class="num">
                            <strong><?= e(money($o['total_price'])) ?></strong>
                            <?php if ((float) $o['shipping_fee'] > 0): ?>
                                <small style="display:block;color:var(--text-muted)">incl. <?= e(money($o['shipping_fee'])) ?> delivery</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span></td>
                        <td class="num">
                            <div class="rowactions" style="justify-content:flex-end">
                                <form method="post" action="placed_orders.php" class="no-print">
                                    <input type="hidden" name="form_action" value="status">
                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                    <input type="hidden" name="back_status" value="<?= e($statusFilter) ?>">
                                    <label class="sr-only" for="st-<?= (int) $o['id'] ?>">Status for <?= e($o['order_ref']) ?></label>
                                    <select class="select" id="st-<?= (int) $o['id'] ?>" name="status"
                                            style="width:auto;padding-block:.3125rem;font-size:var(--fs-sm)">
                                        <?= admin_status_options((string) $o['payment_status']) ?>
                                    </select>
                                    <button class="btn btn--sm" type="submit" title="Save status">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <a class="btn btn--sm btn--ghost" href="order_view.php?id=<?= (int) $o['id'] ?>">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <form method="post" action="placed_orders.php"
                                      data-confirm="Delete order <?= e($o['order_ref']) ?> permanently?">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                    <button class="btn btn--sm btn--danger" type="submit" title="Delete order">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?= admin_pager($page, $total, $perPage) ?>
</section>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
