<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'archive';
$page_title = 'Archive';
$page_sub   = 'Products retired from the storefront';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['form_action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);
    $row    = $id > 0 ? $db->one('SELECT * FROM product_archive WHERE id = ?', [$id]) : null;

    if (!$row) {
        flash('error', 'That archived product no longer exists.');
        redirect('product_archive.php');
    }

    if ($action === 'restore') {
        $name = (string) $row['name'];
        if ($db->value('SELECT 1 FROM products WHERE name = ?', [$name])) {
            flash('error', 'A product called "' . $name . '" is already live. Rename one of them first.');
        } else {

            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '' && $db->value('SELECT 1 FROM products WHERE sku = ?', [$sku])) {
                $sku = '';
            }

            $db->run(
                'INSERT INTO products
                    (name, category, price, discount, discount_price, image, stock,
                     low_stock_threshold, cost_price, sku, supplier, last_restocked_on)
                 VALUES (?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, NULL)',
                [
                    $row['name'],
                    $row['category'],
                    $row['price'],
                    $row['image'],
                    (int) $row['stock'],
                    (int) ($row['low_stock_threshold'] ?? 5),
                    (float) ($row['cost_price'] ?? 0),
                    $sku,
                    (string) ($row['supplier'] ?? ''),
                ]
            );
            $newId = (int) $db->pdo()->lastInsertId();

            if ($newId > 0 && (int) $row['stock'] > 0) {
                open_stock_balance($newId, [
                    'note'  => 'Restored from the archive with its remaining stock',
                    'actor' => (string) (current_admin()['name'] ?? 'admin'),
                ]);
            }

            $db->run('DELETE FROM product_archive WHERE id = ?', [$id]);
            flash('success', '"' . $name . '" is back in the catalogue.');
        }
    } elseif ($action === 'purge') {
        admin_delete_upload((string) $row['image']);
        $db->run('DELETE FROM product_archive WHERE id = ?', [$id]);
        flash('success', '"' . $row['name'] . '" was permanently removed.');
    }

    redirect('product_archive.php');
}

$q        = admin_q('q');
$perPage  = 20;
$whereSql = '';
$params   = [];

if ($q !== '') {
    $whereSql = 'WHERE name LIKE ? OR category LIKE ?';
    $params   = ['%' . $q . '%', '%' . $q . '%'];
}

$total  = (int) ($db->value("SELECT COUNT(*) FROM product_archive $whereSql", $params) ?? 0);
$page   = admin_page_no($total, $perPage);
$offset = ($page - 1) * $perPage;

$rows = $db->all(
    "SELECT * FROM product_archive $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset",
    $params
);

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Archive</h1>
        <p><?= e(plural($total, 'product')) ?> retired. Restoring puts a product back on the storefront with no discount.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="products.php">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to products
        </a>
    </div>
</div>

<form class="filters" method="get" action="product_archive.php">
    <div class="searchinline">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input" type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search archived products" aria-label="Search the archive" data-search>
    </div>
    <div class="filters__count"><strong><?= e(number_format($total)) ?></strong> archived</div>
</form>

<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$rows): ?>
            <?= admin_empty(
                'fa-box-archive',
                $q !== '' ? 'Nothing matches that search' : 'The archive is empty',
                $q !== ''
                    ? 'Try a different term.'
                    : 'Archiving a product from the products page files it here so you can bring it back later.'
            ) ?>
        <?php else: ?>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="num">Price</th>
                        <th scope="col" class="num">Stock</th>
                        <th scope="col">Archived</th>
                        <th scope="col" class="num">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <span class="cellproduct">
                                <img src="../<?= e(product_image($r['image'])) ?>" alt="" loading="lazy">
                                <span>
                                    <strong><?= e($r['name']) ?></strong>
                                    <small>Archive
                                </span>
                            </span>
                        </td>
                        <td><span class="tag"><?= e($r['category']) ?></span></td>
                        <td class="num"><?= e(money($r['price'])) ?></td>
                        <td class="num"><?= e(number_format((int) $r['stock'])) ?></td>
                        <td style="white-space:nowrap"><?= e(nice_date((string) $r['date_deleted'], true)) ?></td>
                        <td class="num">
                            <div class="rowactions" style="justify-content:flex-end">
                                <form method="post" action="product_archive.php"
                                      data-confirm="Put &quot;<?= e($r['name']) ?>&quot; back on the storefront?">
                                    <input type="hidden" name="form_action" value="restore">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn--sm btn--success" type="submit">
                                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restore
                                    </button>
                                </form>
                                <form method="post" action="product_archive.php"
                                      data-confirm="Delete &quot;<?= e($r['name']) ?>&quot; and its image permanently?">
                                    <input type="hidden" name="form_action" value="purge">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
