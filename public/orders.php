<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login('orders.php');

$userId = current_user_id();
$user   = current_user();

$filter = (string) ($_GET['status'] ?? 'all');
$allowed = ['all', 'pending', 'paid', 'completed', 'cancelled'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}

$params = [$userId];
$where  = 'user_id = ?';
if ($filter !== 'all') {
    $where .= ' AND payment_status = ?';
    $params[] = $filter;
}

$orders = $db->all("SELECT * FROM orders WHERE $where ORDER BY id DESC", $params);

$counts = ['all' => 0, 'pending' => 0, 'paid' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($db->all('SELECT payment_status, COUNT(*) AS n FROM orders WHERE user_id = ? GROUP BY payment_status',
                   [$userId]) as $row) {
    $status = (string) $row['payment_status'];
    $counts['all'] += (int) $row['n'];
    if (array_key_exists($status, $counts)) {
        $counts[$status] = (int) $row['n'];
    }
}

$tabs = [
    'all'       => 'All orders',
    'pending'   => 'Awaiting payment',
    'paid'      => 'Paid',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$pageTitle = 'My orders';
$pageDesc  = 'Track and review every order you have placed at Jayann\'s Store.';
$pageClass = 'page-orders';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>My orders</span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">

        <div class="sechead sechead--split">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Order history</span>
                <h1 class="sechead__title">My orders</h1>
                <p class="sechead__sub">
                    <?= $counts['all'] > 0
                        ? plural($counts['all'], 'order') . ' placed so far.'
                        : 'Your order history will appear here.' ?>
                </p>
            </div>
            <a class="btn btn--ghost btn--sm" href="products.php">
                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Shop again
            </a>
        </div>

        <?php if ($counts['all'] > 1): ?>
            <div class="filters" role="group" aria-label="Filter orders by status">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="chip<?= $filter === $key ? ' is-active' : '' ?>"
                       href="orders.php?status=<?= e($key) ?>">
                        <?= e($label) ?>
                        <?php if ($counts[$key] > 0): ?><span class="chip__n"><?= $counts[$key] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$orders): ?>
            <div class="empty">
                <span class="empty__icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                <h2 class="empty__title">
                    <?= $counts['all'] > 0 ? 'Nothing in this filter' : 'No orders yet' ?>
                </h2>
                <p class="empty__text">
                    <?= $counts['all'] > 0
                        ? 'Try a different status tab to see your other orders.'
                        : 'When you place an order you can track its status and download the receipt here.' ?>
                </p>
                <?php if ($counts['all'] > 0): ?>
                    <a class="btn" href="orders.php">Show all orders</a>
                <?php else: ?>
                    <a class="btn btn--lg" href="products.php">
                        <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Start shopping
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <div class="orderlist">
                <?php foreach ($orders as $order):
                    $meta = order_status_meta((string) $order['payment_status']);
                ?>
                    <article class="ordercard">
                        <header class="ordercard__head">
                            <div class="ordercard__ident">
                                <span class="ordercard__id"><?= e($order['order_ref'] ?: ('#' . $order['id'])) ?></span>
                                <span class="ordercard__date">Placed <?= nice_date((string) $order['order_date']) ?></span>
                            </div>
                            <span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
                        </header>

                        <div class="ordercard__body">
                            <ul class="orderitems">
                                <?php foreach (order_lines($order) as $line): ?>
                                    <li class="orderitem">
                                        <img class="orderitem__img" src="<?= e(product_image($line['image'])) ?>"
                                             alt="" loading="lazy">
                                        <div class="orderitem__body">
                                            <span class="orderitem__name"><?= e($line['name']) ?></span>
                                            <span class="orderitem__qty">
                                                Qty <?= (int) $line['quantity'] ?>
                                                <?php if ($line['unit'] !== null): ?>
                                                    &middot; <?= money($line['unit']) ?> each
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <dl class="ordermeta">
                                <div><dt>Payment</dt><dd><?= e(payment_method_label((string) $order['method'])) ?></dd></div>
                                <div><dt>Deliver to</dt><dd><?= e($order['name']) ?> &middot; <?= e($order['number']) ?></dd></div>
                                <div><dt>Address</dt><dd><?= e($order['address']) ?></dd></div>
                                <?php if (!empty($order['payment_reference'])): ?>
                                    <div>
                                        <dt>Reference</dt>
                                        <dd><code><?= e($order['payment_reference']) ?></code></dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($order['completed_on'])): ?>
                                    <div><dt>Completed</dt><dd><?= nice_date((string) $order['completed_on'], true) ?></dd></div>
                                <?php endif; ?>
                            </dl>
                        </div>

                        <footer class="ordercard__foot">
                            <div class="ordercard__total">
                                <span>Total</span>
                                <b><?= money($order['total_price']) ?></b>
                            </div>
                            <div class="ordercard__actions">
                                <a class="btn btn--ghost btn--sm" href="receipt.php?order=<?= (int) $order['id'] ?>">
                                    <i class="fa-regular fa-file-lines" aria-hidden="true"></i> View receipt
                                </a>
                                <a class="btn btn--sm" href="download_receipt.php?order=<?= (int) $order['id'] ?>">
                                    <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i> Download PDF
                                </a>
                                <br>
                            </div>                  
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>

