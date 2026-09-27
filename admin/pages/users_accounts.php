<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'users';
$page_title = 'Customers';
$page_sub   = 'Everyone with an account on the storefront';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id  = (int) ($_POST['id'] ?? 0);
    $row = $id > 0 ? $db->one('SELECT * FROM users WHERE id = ?', [$id]) : null;

    if ($row) {
        if ((int) $row['id'] === current_admin_id()) {
            flash('error', 'That is not a customer account.');
        } else {
            $db->run('DELETE FROM orders WHERE user_id = ?', [$id]);
            $db->run('DELETE FROM cart WHERE user_id = ?', [$id]);
            $db->run('DELETE FROM users WHERE id = ?', [$id]);
            flash('success', $row['name'] . "'s account and order history were deleted.");
        }
    } else {
        flash('error', 'That customer no longer exists.');
    }

    redirect('users_accounts.php');
}

$q        = admin_q('q');
$perPage  = 20;
$whereSql = '';
$params   = [];

if ($q !== '') {
    $whereSql = 'WHERE u.name LIKE ? OR u.email LIKE ? OR u.number LIKE ?';
    $params   = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
}

$total  = (int) ($db->value("SELECT COUNT(*) FROM users u $whereSql", $params) ?? 0);
$page   = admin_page_no($total, $perPage);
$offset = ($page - 1) * $perPage;

$users = $db->all(
    "SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
            (SELECT COALESCE(SUM(o.total_price), 0) FROM orders o
              WHERE o.user_id = u.id AND o.payment_status IN ('paid','completed')) AS lifetime_value,
            (SELECT COUNT(*) FROM cart c WHERE c.user_id = u.id) AS cart_count
     FROM users u $whereSql
     ORDER BY u.id DESC LIMIT $perPage OFFSET $offset",
    $params
);

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Customers</h1>
        <p><?= e(plural($total, 'registered account')) ?>.</p>
    </div>
</div>

<form class="filters" method="get" action="users_accounts.php">
    <div class="searchinline">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input" type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search name, email or phone" aria-label="Search customers" data-search>
    </div>
    <div class="filters__count"><strong><?= e(number_format($total)) ?></strong> shown</div>
</form>

<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$users): ?>
            <?= admin_empty(
                'fa-users',
                $q !== '' ? 'No customer matches' : 'No accounts yet',
                $q !== '' ? 'Try a different search term.' : 'Customers appear here as soon as they register on the storefront.'
            ) ?>
        <?php else: ?>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Customer</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Address</th>
                        <th scope="col" class="num">Orders</th>
                        <th scope="col" class="num">Lifetime value</th>
                        <th scope="col" class="num">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <span class="cellproduct">
                                <span class="sidebar__avatar" aria-hidden="true"><?= e(strtoupper(substr((string) $u['name'], 0, 1))) ?></span>
                                <span>
                                    <strong><?= e($u['name']) ?></strong>
                                    <small><a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a></small>
                                </span>
                            </span>
                        </td>
                        <td style="white-space:nowrap"><?= e($u['number']) ?></td>
                        <td style="max-width:16rem">
                            <span style="display:block;color:var(--text-muted);font-size:var(--fs-sm)">
                                <?= $u['address'] !== '' ? e($u['address']) : 'â€”' ?>
                            </span>
                        </td>
                        <td class="num">
                            <strong><?= (int) $u['order_count'] ?></strong>
                            <?php if ((int) $u['cart_count'] > 0): ?>
                                <br><small style="color:var(--text-muted)"><?= (int) $u['cart_count'] ?> in cart</small>
                            <?php endif; ?>
                        </td>
                        <td class="num"><strong><?= e(money($u['lifetime_value'])) ?></strong></td>
                        <td class="num">
                            <div class="rowactions" style="justify-content:flex-end">
                                <form method="post" action="users_accounts.php"
                                      data-confirm="Delete <?= e($u['name']) ?>? Their orders and cart are deleted too, and this cannot be undone.">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn--sm btn--danger" type="submit">
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
