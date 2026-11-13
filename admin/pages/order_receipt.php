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
<link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/storenijayann.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<?= render_css('admin') ?>
<style>
  body { background: var(--gray-100, #f3f4f6); }
  .sheet-wrap { max-width: 48rem; margin: 0 auto; padding: var(--sp-6) var(--sp-4) var(--sp-10); }
  .sheet-actions { display: flex; gap: var(--sp-2); justify-content: space-between; margin-bottom: var(--sp-4); }
  .sheet {
    background: #fff; border: 1px solid var(--border); border-radius: var(--r-lg);
    box-shadow: 0 12px 32px -16px rgba(17, 24, 39, .18);
    padding: var(--sp-8);
  }
  .sheet__head { display: flex; gap: var(--sp-4); align-items: center; padding-bottom: var(--sp-5); margin-bottom: var(--sp-6); border-bottom: 2px solid var(--brand-500); }
  .sheet__head img { width: 3.5rem; height: 3.5rem; object-fit: contain; flex: none; }
  .sheet__brand { min-width: 0; }
  .sheet__brand h1 { font-size: var(--fs-xl); line-height: 1.2; margin: 0 0 2px; }
  .sheet__brand p { margin: 0; color: var(--text-muted); font-size: var(--fs-sm); line-height: 1.5; }
  .sheet__ref { margin-left: auto; text-align: right; display: grid; justify-items: end; gap: var(--sp-1); flex: none; }
  .sheet__ref small { font-size: var(--fs-xs); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-soft); }
  .sheet__ref strong { font-family: var(--font-mono, ui-monospace, monospace); font-size: var(--fs-lg); }
  .sheet__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--sp-4); margin-bottom: var(--sp-6); }
  .sheet__box { background: var(--gray-50); border: 1px solid var(--border); border-radius: var(--r-md); padding: var(--sp-4); min-width: 0; }
  .sheet h2 { font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text-soft); margin: 0 0 var(--sp-2); }
  .sheet .table td:nth-child(2) { font-weight: 600; }
  .sheet__box p { margin: 0; line-height: 1.6; overflow-wrap: anywhere; }
  .sheet .table { min-width: 0 !important; margin-bottom: var(--sp-5); }
  .sheet .table th, .sheet .table td { padding: var(--sp-3); }
  .sheet .table th:first-child, .sheet .table td:first-child { padding-left: 0; width: 2rem; color: var(--text-soft); }
  .sheet .table th:last-child, .sheet .table td:last-child { padding-right: 0; }
  .sheet__totals { width: min(20rem, 100%); margin-left: auto; }
  .sheet__totals td { padding: var(--sp-2) 0 !important; width: auto !important; color: inherit !important; }
  .sheet__totals tr:last-child td { border-top: 2px solid var(--gray-900); padding-top: var(--sp-3) !important; }
  .sheet__thanks { margin: var(--sp-8) 0 0; padding-top: var(--sp-5); border-top: 1px dashed var(--border-strong, var(--border)); color: var(--text-soft); font-size: var(--fs-xs); text-align: center; }
  @media (max-width: 560px) {
    .sheet-wrap { padding: var(--sp-4) var(--sp-3) var(--sp-8); }
    .sheet { padding: var(--sp-5) var(--sp-4); }
    .sheet__head { flex-wrap: wrap; }
    .sheet__ref { margin-left: 0; width: 100%; justify-items: start; text-align: left; grid-template-columns: auto auto; align-items: center; justify-content: space-between; }
    .sheet__ref small { grid-column: 1 / -1; }
    .sheet__grid { grid-template-columns: 1fr; }
    .sheet__totals { width: 100%; }
    .sheet .table { font-size: var(--fs-sm); }
    .sheet .table th, .sheet .table td { padding: var(--sp-2); }
    .sheet .table th:nth-child(3), .sheet .table td:nth-child(3) { display: none; }
  }
  @media print {
    body { background: #fff; }
    .sheet-wrap { max-width: none; padding: 0; }
    .sheet { border: 0; box-shadow: none; padding: 0; }
    .no-print, .sidebar, .topbar { display: none !important; }
  }
</style>
</head>
<body>

<div class="sheet-wrap">

    <div class="no-print sheet-actions">
        <a class="btn btn--ghost btn--sm" href="order_view.php?id=<?= (int) $order['id'] ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to order
        </a>
        <button class="btn btn--sm" type="button" onclick="window.print()">
            <i class="fa-solid fa-print" aria-hidden="true"></i> Print
        </button>
    </div>

    <div class="sheet">
    <header class="sheet__head">
        <img src="<?= BASE_URL ?>assets/img/storenijayann.png" alt="">
        <div class="sheet__brand">
            <h1><?= e($config['store']['legal']) ?></h1>
            <p>
                <?= e($config['store']['address']) ?><br>
                <?= e($config['store']['phone']) ?> &middot; <?= e($config['store']['email']) ?>
            </p>
        </div>
        <div class="sheet__ref">
            <small>Order sheet</small>
            <strong><?= e($ref) ?></strong>
            <span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
        </div>
    </header>

    <div class="sheet__grid">
        <section class="sheet__box">
            <h2>Deliver to</h2>
            <p><strong><?= e($order['name']) ?></strong><br>
               <?= nl2br(e($order['address'])) ?><br>
               <?= e($order['number']) ?><br>
               <?= e($order['email']) ?></p>
        </section>
        <section class="sheet__box">
            <h2>Order details</h2>
            <p>
                <strong><?= e(payment_method_label((string) $order['method'])) ?></strong><br>
                Placed <?= e(nice_date((string) $order['order_date'], true)) ?><br>
                <?= !empty($order['payment_reference']) ? 'Ref ' . e($order['payment_reference']) . '<br>' : '' ?>
                <?= !empty($order['completed_on']) ? 'Updated ' . e(nice_date((string) $order['completed_on'], true)) : '' ?>
            </p>
        </section>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th scope="col">#</th>
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
                <td><?= e($line['name']) ?></td>
                <td class="num"><?= $line['unit'] !== null ? e(money($line['unit'])) : '—' ?></td>
                <td class="num"><?= (int) $line['quantity'] ?></td>
                <td class="num"><?= $line['unit'] !== null ? e(money($line['unit'] * $line['quantity'])) : '—' ?></td>
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

    <p class="sheet__thanks">
        Thank you for shopping at <?= e($config['store']['legal']) ?>.
    </p>
    </div>
</div>

<script>
  window.addEventListener('load', function () { if (window.matchMedia('print').matches) window.print(); });
</script>
</body>
</html>
