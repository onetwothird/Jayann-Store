<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

include '../app/cart_actions.php';

$pid     = (int) ($_GET['pid'] ?? 0);
$partial = isset($_GET['partial']);

$product = $pid > 0 ? $db->one('SELECT * FROM products WHERE id = ?', [$pid]) : null;

if (!$product) {
    if ($partial) {
        http_response_code(404);
        echo '<div class="qv__loading"><p>Sorry, we could not find that product.</p></div>';
        exit;
    }

    http_response_code(404);
    $pageTitle = 'Product not found';
    $pageDesc  = 'That product is no longer available.';
    $pageClass = 'page-product';
    require '../app/views/layout/head.php';
    echo '<section class="section container">'
       . '<div class="empty">'
       . '<span class="empty__icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>'
       . '<h1 class="empty__title">Product not found</h1>'
       . '<p class="empty__text">It may have been removed from the catalogue.</p>'
       . '<a class="btn" href="products.php">Browse all products</a>'
       . '</div></section>';
    require '../app/views/layout/footer.php';
    exit;
}

$unit     = effective_price($product);
$onSale   = has_discount($product);
$off      = discount_percent($product);
$stock    = (int) $product['stock'];
$inStock  = $stock > 0;
$maxQty   = max(1, min($inStock ? $stock : 1, (int) $config['order']['max_qty_per_item']));

$cartQty = 0;
if (is_logged_in()) {
    $cartQty = (int) $db->value(
        'SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ? AND pid = ?',
        [current_user_id(), $pid]
    );
}
$remaining = max(0, $stock - $cartQty);
$soldOut   = !$inStock;
$allInCart = $inStock && $remaining <= 0;
$sku       = 'JYS-' . str_pad((string) $pid, 4, '0', STR_PAD_LEFT);

$related = $db->all(
    'SELECT * FROM products WHERE category = ? AND id <> ? ORDER BY (discount > 0) DESC, id DESC LIMIT 4',
    [$product['category'], $pid]
);
if (count($related) < 4) {
    $seen = array_merge([$pid], array_column($related, 'id'));
    $ph   = implode(',', array_fill(0, count($seen), '?'));
    $filler = $db->all(
        "SELECT * FROM products WHERE id NOT IN ($ph) ORDER BY (discount > 0) DESC, id DESC LIMIT " . (4 - count($related)),
        $seen
    );
    $related = array_merge($related, $filler);
}

$renderBody = static function () use (
    $product, $pid, $sku, $unit, $onSale, $off, $stock, $inStock,
    $maxQty, $remaining, $soldOut, $allInCart, $related
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
                <?php if ($soldOut): ?>
                    <span class="flag flag--out">Sold out</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="qv__body">
            <?php if (($product['category'] ?? '') !== ''): ?>
                <a class="qv__cat" href="category.php?category=<?= urlencode((string) $product['category']) ?>">
                    <?= e($product['category']) ?>
                </a>
            <?php endif; ?>

            <h2 class="qv__name"><?= e($product['name']) ?></h2>

            <div class="qv__price">
                <b><?= e(money($unit)) ?></b>
                <?php if ($onSale): ?>
                    <s><?= e(money($listPrice)) ?></s>
                    <span class="tag tag--sale">Save <?= e(money($listPrice - $unit)) ?></span>
                <?php endif; ?>
            </div>

            <dl class="qv__meta">
                <div>
                    <dt>Availability</dt>
                    <dd>
                        <?php if ($soldOut): ?>
                            <span class="tag tag--danger">Out of stock</span>
                        <?php elseif ($allInCart): ?>
                            <span class="tag tag--warn">All <?= (int) $stock ?> already in your cart</span>
                        <?php elseif ($remaining <= 10): ?>
                            <span class="tag tag--warn">Only <?= (int) $remaining ?> left</span>
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
            </dl>

            <?php if (!$soldOut): ?>
                <form method="post" action="quick_view.php" class="qv__buy" data-qv-form>
                    <input type="hidden" name="cart_action" value="add">
                    <input type="hidden" name="pid" value="<?= (int) $pid ?>">
                    <label class="sr-only" for="qvQty">Quantity</label>
                    <input type="number" id="qvQty" name="qty" class="qty-input qv__qty"
                           value="1" min="1" max="<?= (int) $maxQty ?>" inputmode="numeric">
                    <button type="submit" class="btn btn--lg qv__addbtn" <?= $allInCart ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
                        <?= $allInCart ? 'All stock in your cart' : 'Add to cart' ?>
                    </button>
                </form>
            <?php else: ?>
                <div class="qv__buy">
                    <a class="btn btn--ghost btn--lg" href="products.php">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Continue shopping
                    </a>
                </div>
            <?php endif; ?>

            <ul class="trustlist">
                <li><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Delivery in 1&ndash;2 days</li>
                <li><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> 7-day replacement</li>
                <li><i class="fa-solid fa-cash-register" aria-hidden="true"></i> Pay on delivery</li>
            </ul>
        </div>
    </div>

    <?php if ($related): ?>
    <section class="qvrelated" aria-labelledby="qvrelatedTitle">
        <h2 class="qvrelated__title" id="qvrelatedTitle">You might also like</h2>
        <?php product_grid($related, ['minWidth' => '150px', 'class' => 'pgrid--related']); ?>
    </section>
    <?php endif;
};

if ($partial) {
    $renderBody();
    exit;
}

$pageTitle = (string) $product['name'];
$pageDesc  = 'Buy ' . $product['name'] . ' at ' . $config['store']['legal'] . '.';
$pageClass = 'page-product';
$pageImage = product_image($product['image'] ?? '');

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <a href="products.php">Products</a>
        <?php if (($product['category'] ?? '') !== ''): ?>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <a href="category.php?category=<?= urlencode((string) $product['category']) ?>"><?= e($product['category']) ?></a>
        <?php endif; ?>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page"><?= e($product['name']) ?></span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">
        <div class="panel panel--product">
            <?php $renderBody(); ?>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>

