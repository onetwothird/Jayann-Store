<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/views/admin/helpers.php';

require_admin();
boot_session();

$pid = (int) ($_GET['pid'] ?? 0);

$product = $pid > 0 ? $db->one('SELECT * FROM products WHERE id = ?', [$pid]) : null;

if (!$product) {
    http_response_code(404);
    if (isset($_GET['partial'])) {
        echo '<div class="qv__loading"><p>Sorry, we could not find that product.</p></div>';
        exit;
    }
    require __DIR__ . '/../app/views/admin/head.php';
    echo '<div class="page-head"><h1>Product not found</h1></div>';
    echo '<div class="container"><div class="empty"><span class="empty__icon"><i class="fa-solid fa-triangle-exclamation"></i></span><h2>Product not found</h2><p>It may have been removed from the catalogue.</p><a class="btn" href="products.php">Back to products</a></div></div>';
    require __DIR__ . '/../app/views/admin/footer.php';
    exit;
}

$unit   = effective_price($product);
$onSale = has_discount($product);
$off    = discount_percent($product);
$stock  = (int) $product['stock'];
$inStock = $stock > 0;
$sku    = 'JYS-' . str_pad((string) $pid, 4, '0', STR_PAD_LEFT);

$related = $db->all(
    'SELECT * FROM products WHERE category = ? AND id <> ? ORDER BY (discount > 0) DESC, id DESC LIMIT 4',
    [$product['category'], $pid]
);

$renderBody = static function () use (
    $product, $pid, $sku, $unit, $onSale, $off, $stock, $inStock
): void {
    $listPrice = (float) $product['price'];
    $image     = product_image($product['image'] ?? '');
    ?>
    <div class="qv">

        <div class="qv__media">
            <img src="<?= e($image) ?>" alt="<?= e($product['name']) ?>" width="600" height="600">
            <div class="qv__flags">
                <?php if ($onSale && $off > 0): ?>
                    <span class="flag flag--sale">-<?= (int) $off ?>%</span>
                <?php endif; ?>
                <?php if (!$inStock): ?>
                    <span class="flag flag--out">Out of stock</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="qv__body">
            <?php if (($product['category'] ?? '') !== ''): ?>
                <a class="qv__cat" href="../public/category.php?category=<?= urlencode((string) $product['category']) ?>">
                    <?= e($product['category']) ?>
                </a>
            <?php endif; ?>

            <h2 class="qv__name"><?= e($product['name']) ?></h2>

            <div class="qv__price">
                <b><?= e(money($unit)) ?></b>
                <?php if ($onSale): ?>
                    <?= e(money($listPrice)) ?>
                    <span class="tag tag--sale">Save <?= e(money($listPrice - $unit)) ?></span>
                <?php endif; ?>
            </div>

            <dl class="qv__meta">
                <div>
                    <dt>Availability</dt>
                    <dd>
                        <?php if (!$inStock): ?>
                            <span class="tag tag--danger">Out of stock</span>
                        <?php else: ?>
                            <span class="tag tag--success">In stock (<?= (int) $stock ?>)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>Department</dt>
                    <dd><?= e((string) ($product['category'] ?: 'Uncategorised')) ?></dd>
                </div>
                <div>
                    <dt>Product code</dt>
                    <dd><code><?= e($sku) ?></code></dd>
                </div>
                <div>
                    <dt>Cost price</dt>
                    <dd><?= e(money((float) $product['cost_price'])) ?></dd>
                </div>
                <div>
                    <dt>SKU</dt>
                    <dd><?= e($product['sku'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Supplier</dt>
                    <dd><?= e($product['supplier'] ?: '—') ?></dd>
                </div>
            </dl>

            <ul class="trustlist">
                <li><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Delivery in 1–2 days</li>
                <li><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> 7-day replacement</li>
                <li><i class="fa-solid fa-cash-register" aria-hidden="true"></i> Pay on delivery</li>
                <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Secure checkout</li>
            </ul>
        </div>
    </div>
    <?php
};

if (isset($_GET['partial'])) {
    $renderBody();
    exit;
}

$pageTitle = (string) $product['name'];
$pageDesc  = 'Preview of ' . $product['name'] . ' at ' . $config['store']['legal'] . '.';
$admin_page = 'products';

require __DIR__ . '/../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Product Preview</h1>
        <p>How this product appears to customers on the storefront.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="update_product.php?id=<?= (int) $pid ?>">
            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
        </a>
        <a class="btn" href="products.php">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to products
        </a>
    </div>
</div>

<div class="container" style="max-width: 700px; margin: 0 auto;">
    <div class="panel panel--product">
        <?php $renderBody(); ?>
    </div>
</div>

<?php require __DIR__ . '/../app/views/admin/footer.php'; ?>