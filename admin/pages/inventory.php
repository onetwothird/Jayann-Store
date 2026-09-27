<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'inventory';
$page_title = 'Inventory';
$page_sub   = 'Stock levels, valuation and what to reorder';

$errors = [];

$actor = (string) (current_admin()['name'] ?? 'admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['form_action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'restock') {
        $qty  = (int) ($_POST['qty'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($qty <= 0) {
            $errors[] = 'Enter how many units arrived — it has to be more than zero.';
        } else {
            $result = adjust_stock($id, $qty, 'purchase', [
                'note'      => $note !== '' ? $note : 'Stock received',
                'reference' => trim((string) ($_POST['reference'] ?? '')),
                'actor'     => $actor,
            ]);
            if ($result['ok']) {
                flash('success', $result['message']);
            } else {
                $errors[] = $result['message'];
            }
        }
        redirect('inventory.php');
    }

    if ($action === 'count') {
        $counted = (int) ($_POST['counted'] ?? -1);
        $reason  = (string) ($_POST['reason'] ?? 'correction');
        $note    = trim((string) ($_POST['note'] ?? ''));

        if ($counted < 0) {
            $errors[] = 'A counted quantity cannot be negative.';
        } else {
            $result = stock_set_absolute($id, $counted, is_inventory_reason($reason) ? $reason : 'correction', [
                'note'  => $note !== '' ? $note : 'Reconciled against a physical count',
                'actor' => $actor,
            ]);
            if ($result['ok']) {
                flash('success', $result['message'] . ' ' . ucfirst(inventory_reason_meta($reason)['label']) . ' recorded.');
            } else {
                $errors[] = $result['message'];
            }
        }
        redirect('inventory.php');
    }

    if ($action === 'settings') {
        $threshold = (int) ($_POST['low_stock_threshold'] ?? 5);
        $cost      = round((float) ($_POST['cost_price'] ?? 0), 2);
        $supplier  = trim((string) ($_POST['supplier'] ?? ''));
        $sku       = trim((string) ($_POST['sku'] ?? ''));

        if ($threshold < 0 || $threshold > 100000) {
            $errors[] = 'The reorder threshold cannot be negative.';
        } elseif ($cost < 0) {
            $errors[] = 'The unit cost cannot be negative.';
        } elseif (mb_strlen($supplier) > 120) {
            $errors[] = 'That supplier name is too long (120 characters maximum).';
        } elseif ($id > 0 && !$db->value('SELECT 1 FROM products WHERE id = ?', [$id])) {
            $errors[] = 'That product no longer exists.';
        } else {
            if ($sku !== '') {
                if (mb_strlen($sku) > 40) {
                    $errors[] = 'That stock code is too long (40 characters maximum).';
                } elseif ($db->value('SELECT 1 FROM products WHERE sku = ? AND id <> ?', [$sku, $id])) {
                    $errors[] = 'Another product already uses the code "' . $sku . '".';
                }
            }
            if (!$errors) {
                $db->run(
                    'UPDATE products SET low_stock_threshold = ?, cost_price = ?, supplier = ?, sku = ? WHERE id = ?',
                    [$threshold, $cost, $supplier, $sku !== '' ? $sku : ('JYS-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)), $id]
                );
                flash('success', 'Inventory settings saved.');
            }
        }
        redirect('inventory.php?' . http_build_query(['focus' => $id]));
    }
}

$search = admin_q('q');
$filter = admin_q('filter');
$sort   = admin_q('sort', 'reorder');
$focus  = admin_qint('focus');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.category LIKE ? OR p.sku LIKE ? OR p.supplier LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

$where[] = match ($filter) {
    'out' => '(p.stock <= 0)',
    'low' => '(p.stock > 0 AND p.low_stock_threshold > 0 AND p.stock <= p.low_stock_threshold)',
    'ok'  => '(p.stock > 0 AND (p.low_stock_threshold <= 0 OR p.stock > p.low_stock_threshold))',
    default => '1 = 1',
};

$whereSql = implode(' AND ', $where);

$sortSql = match ($sort) {
    'stock_desc'  => 'p.stock DESC, p.name ASC',
    'stock_asc'   => 'p.stock ASC, p.name ASC',
    'value_desc'  => '(p.stock * p.cost_price) DESC, p.name ASC',
    'value_asc'   => '(p.stock * p.cost_price) ASC, p.name ASC',
    'name'        => 'p.name ASC',
    'newest'      => 'p.id DESC',

    default       => '(p.stock <= 0) DESC, (p.stock - p.low_stock_threshold) ASC, p.name ASC',
};

$total   = (int) ($db->value("SELECT COUNT(*) FROM products p WHERE $whereSql", $params) ?? 0);
$perPage = 15;
$page    = admin_page_no($total, $perPage);
$offset  = ($page - 1) * $perPage;

$products = $db->all(
    "SELECT p.* FROM products p WHERE $whereSql ORDER BY $sortSql LIMIT $perPage OFFSET $offset",
    $params
);

$summary    = inventory_summary();
$reorder    = inventory_reorder_list(6);
$movements  = inventory_last_movements(8);
$totals     = inventory_movement_totals(30);
$categories = $db->all('SELECT DISTINCT category FROM products ORDER BY category');

$sortOptions = [
    'reorder'     => 'Needs attention first',
    'name'        => 'Name (A–Z)',
    'stock_asc'   => 'Lowest stock',
    'stock_desc'  => 'Highest stock',
    'value_desc'  => 'Highest stock value',
    'value_asc'   => 'Lowest stock value',
    'newest'      => 'Newest first',
];

$filterChips = [
    ''    => 'All',
    'low'  => 'Low stock',
    'out'  => 'Out of stock',
    'ok'   => 'Healthy',
];

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Inventory</h1>
        <p>
            <?= e(number_format($summary['skus'])) ?> products &middot;
            <?= e(number_format($summary['units'])) ?> units on hand &middot;
            <?= e(number_format($summary['out'] + $summary['low'])) ?> need attention
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="stock_movements.php">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Movement history
        </a>
        <a class="btn" href="inventory.php?filter=low">
            <i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Reorder queue
        </a>
    </div>
</div>

<?php foreach ($errors as $err): ?>
    <div class="notice notice--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <div><strong>Could not save that</strong><p><?= e($err) ?></p></div>
    </div>
<?php endforeach; ?>

<!-- ================================================================== stats -->
<div class="statgrid">
    <article class="stat">
        <span class="stat__icon" aria-hidden="true"><i class="fa-solid fa-boxes-stacked"></i></span>
        <div>
            <p class="stat__label">Units on hand</p>
            <p class="stat__value"><?= e(number_format($summary['units'])) ?></p>
            <p class="stat__note">Across <?= e(number_format($summary['skus'])) ?> products</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon stat__icon--info" aria-hidden="true"><i class="fa-solid fa-warehouse"></i></span>
        <div>
            <p class="stat__label">Stock value (cost)</p>
            <p class="stat__value"><?= e(money($summary['value_cost'])) ?></p>
            <p class="stat__note">What the shelf is worth to buy</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon stat__icon--success" aria-hidden="true"><i class="fa-solid fa-tags"></i></span>
        <div>
            <p class="stat__label">Stock value (retail)</p>
            <p class="stat__value"><?= e(money($summary['value_retail'])) ?></p>
            <p class="stat__note"><?= e(money($summary['stock_cost'])) ?> expected margin</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon <?= ($summary['out'] + $summary['low']) > 0 ? 'stat__icon--warn' : 'stat__icon--success' ?>"
              aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div>
            <p class="stat__label">Needs reordering</p>
            <p class="stat__value"><?= e(number_format($summary['out'] + $summary['low'])) ?></p>
            <p class="stat__note">
                <?= e(number_format($summary['out'])) ?> out &middot;
                <?= e(number_format($summary['low'])) ?> low
            </p>
        </div>
    </article>
</div>

<div class="grid-2 grid-2--wide-left">
    <div>
        <form class="filters" method="get" action="inventory.php">
            <div class="searchinline">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input" type="search" name="q" value="<?= e($search) ?>"
                       placeholder="Search product, code or supplier" aria-label="Search inventory" data-search>
            </div>

            <input type="hidden" name="sort" value="<?= e($sort) ?>">

            <?php foreach ($filterChips as $value => $label): ?>
                <button class="chip<?= $filter === $value ? ' is-active' : '' ?>" type="submit"
                        name="filter" value="<?= e($value) ?>">
                    <?= e($label) ?>
                    <?php if ($value === 'low' && $summary['low'] > 0): ?>
                        <span class="sidebar__count"><?= (int) $summary['low'] ?></span>
                    <?php elseif ($value === 'out' && $summary['out'] > 0): ?>
                        <span class="sidebar__count"><?= (int) $summary['out'] ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>

            <div class="filters__count">
                <strong><?= e(number_format($total)) ?></strong> <?= $total === 1 ? 'product' : 'products' ?>
            </div>
        </form>

        <form method="get" action="inventory.php" style="display:flex;gap:var(--sp-2);align-items:center;margin-bottom:var(--sp-4);flex-wrap:wrap">
            <input type="hidden" name="q" value="<?= e($search) ?>">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <label class="field__label" for="isort" style="margin:0">Sort by</label>
            <select class="select" id="isort" name="sort" data-auto-submit style="width:auto;min-width:14rem">
                <?php foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Stock levels</h2>
                <span class="tag"><?= e(money($summary['value_cost'])) ?> at cost</span>
            </div>
            <div class="card__body card__body--flush">
                <?php if (!$products): ?>
                    <?= admin_empty(
                        'fa-magnifying-glass',
                        'Nothing matches',
                        $search !== '' || $filter !== ''
                            ? 'Try a different search term or clear the filter.'
                            : 'Add a product to start tracking stock.',
                        ($search !== '' || $filter !== '')
                            ? '<a class="btn btn--ghost btn--sm" href="inventory.php">Clear filters</a>'
                            : '<a class="btn btn--sm" href="products.php#add-product">Add a product</a>'
                    ) ?>
                <?php else: ?>
                <div class="tablewrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col" class="num">On hand</th>
                                <th scope="col" class="num">Reorder at</th>
                                <th scope="col" class="num">Unit cost</th>
                                <th scope="col" class="num">Stock value</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="num">Receive</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($products as $p): ?>
                            <?php
                            $pid       = (int) $p['id'];
                            $stock     = (int) $p['stock'];
                            $threshold = (int) $p['low_stock_threshold'];
                            $status    = stock_status($stock, $threshold);
                            $value     = $stock * (float) $p['cost_price'];
                            $suggested = max($threshold * 2 - $stock, $threshold > 0 ? 1 : 0);
                            $focusRow  = $focus === $pid;
                            ?>
                            <tr<?= $focusRow ? ' class="is-focused"' : '' ?>>
                                <td>
                                    <span class="cellproduct">
                                        <img src="<?= e(product_image($p['image'])) ?>" alt="" loading="lazy">
                                        <span>
                                            <strong><?= e($p['name']) ?></strong>
                                            <small>
                                                <?= e($p['sku']) ?> &middot; <?= e($p['category']) ?>
                                                <?php if ($p['supplier'] !== ''): ?>
                                                    &middot; <?= e($p['supplier']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </span>
                                    </span>
                                </td>
                                <td class="num">
                                    <strong style="font-size:var(--fs-lg)"><?= e(number_format($stock)) ?></strong>
                                    <?php if ($threshold > 0): ?>
                                        <div class="stockmeter" role="img"
                                             aria-label="<?= e($stock) ?> of a suggested <?= e(max($threshold * 2, 1)) ?> units">
                                            <span class="stockmeter__fill stockmeter__fill--<?= e($status['key']) ?>"
                                                  style="width:<?= e((string) min(100, round($stock / max($threshold * 2, 1) * 100))) ?>%"></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><?= $threshold > 0 ? e(number_format($threshold)) : '<span style="color:var(--text-soft)">—</span>' ?></td>
                                <td class="num"><?= e(money($p['cost_price'])) ?></td>
                                <td class="num"><strong><?= e(money($value)) ?></strong></td>
                                <td>
                                    <span class="tag tag--<?= e($status['tag']) ?>">
                                        <i class="fa-solid <?= e($status['icon']) ?>" aria-hidden="true"></i>
                                        <?= e($status['label']) ?>
                                    </span>
                                </td>
                                <td class="num">
                                    <form class="restock" method="post" action="inventory.php">
                                        <input type="hidden" name="form_action" value="restock">
                                        <input type="hidden" name="id" value="<?= $pid ?>">
                                        <label class="sr-only" for="rq-<?= $pid ?>">Units received for <?= e($p['name']) ?></label>
                                        <input class="input restock__qty" id="rq-<?= $pid ?>" type="number"
                                               name="qty" min="1" step="1" value="<?= $suggested ?>"
                                               placeholder="Qty" inputmode="numeric">
                                        <label class="sr-only" for="rn-<?= $pid ?>">Reference for <?= e($p['name']) ?></label>
                                        <input class="input restock__ref" id="rn-<?= $pid ?>" type="text"
                                               name="reference" maxlength="60" placeholder="Ref / PO">
                                        <button class="btn btn--sm btn--success" type="submit" title="Receive stock">
                                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <a class="btn btn--sm btn--ghost" style="margin-top:var(--sp-2)"
                                       href="product_stock.php?id=<?= $pid ?>">
                                        <i class="fa-solid fa-sliders" aria-hidden="true"></i> Manage
                                    </a>
                                </td>
                            </tr>

                            <?php if ($focusRow): ?>
                            <tr class="row-expand">
                                <td colspan="7">
                                    <form method="post" action="inventory.php" class="inlineform">
                                        <input type="hidden" name="form_action" value="settings">
                                        <input type="hidden" name="id" value="<?= $pid ?>">

                                        <div class="field">
                                            <label class="field__label" for="f-sku-<?= $pid ?>">Stock code</label>
                                            <input class="input" id="f-sku-<?= $pid ?>" type="text" name="sku"
                                                   maxlength="40" value="<?= e($p['sku']) ?>">
                                        </div>
                                        <div class="field">
                                            <label class="field__label" for="f-cost-<?= $pid ?>">Unit cost (<?= e(currency_symbol()) ?>)</label>
                                            <input class="input" id="f-cost-<?= $pid ?>" type="number" name="cost_price"
                                                   min="0" step="0.01" value="<?= e(number_format((float) $p['cost_price'], 2, '.', '')) ?>"
                                                   inputmode="decimal">
                                        </div>
                                        <div class="field">
                                            <label class="field__label" for="f-low-<?= $pid ?>">Reorder at</label>
                                            <input class="input" id="f-low-<?= $pid ?>" type="number" name="low_stock_threshold"
                                                   min="0" step="1" value="<?= $threshold ?>" inputmode="numeric">
                                        </div>
                                        <div class="field">
                                            <label class="field__label" for="f-sup-<?= $pid ?>">Supplier</label>
                                            <input class="input" id="f-sup-<?= $pid ?>" type="text" name="supplier"
                                                   maxlength="120" value="<?= e($p['supplier']) ?>"
                                                   placeholder="Who we buy it from">
                                        </div>
                                        <div class="field field--full">
                                            <label class="field__label" for="f-count-<?= $pid ?>">Physical count</label>
                                            <div class="countrow">
                                                <input class="input" id="f-count-<?= $pid ?>" type="number"
                                                       name="counted" min="0" step="1"
                                                       value="<?= $stock ?>" inputmode="numeric">
                                                <select class="select" name="reason" aria-label="Reason for the count">
                                                    <?php foreach (inventory_reasons() as $code => $meta): ?>
                                                        <option value="<?= e($code) ?>"><?= e($meta['label']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input class="input" type="text" name="note" maxlength="255"
                                                       placeholder="Note (optional)">
                                                <button class="btn btn--ghost" type="submit" name="form_action" value="count">
                                                    <i class="fa-solid fa-calculator" aria-hidden="true"></i> Reconcile
                                                </button>
                                            </div>
                                            <p class="field__hint">
                                                Reconcile rewrites the balance to the number you counted and logs
                                                the difference, so the ledger stays truthful.
                                            </p>
                                        </div>

                                        <div class="field field--full inlineform__actions">
                                            <button class="btn" type="submit" name="form_action" value="settings">
                                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save settings
                                            </button>
                                            <a class="btn btn--ghost" href="inventory.php">Close</a>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?= admin_pager($page, $total, $perPage) ?>
        </section>
    </div>

    <div>
        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Reorder queue</h2>
                <a class="btn btn--ghost btn--sm" href="inventory.php?filter=low">All</a>
            </div>
            <?php if (!$reorder): ?>
                <?= admin_empty('fa-circle-check', 'Stock is healthy', 'Nothing is at or below its reorder threshold.') ?>
            <?php else: ?>
            <div class="card__body card__body--flush">
                <div class="tablewrap">
                    <table class="table">
                        <tbody>
                        <?php foreach ($reorder as $p): ?>
                            <?php $st = stock_status((int) $p['stock'], (int) $p['low_stock_threshold']); ?>
                            <tr>
                                <td>
                                    <span class="cellproduct">
                                        <img src="<?= e(product_image($p['image'])) ?>" alt="" loading="lazy">
                                        <span>
                                            <strong><?= e($p['name']) ?></strong>
                                            <small><?= e($p['sku']) ?></small>
                                        </span>
                                    </span>
                                </td>
                                <td class="num">
                                    <span class="tag tag--<?= e($st['tag']) ?>"><?= e($st['label']) ?></span>
                                    <br>
                                    <form class="restock restock--stacked" method="post" action="inventory.php">
                                        <input type="hidden" name="form_action" value="restock">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <label class="sr-only" for="qq-<?= (int) $p['id'] ?>">Units to order</label>
                                        <input class="input restock__qty" id="qq-<?= (int) $p['id'] ?>" type="number"
                                               name="qty" min="1" step="1" max="9999"
                                               value="<?= (int) max($p['suggested_order'], 1) ?>" inputmode="numeric">
                                        <button class="btn btn--sm btn--success" type="submit" title="Receive this delivery">
                                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                        </button>
                                    </form>
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
                <h2 class="card__title">Movement totals</h2>
                <span class="tag">Last 30 days</span>
            </div>
            <div class="card__body">
                <?php if (!$totals): ?>
                    <p class="field__hint" style="margin:0">
                        No stock has moved in the last 30 days. Receive a delivery, place an order, or
                        reconcile a count and it will show up here.
                    </p>
                <?php else: ?>
                    <ul class="movestats">
                        <?php foreach ($totals as $t): ?>
                            <li>
                                <i class="fa-solid <?= e($t['icon']) ?>" aria-hidden="true"></i>
                                <span class="movestats__label"><?= e($t['label']) ?></span>
                                <span class="movestats__value"><?= e(number_format($t['units'])) ?></span>
                                <span class="movestats__note"><?= e(plural($t['rows'], 'entry', 'entries')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Latest movements</h2>
                <a class="btn btn--ghost btn--sm" href="stock_movements.php">View all</a>
            </div>
            <?php if (!$movements): ?>
                <?= admin_empty('fa-clock-rotate-left', 'No movements yet', 'Stock changes will be listed here.') ?>
            <?php else: ?>
            <div class="card__body card__body--flush">
                <ul class="movelist">
                    <?php foreach ($movements as $m): ?>
                        <?php $meta = inventory_reason_meta((string) $m['reason']); ?>
                        <li>
                            <span class="delta delta--<?= e($m['direction']) ?>" aria-hidden="true">
                                <i class="fa-solid <?= e((int) $m['qty_change'] >= 0 ? 'fa-arrow-down' : 'fa-arrow-up') ?>"></i>
                            </span>
                            <span class="movelist__body">
                                <strong><?= e($m['product_name']) ?></strong>
                                <small>
                                    <?= e($meta['label']) ?>
                                    <?php if ($m['actor'] !== ''): ?>
                                        &middot; <?= e($m['actor']) ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                            <span class="movelist__figures">
                                <strong class="delta-text delta-text--<?= e($m['direction']) ?>">
                                    <?= (int) $m['qty_change'] >= 0 ? '+' : '&minus;' ?><?= e(number_format(abs((int) $m['qty_change']))) ?>
                                </strong>
                                <small><?= e(number_format((int) $m['balance_after'])) ?> left</small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
