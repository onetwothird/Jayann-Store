<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'stock';
$page_title = 'Stock movements';
$page_sub   = 'Every change to every product, with its reason';

$productId  = admin_qint('product');
$reason     = admin_q('reason');
$direction  = admin_q('dir');
$days       = admin_qint('days', 30);
$search     = admin_q('q');

$where  = [];
$params = [];

if ($productId > 0) {
    $where[]  = 'm.product_id = ?';
    $params[] = $productId;
}
if ($reason !== '' && is_inventory_reason($reason)) {
    $where[]  = 'm.reason = ?';
    $params[] = $reason;
}
if ($direction === 'in' || $direction === 'out') {
    $where[]  = 'm.direction = ?';
    $params[] = $direction;
}
if ($days > 0) {
    $where[]  = 'm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $params[] = $days;
}
if ($search !== '') {
    $where[]  = '(m.product_name LIKE ? OR m.note LIKE ? OR m.reference LIKE ? OR m.actor LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = $where === [] ? '1 = 1' : implode(' AND ', $where);

$total   = (int) ($db->value("SELECT COUNT(*) FROM stock_movements m WHERE $whereSql", $params) ?? 0);
$perPage = 25;
$page    = admin_page_no($total, $perPage);
$offset  = ($page - 1) * $perPage;

$movements = $db->all(
    "SELECT m.* FROM stock_movements m WHERE $whereSql ORDER BY m.id DESC LIMIT $perPage OFFSET $offset",
    $params
);

$netUnits = (int) ($db->value(
    "SELECT COALESCE(SUM(qty_change), 0) FROM stock_movements m WHERE $whereSql",
    $params
) ?? 0);

$totals  = inventory_movement_totals($days > 0 ? $days : 30);
$products = $db->all('SELECT id, name, sku FROM products ORDER BY name');

if (admin_q('export') === 'csv') {
    $all = $db->all(
        "SELECT m.* FROM stock_movements m WHERE $whereSql ORDER BY m.id ASC",
        $params
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock-movements-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['When', 'Product', 'Code', 'Reason', 'In', 'Out', 'Change', 'Balance after', 'Reference', 'Note', 'By']);

    foreach ($all as $m) {
        $meta  = inventory_reason_meta((string) $m['reason']);
        $delta = (int) $m['qty_change'];
        fputcsv($out, [
            (string) $m['created_at'],
            (string) $m['product_name'],
            (string) ($db->value('SELECT sku FROM products WHERE id = ?', [(int) $m['product_id']]) ?? ''),
            $meta['label'],
            $delta > 0 ? $delta : 0,
            $delta < 0 ? abs($delta) : 0,
            $delta,
            (int) $m['balance_after'],
            (string) $m['reference'],
            (string) $m['note'],
            (string) $m['actor'],
        ]);
    }
    fclose($out);
    exit;
}

$dayOptions = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year', 0 => 'All time'];

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Stock movements</h1>
        <p>
            <?= e(number_format($total)) ?> <?= $total === 1 ? 'entry' : 'entries' ?> &middot;
            net change <?= e(($netUnits >= 0 ? '+' : '&minus;') . number_format(abs($netUnits))) ?> units
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="inventory.php">
            <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Stock levels
        </a>
        <a class="btn" href="<?= e(admin_url(['export' => 'csv'])) ?>">
            <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV
        </a>
    </div>
</div>

<!-- ================================================================== stats -->
<div class="statgrid statgrid--compact">
    <?php if (!$totals): ?>
        <article class="stat">
            <span class="stat__icon stat__icon--success" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <div>
                <p class="stat__label">No movements</p>
                <p class="stat__value">&mdash;</p>
                <p class="stat__note">Nothing has changed stock in this window</p>
            </div>
        </article>
    <?php else: ?>
        <?php foreach (array_slice($totals, 0, 4) as $t): ?>
            <article class="stat">
                <span class="stat__icon" aria-hidden="true"><i class="fa-solid <?= e($t['icon']) ?>"></i></span>
                <div>
                    <p class="stat__label"><?= e($t['label']) ?></p>
                    <p class="stat__value"><?= e(number_format($t['units'])) ?></p>
                    <p class="stat__note"><?= e(plural($t['rows'], 'entry', 'entries')) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ================================================================= filters -->
<form class="filters card" method="get" action="stock_movements.php">
    <div class="searchinline">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input" type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search product, note, reference or staff" aria-label="Search movements" data-search>
    </div>

    <label class="sr-only" for="fm-product">Product</label>
    <select class="select" id="fm-product" name="product" data-auto-submit>
        <option value="0">All Products</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>"<?= $productId === (int) $p['id'] ? ' selected' : '' ?>>
                <?= e($p['sku']) ?> &middot; <?= e($p['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="sr-only" for="fm-reason">Reason</label>
    <select class="select" id="fm-reason" name="reason" data-auto-submit>
        <option value="">Any reason</option>
        <?php foreach (inventory_reasons() as $code => $meta): ?>
            <option value="<?= e($code) ?>"<?= $reason === $code ? ' selected' : '' ?>><?= e($meta['label']) ?></option>
        <?php endforeach; ?>
    </select>

    <label class="sr-only" for="fm-dir">Direction</label>
    <select class="select" id="fm-dir" name="dir" data-auto-submit>
        <option value="">In and out</option>
        <option value="in"<?= $direction === 'in' ? ' selected' : '' ?>>Stock in</option>
        <option value="out"<?= $direction === 'out' ? ' selected' : '' ?>>Stock out</option>
    </select>

    <label class="sr-only" for="fm-days">Period</label>
    <select class="select" id="fm-days" name="days" data-auto-submit>
        <?php foreach ($dayOptions as $value => $label): ?>
            <option value="<?= (int) $value ?>"<?= $days === (int) $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <button class="chip<?= $search === '' && $reason === '' && $direction === '' && $productId === 0 ? ' is-active' : '' ?>"
            type="submit">Apply</button>

    <div class="filters__count">
        <strong><?= e(number_format($total)) ?></strong> <?= $total === 1 ? 'match' : 'matches' ?>
    </div>
</form>

<!-- ================================================================= listing -->
<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$movements): ?>
            <?= admin_empty(
                'fa-clock-rotate-left',
                'No movements match',
                'Stock changes are recorded here automatically when a delivery is received, an order is placed, or a count is reconciled.',
                '<a class="btn btn--soft btn--sm" href="inventory.php">Go to inventory</a>'
            ) ?>
        <?php else: ?>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Product</th>
                        <th scope="col">Reason</th>
                        <th scope="col" class="num">Change</th>
                        <th scope="col" class="num">Balance</th>
                        <th scope="col">Reference</th>
                        <th scope="col">By</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                    <?php
                    $meta  = inventory_reason_meta((string) $m['reason']);
                    $delta = (int) $m['qty_change'];
                    $link  = $db->one('SELECT id FROM products WHERE id = ?', [(int) $m['product_id']]);
                    ?>
                    <tr>
                        <td style="white-space:nowrap">
                            <strong><?= e(nice_date((string) $m['created_at'])) ?></strong>
                            <small style="display:block;color:var(--text-muted)">
                                <?= e(date('H:i', strtotime((string) $m['created_at']))) ?>
                            </small>
                        </td>
                        <td>
                            <span class="cellproduct">
                                <span>
                                    <strong><?= e($m['product_name']) ?></strong>
                                    <small>
                                        <?php if ($link): ?>
                                            <a href="product_stock.php?id=<?= (int) $m['product_id'] ?>">Product
                                        <?php else: ?>
                                            Product
                                        <?php endif; ?>
                                    </small>
                                </span>
                            </span>
                        </td>
                        <td>
                            <span class="tag">
                                <i class="fa-solid <?= e($meta['icon']) ?>" aria-hidden="true"></i>
                                <?= e($meta['label']) ?>
                            </span>
                            <?php if (trim((string) $m['note']) !== ''): ?>
                                <small style="display:block;color:var(--text-muted);margin-top:2px"><?= e($m['note']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="num">
                            <span class="delta-text delta-text--<?= e($m['direction']) ?>">
                                <?= $delta >= 0 ? '+' : '&minus;' ?><?= e(number_format(abs($delta))) ?>
                            </span>
                        </td>
                        <td class="num"><?= e(number_format((int) $m['balance_after'])) ?></td>
                        <td><?= trim((string) $m['reference']) !== '' ? e($m['reference']) : '<span style="color:var(--text-soft)">&mdash;</span>' ?></td>
                        <td><?= e($m['actor']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?= admin_pager($page, $total, $perPage) ?>
</section>

<p class="field__hint" style="margin-top:var(--sp-4)">
    Movements are written automatically by <code>app/inventory.php</code> whenever stock changes and are
    never edited by hand, so the running balance in the last column should always equal the
    <code>stock</code> column on that product.
</p>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
