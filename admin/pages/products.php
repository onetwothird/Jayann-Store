<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'products';
$page_title = 'Products';
$page_sub   = 'Add, search and maintain the catalogue';

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['add_product'])) {
    $name     = trim((string) ($_POST['name'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $price    = (float) ($_POST['price'] ?? 0);
    $stock    = (int) ($_POST['stock'] ?? 0);
    $discount = (int) ($_POST['discount'] ?? 0);
    $existing = trim((string) ($_POST['existing_category'] ?? ''));

    if ($name === '') {
        $errors[] = 'The product needs a name.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'That product name is too long (100 characters maximum).';
    }

    if ($category === '') {
        $errors[] = 'Choose a category.';
    }

    if ($price <= 0) {
        $errors[] = 'The price must be greater than zero.';
    }

    if ($stock < 0) {
        $errors[] = 'The stock cannot be negative.';
    }

    if ($discount < 0 || $discount > 90) {
        $errors[] = 'The discount must be between 0 and 90 percent.';
    }

    if (!$errors && $db->value('SELECT 1 FROM products WHERE name = ?', [$name])) {
        $errors[] = 'A product called "' . $name . '" already exists.';
    }

    $image = null;
    if (!$errors) {
        [$image, $uploadError] = admin_handle_upload('image');
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $discount_price = $discount > 0
            ? round($price - ($price * ($discount / 100)), 2)
            : 0.00;

        try {
            $newId = $db->insert(
                'INSERT INTO products (name, category, price, discount, discount_price, image, stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$name, $category, $price, $discount, $discount_price, $image ?? '', $stock]
            );

            if ($stock > 0) {
                open_stock_balance($newId, [
                    'note'  => 'Opening balance when the product was added',
                    'actor' => (string) (current_admin()['name'] ?? 'admin'),
                ]);
            }

            flash('success', '"' . $name . '" was added to the catalogue.');
            redirect('products.php?highlight=' . $newId);
        } catch (PDOException $e) {
            error_log('admin product insert failed: ' . $e->getMessage());
            $errors[] = 'The product could not be saved. Please try again.';
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['form_action'])) {
    $action = (string) $_POST['form_action'];
    $id     = (int) ($_POST['id'] ?? 0);
    $row    = $id > 0 ? $db->one('SELECT * FROM products WHERE id = ?', [$id]) : null;

    if (!$row) {
        flash('error', 'That product no longer exists.');
        redirect('products.php');
    }

    if ($action === 'archive') {

        $db->run(
            'INSERT INTO product_archive
                (name, category, price, image, stock, low_stock_threshold, cost_price, sku, supplier, date_deleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $row['name'],
                $row['category'],
                $row['price'],
                $row['image'],
                (int) $row['stock'],
                (int) ($row['low_stock_threshold'] ?? 5),
                (float) ($row['cost_price'] ?? 0),

                $row['sku'] ?? null,
                $row['supplier'] ?? null,
            ]
        );
        $db->run('DELETE FROM products WHERE id = ?', [$id]);

        $db->run('DELETE FROM cart WHERE pid = ?', [$id]);

        $shelved = (int) $row['stock'];
        flash(
            'success',
            '"' . $row['name'] . '" was moved to the archive'
            . ($shelved > 0
                ? ' with its ' . plural($shelved, 'unit') . ' still in stock. Restoring it will bring them back.'
                : '.')
        );
    } elseif ($action === 'delete') {

        $onHand = (int) $row['stock'];
        if ($onHand > 0) {
            adjust_stock($id, -$onHand, 'archive', [
                'note'  => 'Remaining stock written off when the product was deleted',
                'actor' => (string) (current_admin()['name'] ?? 'admin'),
            ]);
        }
        $db->run('DELETE FROM products WHERE id = ?', [$id]);
        $db->run('DELETE FROM cart WHERE pid = ?', [$id]);
        flash(
            'success',
            '"' . $row['name'] . '" was deleted permanently'
            . ($onHand > 0 ? ', writing off its ' . plural($onHand, 'unit') . ' of stock.' : '.')
        );
    }

    redirect('products.php');
}

$search = admin_q('q');
$filter = admin_q('filter');
$sort   = admin_q('sort', 'name');

$sortSql = match ($sort) {
    'price_desc' => 'p.price DESC',
    'price_asc'  => 'p.price ASC',
    'newest'     => 'p.id DESC',
    'stock'      => 'p.stock ASC, p.name ASC',
    default      => 'p.name ASC',
};

[$whereSql, $params] = admin_product_filter($search, $filter);

$total = (int) ($db->value("SELECT COUNT(*) FROM products p WHERE $whereSql", $params) ?? 0);
$perPage = 12;
$page    = admin_page_no($total, $perPage);
$offset  = ($page - 1) * $perPage;

$products = $db->all(
    "SELECT p.* FROM products p WHERE $whereSql ORDER BY $sortSql LIMIT $perPage OFFSET $offset",
    $params
);

$categories = $db->all('SELECT DISTINCT category FROM products ORDER BY category');
$highlight  = admin_qint('highlight');

$sortOptions = [
    'name'      => 'Name (Aâ€“Z)',
    'newest'    => 'Newest first',
    'price_desc'=> 'Price (high â†’ low)',
    'price_asc' => 'Price (low â†’ high)',
    'stock'     => 'Lowest stock',
];

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Products</h1>
        <p><?= e(plural($total, 'product')) ?> in the catalogue.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="product_archive.php">
            <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Archive
            <span class="sidebar__count"><?= (int) ($db->value('SELECT COUNT(*) FROM product_archive') ?? 0) ?></span>
        </a>
        <a class="btn" href="#add-product">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add product
        </a>
    </div>
</div>

<?php foreach ($errors as $err): ?>
    <div class="notice notice--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <div><strong>Check the form</strong><p><?= e($err) ?></p></div>
    </div>
<?php endforeach; ?>

<!-- =========================================================== add product -->
<section class="card" id="add-product">
    <div class="card__head">
        <h2 class="card__title">Add a product</h2>
        <span class="tag">Price is what the customer pays</span>
    </div>
    <form class="card__body" method="post" action="products.php#add-product" enctype="multipart/form-data" data-validate novalidate>
        <input type="hidden" name="add_product" value="1">

        <div class="formgrid">
            <div class="field field--full">
                <label class="field__label" for="np-name">Product name</label>
                <input class="input" type="text" id="np-name" name="name" required maxlength="100"
                       placeholder="Canned Sardines">
            </div>

            <div class="field">
                <label class="field__label" for="np-category">Category</label>
                <select class="select" id="np-category" name="category" required>
                    <option value="">Choose a categoryâ€¦</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['category']) ?>"><?= e($c['category']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="input" type="text" name="existing_category" style="margin-top:var(--sp-2)"
                       placeholder="â€¦or type a new one" list="np-cats" maxlength="100">
                <datalist id="np-cats">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['category']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="field__hint">Leave the dropdown empty and type a name to create a new category.</p>
            </div>

            <div class="field">
                <label class="field__label" for="np-price">Price (<?= e(currency_symbol()) ?>)</label>
                <input class="input" type="number" id="np-price" name="price" min="0.01" step="0.01" required
                       placeholder="0.00" inputmode="decimal">
            </div>

            <div class="field">
                <label class="field__label" for="np-discount">Discount <span class="field__opt">(optional)</span></label>
                <input class="input" type="number" id="np-discount" name="discount" min="0" max="90" step="1"
                       value="0" inputmode="numeric">
                <p class="field__hint">Percentage off. The sale price is calculated for you.</p>
            </div>

            <div class="field">
                <label class="field__label" for="np-stock">Stock</label>
                <input class="input" type="number" id="np-stock" name="stock" min="0" step="1" value="0"
                       inputmode="numeric">
            </div>

            <div class="field field--full">
                <label class="field__label" for="np-image">Photo <span class="field__opt">(optional, max 2 MB)</span></label>
                <div class="imagefield">
                    <span class="imagefield__preview" id="np-preview">
                        <i class="fa-regular fa-image" style="color:var(--text-soft);font-size:1.5rem" aria-hidden="true"></i>
                    </span>
                    <input class="input" type="file" id="np-image" name="image" accept="image/*" data-image-input="np-preview">
                </div>
            </div>
        </div>

        <button class="btn btn--lg" type="submit">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add product
        </button>
    </form>
</section>

<!-- ================================================================ filters -->
<form class="filters" method="get" action="products.php">
    <div class="searchinline">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input" type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search name or category" aria-label="Search products" data-search>
    </div>

    <input type="hidden" name="sort" value="<?= e($sort) ?>">

    <button class="chip<?= $filter === '' ? ' is-active' : '' ?>" type="submit" name="filter" value="">All</button>
    <button class="chip<?= $filter === 'sale' ? ' is-active' : '' ?>" type="submit" name="filter" value="sale">On sale</button>
    <button class="chip<?= $filter === 'low' ? ' is-active' : '' ?>" type="submit" name="filter" value="low">Low stock</button>
    <button class="chip<?= $filter === 'out' ? ' is-active' : '' ?>" type="submit" name="filter" value="out">Out of stock</button>

    <div class="filters__count">
        <strong><?= e(number_format($total)) ?></strong> <?= $total === 1 ? 'match' : 'matches' ?>
    </div>
</form>

<form method="get" action="products.php" style="display:flex;gap:var(--sp-2);align-items:center;margin-bottom:var(--sp-4);flex-wrap:wrap">
    <input type="hidden" name="q" value="<?= e($search) ?>">
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <label class="field__label" for="sort" style="margin:0">Sort by</label>
    <select class="select" id="sort" name="sort" data-auto-submit style="width:auto;min-width:12rem">
        <?php foreach ($sortOptions as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<!-- ================================================================ listing -->
<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$products): ?>
            <?= admin_empty(
                'fa-magnifying-glass',
                'No products match',
                $search !== '' || $filter !== ''
                    ? 'Try a different search term or clear the filter.'
                    : 'Add your first product using the form above.',
                ($search !== '' || $filter !== '')
                    ? '<a class="btn btn--ghost btn--sm" href="products.php">Clear filters</a>'
                    : '<a class="btn btn--sm" href="#add-product">Add a product</a>'
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
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <?php
                    $price  = effective_price($p);
                    $sale   = has_discount($p);
                    $pct    = discount_percent($p);
                    $stock  = (int) $p['stock'];
                    ?>
                    <tr<?= $highlight === (int) $p['id'] ? ' style="background:var(--brand-50)"' : '' ?>>
                        <td>
                            <span class="cellproduct">
                                <img src="../<?= e(product_image($p['image'])) ?>" alt="" loading="lazy">
                                <span>
                                    <strong><?= e($p['name']) ?></strong>
                                    <small>
                                </span>
                            </span>
                        </td>
                        <td><span class="tag"><?= e($p['category']) ?></span></td>
                        <td class="num">
                            <strong><?= e(money($price)) ?></strong>
                            <?php if ($sale): ?>
                                <br><small style="color:var(--text-muted);text-decoration:line-through"><?= e(money($p['price'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= e(number_format($stock)) ?></td>
                        <td>
                            <?php if ($stock === 0): ?>
                                <span class="tag tag--danger">Out of stock</span>
                            <?php elseif ($stock <= 5): ?>
                                <span class="tag tag--warn">Low</span>
                            <?php else: ?>
                                <span class="tag tag--success">In stock</span>
                            <?php endif; ?>
                            <?php if ($sale): ?>
                                <span class="tag tag--sale">-<?= (int) $pct ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td class="num">
                            <div class="rowactions" style="justify-content:flex-end">
                                <a class="btn btn--sm btn--ghost" href="update_product.php?id=<?= (int) $p['id'] ?>">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                                </a>
                                <a class="btn btn--sm btn--ghost" target="_blank" rel="noopener"
                                   href="../quick_view.php?pid=<?= (int) $p['id'] ?>" title="Preview on the storefront">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <form method="post" action="products.php" data-confirm="Move &quot;<?= e($p['name']) ?>&quot; to the archive? It will be removed from the storefront.">
                                    <input type="hidden" name="form_action" value="archive">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button class="btn btn--sm btn--ghost" type="submit" title="Archive">
                                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <form method="post" action="products.php" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot; permanently? This cannot be undone.">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button class="btn btn--sm btn--danger" type="submit" title="Delete">
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
