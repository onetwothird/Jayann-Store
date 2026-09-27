<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'products';
$page_title = 'Edit product';

$id = admin_qint('id');
if ($id <= 0) {
    $id = (int) ($_POST['id'] ?? 0);
}

$product = $id > 0 ? $db->one('SELECT * FROM products WHERE id = ?', [$id]) : null;

if (!$product) {
    flash('error', 'That product could not be found.');
    redirect('products.php');
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update'])) {
    $name     = trim((string) ($_POST['name'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $price    = (float) ($_POST['price'] ?? 0);
    $stock    = (int) ($_POST['stock'] ?? 0);
    $discount = (int) ($_POST['discount'] ?? 0);

    if ($name === '') {
        $errors[] = 'The product needs a name.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'That product name is too long (100 characters maximum).';
    } elseif ($name !== $product['name'] && $db->value('SELECT 1 FROM products WHERE name = ?', [$name])) {
        $errors[] = 'Another product is already called "' . $name . '".';
    }

    if ($category === '') {
        $errors[] = 'Choose or type a category.';
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

    $newImage = null;
    if (!$errors) {
        [$newImage, $uploadError] = admin_handle_upload('image');
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $discount_price = $discount > 0
            ? round($price - ($price * ($discount / 100)), 2)
            : 0.00;

        $db->run(
            'UPDATE products SET name = ?, category = ?, price = ?, discount = ?, discount_price = ? WHERE id = ?',
            [$name, $category, $price, $discount, $discount_price, $id]
        );

        if ($newImage !== null) {
            $db->run('UPDATE products SET image = ? WHERE id = ?', [$newImage, $id]);
            admin_delete_upload((string) $product['image']);
        }

        $message = '"' . $name . '" was updated.';

        if ($stock !== (int) $product['stock']) {
            $result = stock_set_absolute($id, $stock, 'correction', [
                'note'  => 'Counted while editing the product',
                'actor' => (string) (current_admin()['name'] ?? 'admin'),
            ]);
            $message .= $result['ok']
                ? ' Stock ' . number_format($result['applied'] > 0 ? $result['applied'] : 0)
                    . ($result['applied'] > 0 ? ' added' : '')
                    . ', now ' . number_format($result['balance']) . '.'
                : ' ' . $result['message'];
            if (!$result['ok']) {
                flash('error', $message);
                redirect('update_product.php?id=' . $id);
            }
        }

        flash('success', $message);
        redirect('products.php?highlight=' . $id);
    }

    $product = array_merge($product, [
        'name' => $name, 'category' => $category, 'price' => $price,
        'stock' => $stock, 'discount' => $discount,
    ]);
}

$page_sub   = (string) $product['name'];
$categories = $db->all('SELECT DISTINCT category FROM products ORDER BY category');

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">
            <a href="products.php" style="color:inherit">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Products
            </a>
        </p>
        <h1>Edit product</h1>
        <p>Product
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" target="_blank" rel="noopener" href="../quick_view.php?pid=<?= (int) $product['id'] ?>">
            <i class="fa-solid fa-eye" aria-hidden="true"></i> Preview
        </a>
    </div>
</div>

<?php foreach ($errors as $err): ?>
    <div class="notice notice--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <div><strong>Nothing was saved</strong><p><?= e($err) ?></p></div>
    </div>
<?php endforeach; ?>

<form method="post" action="update_product.php?id=<?= (int) $product['id'] ?>" enctype="multipart/form-data" data-validate novalidate>
    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">

    <div class="grid-2">
        <section class="card">
            <div class="card__head"><h2 class="card__title">Details</h2></div>
            <div class="card__body">
                <div class="formgrid">
                    <div class="field field--full">
                        <label class="field__label" for="name">Product name</label>
                        <input class="input" type="text" id="name" name="name" required maxlength="100"
                               value="<?= e($product['name']) ?>">
                    </div>

                    <div class="field field--full">
                        <label class="field__label" for="category">Category</label>
                        <input class="input" type="text" id="category" name="category" required maxlength="100"
                               list="cats" value="<?= e($product['category']) ?>">
                        <datalist id="cats">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= e($c['category']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="field">
                        <label class="field__label" for="price">Price (<?= e(currency_symbol()) ?>)</label>
                        <input class="input" type="number" id="price" name="price" min="0.01" step="0.01" required
                               inputmode="decimal" value="<?= e($product['price']) ?>">
                    </div>

                    <div class="field">
                        <label class="field__label" for="stock">Stock on hand</label>
                        <input class="input" type="number" id="stock" name="stock" min="0" step="1"
                               inputmode="numeric" value="<?= (int) $product['stock'] ?>">
                        <p class="field__hint">
                            Change this only when you have counted the shelf. The difference is logged to
                            the stock ledger as a stocktake correction.
                            <a href="product_stock.php?id=<?= (int) $product['id'] ?>">Open stock control &rarr;</a>
                        </p>
                    </div>

                    <div class="field field--full">
                        <label class="field__label" for="discount">Discount <span class="field__opt">(percent off)</span></label>
                        <input class="input" type="number" id="discount" name="discount" min="0" max="90" step="1"
                               inputmode="numeric" value="<?= (int) $product['discount'] ?>">
                        <p class="field__hint">
                            At <?= (int) $product['discount'] ?>% off a <?= e(money($product['price'])) ?> item sells for
                            <strong><?= e(money(effective_price($product))) ?></strong>.
                        </p>
                    </div>
                </div>

                <button class="btn btn--lg" type="submit" name="update" value="1">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save changes
                </button>
            </div>
        </section>

        <div>
            <section class="card">
                <div class="card__head"><h2 class="card__title">Photo</h2></div>
                <div class="card__body">
                    <div class="imagefield" style="margin-bottom:var(--sp-4)">
                        <span class="imagefield__preview" id="preview">
                            <img src="<?= e(product_image($product['image'])) ?>" alt="Current product photo">
                        </span>
                        <div>
                            <p class="field__label" style="margin-bottom:var(--sp-1)">Current image</p>
                            <p class="field__hint" style="margin:0">
                                Upload a new file to replace it. The old file is removed automatically.
                            </p>
                        </div>
                    </div>
                    <input class="input" type="file" name="image" accept="image/*" data-image-input="preview">
                    <p class="field__hint">JPG, PNG, GIF or WEBP up to 2 MB.</p>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><h2 class="card__title">Live preview</h2></div>
                <div class="card__body">
                    <?php
                    product_card($product, [
                        'base'    => '../',
                        'context' => 'admin',
                    ]);
                    ?>
                    <p class="field__hint">This is exactly how the card appears on the storefront.</p>
                </div>
            </section>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
