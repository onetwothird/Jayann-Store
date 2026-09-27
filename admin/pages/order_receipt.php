<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$id    = admin_qint('id');
$order = $id > 0 ? $db->one('SELECT * FROM orders WHERE id = ?', [$id]) : null;

if (!$order) {
    flash('error', 'That order could not be found.');
    redirect('placed_orders.php');
}

$lines = order_lines($order);
$meta  = order_status_meta((string) $order['payment_status']);
$ref   = (string) ($order['order_ref'] ?: ('#' . $order['id']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($ref) ?> &middot; order sheet</title>
<link rel="icon" type="image/png" href="../assets/img/storenijayann.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin_style.css?v=2.0">
<style>
  .sheet { max-width: 44rem; margin: 0 auto; padding: var(--sp-6); background:
  .sheet__head { display: flex; gap: var(--sp-4); align-items: center; border-bottom: 2px solid var(--brand-500); padding-bottom: var(--sp-4); margin-bottom: var(--sp-5); }
  .sheet__head img { width: 3.5rem; height: 3.5rem; object-fit: contain; }
  .sheet__head h1 { font-size: var(--fs-xl); }
  .sheet__ref { margin-left: auto; text-align: right; }
  .sheet__ref strong { display: block; font-size: var(--fs-lg); }
  .sheet__grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--sp-5); margin-bottom: var(--sp-5); }
  @media (max-width: 560px) { .sheet__grid { grid-template-columns: 1fr; } }
  .sheet h2 { font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: .08em; color: var(--text-soft); margin-bottom: var(--sp-2); }
  .sheet__totals { max-width: 20rem; margin-left: auto; }
  .no-print { margin-bottom: var(--sp-5); }
  @media print {
    body { background:
    .no-print, .sidebar, .topbar { display: none !important; }
    .sheet { max-width: none; padding: 0; }
  }
</style>
</head>
<body>

<div class="sheet">

    <div class="no-print">
        <a class="btn btn--ghost btn--sm" href="order_view.php?id=<?= (int) $order['id'] ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to order
        </a>
        <button class="btn btn--sm" type="button" onclick="window.print()">
            <i class="fa-solid fa-print" aria-hidden="true"></i> Print
        </button>
    </div>

    <header class="sheet__head">
        <img src="../<?= e(product_image(null)) ?>" alt="">
        <div>
            <h1><?= e($config['store']['legal']) ?></h1>
            <p style="color:var(--text-muted);font-size:var(--fs-sm)">
                <?= e($config['store']['address']) ?><br>
                <?= e($config['store']['phone']) ?> &middot; <?= e($config['store']['email']) ?>
            </p>
        </div>
        <div class="sheet__ref">
            <strong><?= e($ref) ?></strong>
            <span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
        </div>
    </header>

    <div class="sheet__grid">
        <section>
            <h2>Deliver to</h2>
            <p><strong><?= e($order['name']) ?></strong><br>
               <?= nl2br(e($order['address'])) ?><br>
               <?= e($order['number']) ?><br>
               <?= e($order['email']) ?></p>
        </section>
        <section>
            <h2>Order details</h2>
            <p>
                <strong><?= e(payment_method_label((string) $order['method'])) ?></strong><br>
                Placed <?= e(nice_date((string) $order['order_date'], true)) ?><br>
                <?= !empty($order['payment_reference']) ? 'Ref ' . e($order['payment_reference']) . '<br>' : '' ?>
                <?= !empty($order['completed_on']) ? 'Updated ' . e(nice_date((string) $order['completed_on'], true)) : '' ?>
            </p>
        </section>
    </div>

    <table class="table" style="margin-bottom:var(--sp-5)">
        <thead>
            <tr>
                <th scope="col">
                <th scope="col">Product</th>
                <th scope="col" class="num">Unit</th>
                <th scope="col" class="num">Qty</th>
                <th scope="col" class="num">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $i => $line): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= e($line['name']) ?></strong></td>
                <td class="num"><?= $line['unit'] !== null ? e(money($line['unit'])) : 'â€”' ?></td>
                <td class="num"><?= (int) $line['quantity'] ?></td>
                <td class="num"><?= $line['unit'] !== null ? e(money($line['unit'] * $line['quantity'])) : 'â€”' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="table sheet__totals">
        <tbody>
            <tr><td>Subtotal</td><td class="num"><?= e(money($order['subtotal'] ?: $order['total_price'])) ?></td></tr>
            <tr><td>Delivery</td><td class="num"><?= e(money($order['shipping_fee'])) ?></td></tr>
            <tr><td><strong>Amount due</strong></td><td class="num"><strong style="font-size:var(--fs-xl)"><?= e(money($order['total_price'])) ?></strong></td></tr>
        </tbody>
    </table>

    <p style="margin-top:var(--sp-8);color:var(--text-soft);font-size:var(--fs-xs);text-align:center">
        Thank you for shopping at <?= e($config['store']['legal']) ?>.
    </p>
</div>

<script>
  window.addEventListener('load', function () { if (window.matchMedia('print').matches) window.print(); });
</script>
</body>
</html>
