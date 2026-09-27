<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login('receipt.php');

$orderId = (int) ($_GET['order'] ?? 0);
$order   = $db->one('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$orderId, current_user_id()]);

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Receipt not found';
    require '../app/views/layout/head.php';
    echo '<section class="section container"><div class="empty">'
       . '<span class="empty__icon"><i class="fa-solid fa-triangle-exclamation"></i></span>'
       . '<h1 class="empty__title">Receipt not found</h1>'
       . '<p class="empty__text">This order does not exist, or it belongs to another account.</p>'
       . '<a class="btn" href="orders.php">Back to my orders</a></div></section>';
    require '../app/views/layout/footer.php';
    exit;
}

$store   = $config['store'];
$lines   = order_lines($order);
$meta    = order_status_meta((string) $order['payment_status']);
$ref     = $order['order_ref'] ?: ('#' . $order['id']);
$subtotal = (float) ($order['subtotal'] ?: 0);
$shipping = (float) ($order['shipping_fee'] ?? 0);

if ($subtotal <= 0 && (float) $order['total_price'] > 0) {
    $shipping = (float) $order['total_price'];
    $subtotal = $shipping;
}

$pageTitle = 'Receipt ' . $ref;
$pageDesc  = 'Order receipt for ' . $ref . ' at Jayann\'s Store.';
$pageClass = 'page-receipt';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <a href="orders.php">My orders</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span><?= e($ref) ?></span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">
        <div class="panel receipt">

            <div class="receipt__head">
                <img class="receipt__logo" src="<?= BASE_URL ?>assets/img/storenijayann.png" alt="">
                <h1 class="receipt__title">Order receipt</h1>
                <p class="receipt__sub">
                    <?= e($store['name']) ?> &middot; <?= e($store['address']) ?>
                </p>
                <div class="receipt__ref">
                    <span class="receipt__reflabel">Order reference</span>
                    <strong><?= e($ref) ?></strong>
                    <button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e($ref) ?>"
                            aria-label="Copy order reference">
                        <i class="fa-regular fa-copy" aria-hidden="true"></i> Copy
                    </button>
                </div>
                <span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
            </div>

            <dl class="receipt__grid">
                <div class="receipt__cell">
                    <dt>Placed on</dt>
                    <dd><?= nice_date((string) $order['order_date'], true) ?></dd>
                </div>
                <div class="receipt__cell">
                    <dt>Payment method</dt>
                    <dd><?= e(payment_method_label((string) $order['method'])) ?></dd>
                </div>
                <div class="receipt__cell">
                    <dt>Recipient</dt>
                    <dd><?= e($order['name']) ?></dd>
                </div>
                <div class="receipt__cell">
                    <dt>Contact</dt>
                    <dd><?= e($order['number']) ?></dd>
                </div>
                <div class="receipt__cell receipt__cell--wide">
                    <dt>Delivery address</dt>
                    <dd><?= nl2br(e($order['address'])) ?></dd>
                </div>
            </dl>

            <div class="tablewrap">
                <table class="table table--stack">
                    <caption class="sr-only">Items in this order</caption>
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col" class="num">Price</th>
                            <th scope="col" class="num">Qty</th>
                            <th scope="col" class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td data-label="Item"><?= e($line['name']) ?></td>
                                <td class="num" data-label="Price"><?= $line['unit'] !== null ? money($line['unit']) : '—' ?></td>
                                <td class="num" data-label="Qty"><?= (int) $line['quantity'] ?></td>
                                <td class="num" data-label="Subtotal">
                                    <?= $line['unit'] !== null
                                        ? money($line['unit'] * $line['quantity'])
                                        : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="receipt__totals">
                <dl class="dl">
                    <div class="dl__row">
                        <dt>Subtotal</dt>
                        <dd><?= money($subtotal) ?></dd>
                    </div>
                    <div class="dl__row">
                        <dt>Delivery fee</dt>
                        <dd><?= $shipping > 0 ? money($shipping) : 'Free' ?></dd>
                    </div>
                    <div class="dl__row dl__row--total">
                        <dt>Total paid</dt>
                        <dd><?= money($order['total_price']) ?></dd>
                    </div>
                </dl>
            </div>

            <div class="timeline">
                <div class="timeline__row">
                    <span class="timeline__dot is-done"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                    <div class="timeline__body">
                        <strong>Order placed</strong>
                        <small><?= nice_date((string) $order['order_date'], true) ?></small>
                    </div>
                </div>
                <div class="timeline__row">
                    <span class="timeline__dot <?= in_array($meta['class'], ['paid', 'completed'], true) ? 'is-done' : 'is-now' ?>">
                        <i class="fa-solid fa-box" aria-hidden="true"></i>
                    </span>
                    <div class="timeline__body">
                        <strong>Preparing your order</strong>
                        <small>We&rsquo;re packing your items now.</small>
                    </div>
                </div>
                <div class="timeline__row">
                    <span class="timeline__dot <?= $meta['class'] === 'completed' ? 'is-done' : '' ?>">
                        <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                    </span>
                    <div class="timeline__body">
                        <strong>Out for delivery</strong>
                        <small>Delivery within 1&ndash;2 days in Ternate, Cavite.</small>
                    </div>
                </div>
            </div>

            <div class="receipt__foot">
                <a class="btn" href="download_receipt.php?order=<?= (int) $order['id'] ?>">
                    <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i> Download PDF
                </a>
                <a class="btn btn--ghost" href="orders.php">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All orders
                </a>
                <a class="btn btn--ghost" href="products.php">
                    <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Shop again
                </a>
            </div>
        </div>

        <div class="notice notice--info no-print">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <div>
                <strong>Questions about this order?</strong>
                <p>
                    Message us on
                    <a href="<?= e($store['facebook']) ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
                    or call <a href="tel:<?= e($store['phone_raw']) ?>"><?= e($store['phone']) ?></a>
                    quoting reference <strong><?= e($ref) ?></strong>.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>


