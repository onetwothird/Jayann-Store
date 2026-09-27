<?php declare(strict_types=1);

if (!function_exists('product_card')) {

    function product_card(array $product, array $options = []): void
    {

        $base       = (string) ($options['base'] ?? '');
        $context    = ($options['context'] ?? 'storefront') === 'admin' ? 'admin' : 'storefront';
        $action     = (string) ($options['action'] ?? '');
        $wantTag    = (string) ($options['heading'] ?? 'h3');
        $heading    = in_array($wantTag, ['h2', 'h3', 'h4'], true) ? $wantTag : 'h3';
        $showQuickView = array_key_exists('showQuickView', $options)
            ? (bool) $options['showQuickView']
            : $context === 'storefront';
        $isAdmin      = $context === 'admin';

        $id = (int) ($product['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $name = trim((string) ($product['name'] ?? ''));
        if ($name === '') {
            $name = 'Untitled product';
        }

        $unit      = effective_price($product);
        $listPrice = (float) $product['price'];
        $onSale    = has_discount($product);
        $off       = discount_percent($product);
        $stock     = max(0, (int) ($product['stock'] ?? 0));
        $inStock   = $stock > 0;
        $lowStock  = $inStock && $stock <= 10;
        $category  = trim((string) ($product['category'] ?? ''));

        $maxQty = max(1, min($inStock ? $stock : 1, (int) config_value('order', 'max_qty_per_item', 99)));
        $qty    = max(1, min((int) ($options['quantity'] ?? 1), $maxQty));

        $detailUrl = $base . 'quick_view.php?pid=' . $id;
        $editUrl   = $base . 'update_product.php?id=' . $id;

        static $seq = 0;
        $seq++;
        $uid = 'pc-' . $id . '-' . $seq;

        $image = product_image($product['image'] ?? '');
        $saving = $onSale ? $listPrice - $unit : 0.0;
        ?>
        <article class="pcard<?= $inStock ? '' : ' is-out' ?>" data-product-id="<?= $id ?>">

            <div class="pcard__media">
                <a class="pcard__link" href="<?= e($detailUrl) ?>" tabindex="-1" aria-hidden="true">
                    <img src="<?= e($image) ?>" alt="" <?= $seq <= 4 ? '' : 'loading="lazy"' ?> decoding="async">
                </a>

                <div class="pcard__flags">
                    <?php if ($onSale && $off > 0): ?>
                        <span class="flag flag--sale">-<?= (int) $off ?>%</span>
                    <?php endif; ?>
                    <?php if (!$inStock): ?>
                        <span class="flag flag--out">Sold out</span>
                    <?php endif; ?>
                </div>

                <?php if ($showQuickView || $isAdmin): ?>
                <div class="pcard__actions">
                    <?php if ($showQuickView && $inStock): ?>
                        <button type="button" class="pcard__act" data-quick-view="<?= $id ?>"
                                aria-label="Quick view <?= e($name) ?>">
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                        <a class="pcard__act" href="<?= e($editUrl) ?>" aria-label="Edit <?= e($name) ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="pcard__body">
                <?php if ($category !== ''): ?>
                    <a class="pcard__cat" href="<?= e($base) ?>category.php?category=<?= urlencode($category) ?>">
                        <?= e($category) ?>
                    </a>
                <?php endif; ?>

                <<?= $heading ?> class="pcard__name">
                    <a href="<?= e($detailUrl) ?>"><?= e($name) ?></a>
                </<?= $heading ?>>

                <div class="pcard__price">
                    <span class="pcard__amount"><?= e(money($unit)) ?></span>
                    <?php if ($onSale): ?>
                        <s class="pcard__was"><?= e(money($listPrice)) ?></s>
                    <?php endif; ?>
                </div>

                <?php if ($isAdmin): ?>
                    <div class="pcard__foot pcard__foot--admin">
                        <span class="pcard__stock<?= $lowStock ? ' is-low' : '' ?><?= $inStock ? '' : ' is-out' ?>">
                            <?= $inStock ? number_format($stock) . ' in stock' : 'Out of stock' ?>
                        </span>
                        <a class="pcard__editbtn" href="<?= e($editUrl) ?>">Edit</a>
                    </div>
                <?php else: ?>
                    <div class="pcard__foot">
                        <span class="pcard__stock<?= $lowStock ? ' is-low' : '' ?><?= $inStock ? '' : ' is-out' ?>">
                            <?php if (!$inStock): ?>
                                Out of stock
                            <?php elseif ($lowStock): ?>
                                Only <?= (int) $stock ?> left
                            <?php else: ?>
                                In stock
                            <?php endif; ?>
                        </span>

                        <form method="post" action="<?= e($action) ?>" class="pcard__add" data-add-to-cart>
                            <input type="hidden" name="cart_action" value="add">
                            <input type="hidden" name="pid" value="<?= $id ?>">
                            <label class="sr-only" for="<?= $uid ?>-qty">Quantity for <?= e($name) ?></label>
                            <input type="number" id="<?= $uid ?>-qty" name="qty" class="qty-input pcard__qty"
                                   value="<?= $qty ?>" min="1" max="<?= $maxQty ?>"
                                   inputmode="numeric" <?= $inStock ? '' : 'disabled' ?>>
                            <button type="submit" class="pcard__addbtn"
                                    <?= $inStock ? '' : 'disabled aria-disabled="true"' ?>
                                    aria-label="Add <?= e($name) ?> to cart">
                                <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    function product_grid(array $items, array $options = []): void
    {
        if (!$items) {
            return;
        }

        $class    = 'pgrid' . (isset($options['class']) ? ' ' . preg_replace('/[^a-z0-9 _-]/i', '', (string) $options['class']) : '');
        $minWidth = (string) ($options['minWidth'] ?? '210px');
        ?>
        <div class="<?= e($class) ?>" style="--card-min: <?= e($minWidth) ?>">
            <?php foreach ($items as $item) {
                product_card($item, $options);
            } ?>
        </div>
        <?php
    }
}

