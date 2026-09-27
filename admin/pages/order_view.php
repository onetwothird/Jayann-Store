<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$id = admin_qint('id');
$order = $id > 0
    ? $db->one(
        'SELECT o.*, u.name AS user_name, u.email AS user_email
         FROM orders o LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?',
        [$id]
    )
    : null;

if (!$order) {
    flash('error', 'That order could not be found.');
    redirect('placed_orders.php');
}

$admin_page = 'orders';
$page_title = 'Order ' . ($order['order_ref'] ?: ('#' . $order['id']));
$page_sub   = 'Placed ' . nice_date((string) $order['order_date'], true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['form_action'] ?? '') === 'status') {
    $status = (string) ($_POST['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'paid', 'completed', 'cancelled'], true)) {
        $status = 'pending';
    }

    $was        = (string) $order['payment_status'];
    $cancelling = $status === 'cancelled' && $was !== 'cancelled';

    $db->run(
        'UPDATE orders SET payment_status = ?, completed_on = ? WHERE id = ?',
        [$status, in_array($status, ['paid', 'completed'], true) ? date('Y-m-d H:i:s') : null, $id]
    );

    $message = $order['order_ref'] . ' is now marked as ' . strtolower(order_status_meta($status)['label']) . '.';

    if ($cancelling) {
        if ((int) ($order['stock_returned'] ?? 0) === 1) {
            $message .= ' Its stock was already returned earlier, so nothing was added back.';
        } else {
            $back    = restock_cancelled_order($order, (string) (current_admin()['name'] ?? 'admin'));
            $message .= ' ' . $back['message'];
        }
    }

    flash('success', $message);
    redirect('order_view.php?id=' . $id);
}

$lines = order_lines($order);
$meta  = order_status_meta((string) $order['payment_status']);

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head no-print">
    <div>
        <p class="eyebrow">
            <a href="placed_orders.php" style="color:inherit">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Orders
            </a>
        </p>
        <h1><?= e($page_title) ?></h1>
        <p>
            <span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="order_receipt.php?id=<?= (int) $order['id'] ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-print" aria-hidden="true"></i> Print
        </a>
    </div>
</div>

<div class="grid-2">
    <div>
        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Items</h2>
                <span class="tag"><?= e(plural(count($lines), 'line')) ?></span>
            </div>
            <div class="card__body card__body--flush">
                <?php if (!$lines): ?>
                    <?= admin_empty('fa-box-open', 'No item detail', 'This order was stored without a readable item list.') ?>
                <?php else: ?>
                <div class="tablewrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col" class="num">Unit</th>
                                <th scope="col" class="num">Qty</th>
                                <th scope="col" class="num">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td>
                                    <span class="cellproduct">
                                        <img src="../<?= e(product_image($line['image'])) ?>" alt="" loading="lazy">
                                        <span><strong><?= e($line['name']) ?></strong></span>
                                    </span>
                                </td>
                                <td class="num"><?= $line['unit'] !== null ? e(money($line['unit'])) : 'â€”' ?></td>
                                <td class="num"><?= (int) $line['quantity'] ?></td>
                                <td class="num">
                                    <?php if ($line['unit'] !== null): ?>
                                        <strong><?= e(money($line['unit'] * $line['quantity'])) ?></strong>
                                    <?php else: ?>
                                        <span style="color:var(--text-soft)">see total</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2 class="card__title">Payment summary</h2></div>
            <div class="card__body">
                <table class="table" style="max-width:26rem;margin-left:auto">
                    <tbody>
                        <tr>
                            <td>Subtotal</td>
                            <td class="num"><?= e(money($order['subtotal'] ?: $order['total_price'])) ?></td>
                        </tr>
                        <tr>
                            <td>Delivery fee</td>
                            <td class="num"><?= e(money($order['shipping_fee'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td class="num"><strong style="font-size:var(--fs-lg);color:var(--brand-600)"><?= e(money($order['total_price'])) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div>
        <section class="card no-print">
            <div class="card__head"><h2 class="card__title">Fulfilment</h2></div>
            <form class="card__body" method="post" action="order_view.php" data-confirm="Update the status of this order?">
                <input type="hidden" name="form_action" value="status">
                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">

                <div class="field">
                    <label class="field__label" for="status">Payment status</label>
                    <select class="select" id="status" name="status">
                        <?= admin_status_options((string) $order['payment_status']) ?>
                    </select>
                    <p class="field__hint">Marking an order paid or completed stamps it into today's revenue.</p>
                </div>

                <button class="btn btn--block" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Update status
                </button>
            </form>
        </section>

        <section class="card">
            <div class="card__head"><h2 class="card__title">Customer</h2></div>
            <div class="card__body">
                <table class="table">
                    <tbody>
                        <tr><td>Name</td><td class="num"><?= e($order['name']) ?></td></tr>
                        <tr><td>Email</td><td class="num"><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></td></tr>
                        <tr><td>Phone</td><td class="num"><a href="tel:<?= e(preg_replace('/\s+/', '', (string) $order['number'])) ?>"><?= e($order['number']) ?></a></td></tr>
                        <tr>
                            <td>Delivery address</td>
                            <td class="num" style="text-align:right;max-width:16rem"><?= nl2br(e($order['address'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2 class="card__title">Payment</h2></div>
            <div class="card__body">
                <table class="table">
                    <tbody>
                        <tr>
                            <td>Method</td>
                            <td class="num">
                                <span class="tag">
                                    <i class="fa-solid <?= e(payment_method_icon((string) $order['method'])) ?>" aria-hidden="true"></i>
                                    <?= e(payment_method_label((string) $order['method'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($order['payment_details'])): ?>
                        <tr><td>Details</td><td class="num"><?= e($order['payment_details']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order['payment_reference'])): ?>
                        <tr><td>Reference</td><td class="num"><code><?= e($order['payment_reference']) ?></code></td></tr>
                        <?php endif; ?>
                        <tr><td>Placed</td><td class="num"><?= e(nice_date((string) $order['order_date'], true)) ?></td></tr>
                        <tr><td>Last update</td><td class="num"><?= e(nice_date((string) $order['completed_on'], true)) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
