<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$id     = admin_qint('id');
$errors = [];
$actor  = (string) (current_admin()['name'] ?? 'admin');

$product = $id > 0 ? $db->one('SELECT * FROM products WHERE id = ?', [$id]) : null;

if (!$product) {
    flash('error', 'That product could not be found.');
    redirect('inventory.php');
}

$admin_page = 'stock';
$page_title = $product['name'];
$page_sub   = 'Stock control and history';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['form_action'] ?? '');

    if ($action === 'restock') {
        $qty = (int) ($_POST['qty'] ?? 0);
        if ($qty <= 0) {
            $errors[] = 'Enter how many units arrived — it has to be more than zero.';
        } else {
            $result = adjust_stock($id, $qty, 'purchase', [
                'note'      => trim((string) ($_POST['note'] ?? '')) ?: 'Stock received',
                'reference' => trim((string) ($_POST['reference'] ?? '')),
                'actor'     => $actor,
            ]);
            $result['ok']
                ? flash('success', $result['message'])
                : $errors[] = $result['message'];
        }
    }

    if ($action === 'remove') {
        $qty = (int) ($_POST['qty'] ?? 0);
        $reason = (string) ($_POST['reason'] ?? 'damage');
        if ($qty <= 0) {
            $errors[] = 'Enter how many units are leaving — it has to be more than zero.';
        } elseif (!in_array($reason, ['damage', 'correction', 'return', 'sale'], true)) {
            $errors[] = 'Choose a valid reason.';
        } else {
            $result = adjust_stock($id, -$qty, $reason, [
                'note'  => trim((string) ($_POST['note'] ?? '')) ?: inventory_reason_meta($reason)['label'],
                'actor' => $actor,
            ]);
            $result['ok']
                ? flash('success', $result['message'])
                : $errors[] = $result['message'];
        }
    }

    if ($action === 'count') {
        $counted = (int) ($_POST['counted'] ?? -1);
        $reason  = (string) ($_POST['reason'] ?? 'correction');
        if ($counted < 0) {
            $errors[] = 'A counted quantity cannot be negative.';
        } else {
            $result = stock_set_absolute($id, $counted, is_inventory_reason($reason) ? $reason : 'correction', [
                'note'  => trim((string) ($_POST['note'] ?? '')) ?: 'Reconciled against a physical count',
                'actor' => $actor,
            ]);
            if ($result['ok']) {
                flash('success', $result['message'] . ' Difference of '
                    . ($result['applied'] >= 0 ? '+' : '') . number_format($result['applied'])
                    . ' units logged as ' . strtolower(inventory_reason_meta($reason)['label']) . '.');
            } else {
                $errors[] = $result['message'];
            }
        }
    }

    if ($action === 'settings') {
        $threshold = (int) ($_POST['low_stock_threshold'] ?? 5);
        $cost      = round((float) ($_POST['cost_price'] ?? 0), 2);
        $supplier  = trim((string) ($_POST['supplier'] ?? ''));
        $sku       = trim((string) ($_POST['sku'] ?? ''));

        if ($threshold < 0) {
            $errors[] = 'The reorder threshold cannot be negative.';
        } elseif ($cost < 0) {
            $errors[] = 'The unit cost cannot be negative.';
        } elseif (mb_strlen($supplier) > 120) {
            $errors[] = 'That supplier name is too long (120 characters maximum).';
        } elseif ($sku !== '' && $db->value('SELECT 1 FROM products WHERE sku = ? AND id <> ?', [$sku, $id])) {
            $errors[] = 'Another product already uses the code "' . $sku . '".';
        } else {
            $db->run(
                'UPDATE products SET low_stock_threshold = ?, cost_price = ?, supplier = ?, sku = ? WHERE id = ?',
                [$threshold, $cost, $supplier, $sku, $id]
            );
            flash('success', 'Inventory settings saved.');
        }
    }

    if (!$errors) {
        redirect('product_stock.php?id=' . $id);
    }

    $product = $db->one('SELECT * FROM products WHERE id = ?', [$id]);
}

$stock     = (int) $product['stock'];
$threshold = (int) $product['low_stock_threshold'];
$status    = stock_status($stock, $threshold);
$cost      = (float) $product['cost_price'];
$value     = $stock * $cost;

$movements = inventory_product_movements($id, 60);

$out30 = (int) ($db->value(
    "SELECT COALESCE(SUM(quantity), 0) FROM stock_movements
     WHERE product_id = ? AND direction = 'out' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    [$id]
) ?? 0);
$perDay = $out30 / 30;
$cover  = $perDay > 0 ? (int) floor($stock / $perDay) : null;

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">
            <a href="inventory.php" style="color:inherit">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Inventory
            </a>
        </p>
        <h1><?= e($product['name']) ?></h1>
        <p>
            <?= e($product['sku']) ?> &middot; <?= e($product['category']) ?>
            <?php if ($product['supplier'] !== ''): ?>
                &middot; <?= e($product['supplier']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="update_product.php?id=<?= (int) $id ?>">
            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit product
        </a>
        <a class="btn btn--ghost" target="_blank" rel="noopener" href="../quick_view.php?pid=<?= (int) $id ?>">
            <i class="fa-solid fa-eye" aria-hidden="true"></i> Preview
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
        <span class="stat__icon stat__icon--<?= e($status['tag']) ?>" aria-hidden="true">
            <i class="fa-solid fa-boxes-stacked"></i>
        </span>
        <div>
            <p class="stat__label">On hand</p>
            <p class="stat__value"><?= e(number_format($stock)) ?></p>
            <p class="stat__note">
                <span class="tag tag--<?= e($status['tag']) ?>"><?= e($status['label']) ?></span>
            </p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon stat__icon--info" aria-hidden="true"><i class="fa-solid fa-warehouse"></i></span>
        <div>
            <p class="stat__label">Stock value</p>
            <p class="stat__value"><?= e(money($value)) ?></p>
            <p class="stat__note"><?= e(money($cost)) ?> each at cost</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon stat__icon--warn" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
        <div>
            <p class="stat__label">Sold (30 days)</p>
            <p class="stat__value"><?= e(number_format($out30)) ?></p>
            <p class="stat__note"><?= e(number_format($perDay, 1)) ?> per day</p>
        </div>
    </article>

    <article class="stat">
        <span class="stat__icon <?= $cover !== null && $cover <= 7 ? 'stat__icon--danger' : 'stat__icon--success' ?>"
              aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
        <div>
            <p class="stat__label">Days of cover</p>
            <p class="stat__value"><?= $cover === null ? '&mdash;' : e(number_format($cover)) ?></p>
            <p class="stat__note">
                <?php if ($cover === null): ?>
                    No sales in the last 30 days
                <?php elseif ($cover <= 7): ?>
                    Running low at this rate
                <?php else: ?>
                    At the current rate
                <?php endif; ?>
            </p>
        </div>
    </article>
</div>

<?php if ($threshold > 0 && $stock <= $threshold): ?>
    <div class="notice notice--warn" role="status">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
            <strong>This product needs reordering</strong>
            <p>
                <?= e(number_format($stock)) ?> on hand against a reorder threshold of
                <?= e(number_format($threshold)) ?>. Ordering
                <?= e(number_format(max($threshold * 2 - $stock, $threshold > 0 ? 1 : 0))) ?>
                would bring it back to twice the threshold.
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div>
        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Adjust stock</h2>
                <span class="tag">Every change is logged</span>
            </div>
            <div class="card__body">
                <form method="post" action="product_stock.php?id=<?= (int) $id ?>" class="stackform">
                    <input type="hidden" name="form_action" value="restock">
                    <h3 class="stackform__title">
                        <i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i>
                        Receive a delivery
                    </h3>
                    <div class="formgrid">
                        <div class="field">
                            <label class="field__label" for="add-qty">Units received</label>
                            <input class="input" id="add-qty" type="number" name="qty" min="1" step="1"
                                   value="1" required inputmode="numeric">
                        </div>
                        <div class="field">
                            <label class="field__label" for="add-ref">Reference <span class="field__opt">(optional)</span></label>
                            <input class="input" id="add-ref" type="text" name="reference" maxlength="60"
                                   placeholder="PO number, invoice…">
                        </div>
                        <div class="field field--full">
                            <label class="field__label" for="add-note">Note <span class="field__opt">(optional)</span></label>
                            <input class="input" id="add-note" type="text" name="note" maxlength="255"
                                   placeholder="What arrived">
                        </div>
                    </div>
                    <button class="btn btn--success" type="submit">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add to stock
                    </button>
                </form>

                <hr class="rule">

                <form method="post" action="product_stock.php?id=<?= (int) $id ?>" class="stackform">
                    <input type="hidden" name="form_action" value="remove">
                    <h3 class="stackform__title">
                        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                        Take stock out
                    </h3>
                    <div class="formgrid">
                        <div class="field">
                            <label class="field__label" for="out-qty">Units out</label>
                            <input class="input" id="out-qty" type="number" name="qty" min="1" step="1"
                                   max="<?= max($stock, 1) ?>" value="1" required inputmode="numeric"
                                   <?= $stock === 0 ? 'disabled' : '' ?>>
                            <p class="field__hint">You cannot take out more than the <?= e(number_format($stock)) ?> on hand.</p>
                        </div>
                        <div class="field">
                            <label class="field__label" for="out-reason">Reason</label>
                            <select class="select" id="out-reason" name="reason" <?= $stock === 0 ? 'disabled' : '' ?>>
                                <option value="damage">Damaged or expired</option>
                                <option value="return">Returned by a customer</option>
                                <option value="sale">Sold outside the site</option>
                                <option value="correction">Miscount corrected</option>
                            </select>
                        </div>
                        <div class="field field--full">
                            <label class="field__label" for="out-note">Note <span class="field__opt">(optional)</span></label>
                            <input class="input" id="out-note" type="text" name="note" maxlength="255">
                        </div>
                    </div>
                    <button class="btn btn--danger" type="submit" <?= $stock === 0 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-minus" aria-hidden="true"></i> Remove from stock
                    </button>
                </form>

                <hr class="rule">

                <form method="post" action="product_stock.php?id=<?= (int) $id ?>" class="stackform">
                    <input type="hidden" name="form_action" value="count">
                    <h3 class="stackform__title">
                        <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                        Reconcile a physical count
                    </h3>
                    <div class="formgrid">
                        <div class="field">
                            <label class="field__label" for="ct-qty">Counted quantity</label>
                            <input class="input" id="ct-qty" type="number" name="counted" min="0" step="1"
                                   value="<?= $stock ?>" required inputmode="numeric">
                            <p class="field__hint">System says <?= e(number_format($stock)) ?>. Count what is on the shelf and enter that.</p>
                        </div>
                        <div class="field">
                            <label class="field__label" for="ct-reason">Why the difference?</label>
                            <select class="select" id="ct-reason" name="reason">
                                <?php foreach (['correction' => 'Stocktake correction', 'damage' => 'Damaged or expired', 'return' => 'Customer return', 'opening' => 'Re-opening the count'] as $code => $label): ?>
                                    <option value="<?= e($code) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field field--full">
                            <label class="field__label" for="ct-note">Note <span class="field__opt">(optional)</span></label>
                            <input class="input" id="ct-note" type="text" name="note" maxlength="255"
                                   placeholder="e.g. two packs found behind the shelf">
                        </div>
                    </div>
                    <button class="btn" type="submit">
                        <i class="fa-solid fa-calculator" aria-hidden="true"></i> Reconcile
                    </button>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Inventory settings</h2>
            </div>
            <form class="card__body" method="post" action="product_stock.php?id=<?= (int) $id ?>">
                <input type="hidden" name="form_action" value="settings">
                <div class="formgrid">
                    <div class="field">
                        <label class="field__label" for="s-sku">Stock code</label>
                        <input class="input" id="s-sku" type="text" name="sku" maxlength="40"
                               value="<?= e($product['sku']) ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="s-cost">Unit cost (<?= e(currency_symbol()) ?>)</label>
                        <input class="input" id="s-cost" type="number" name="cost_price" min="0" step="0.01"
                               value="<?= e(number_format($cost, 2, '.', '')) ?>" inputmode="decimal">
                        <p class="field__hint">Used to value the stock you hold.</p>
                    </div>
                    <div class="field">
                        <label class="field__label" for="s-low">Reorder threshold</label>
                        <input class="input" id="s-low" type="number" name="low_stock_threshold" min="0" step="1"
                               value="<?= $threshold ?>" inputmode="numeric">
                        <p class="field__hint">Raise a reorder alert at or below this number. Use 0 to stop tracking.</p>
                    </div>
                    <div class="field">
                        <label class="field__label" for="s-sup">Supplier</label>
                        <input class="input" id="s-sup" type="text" name="supplier" maxlength="120"
                               value="<?= e($product['supplier']) ?>" placeholder="Who we buy it from">
                    </div>
                </div>
                <button class="btn" type="submit" style="margin-top:var(--sp-4)">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save settings
                </button>
            </form>
        </section>
    </div>

    <div>
        <section class="card">
            <div class="card__head">
                <h2 class="card__title">Stock history</h2>
                <span class="tag"><?= e(plural(count($movements), 'movement')) ?></span>
            </div>

            <?php if (!$movements): ?>
                <?= admin_empty('fa-clock-rotate-left', 'No movements yet', 'The first change to this product will appear here.') ?>
            <?php else: ?>
            <div class="card__body card__body--flush">
                <ul class="movelist movelist--tall">
                    <?php foreach ($movements as $m): ?>
                        <?php
                        $meta  = inventory_reason_meta((string) $m['reason']);
                        $delta = (int) $m['qty_change'];
                        ?>
                        <li>
                            <span class="delta delta--<?= e($m['direction']) ?>" aria-hidden="true">
                                <i class="fa-solid <?= e($delta >= 0 ? 'fa-arrow-down' : 'fa-arrow-up') ?>"></i>
                            </span>
                            <span class="movelist__body">
                                <strong><?= e($meta['label']) ?></strong>
                                <small>
                                    <?= e(nice_date((string) $m['created_at'])) ?>
                                    <?= $m['actor'] !== '' ? ' &middot; ' . e($m['actor']) : '' ?>
                                    <?php if (trim((string) $m['note']) !== ''): ?>
                                        &middot; <?= e($m['note']) ?>
                                    <?php endif; ?>
                                    <?php if (trim((string) $m['reference']) !== ''): ?>
                                        &middot; <?= e($m['reference']) ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                            <span class="movelist__figures">
                                <strong class="delta-text delta-text--<?= e($m['direction']) ?>">
                                    <?= $delta >= 0 ? '+' : '&minus;' ?><?= e(number_format(abs($delta))) ?>
                                </strong>
                                <small><?= e(number_format((int) $m['balance_after'])) ?> left</small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card__head" style="border-top:1px solid var(--border)">
                <a class="btn btn--ghost btn--sm btn--block" href="stock_movements.php?product=<?= (int) $id ?>">
                    Open in the full ledger
                </a>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
