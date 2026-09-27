<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login('cart.php');

include '../app/cart_actions.php';

$totals = cart_totals(current_user_id());

$pageTitle = 'Your cart';
$pageDesc  = 'Review the items in your cart before checking out.';
$pageClass = 'page-cart';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Cart</span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">

        <div class="sechead sechead--split">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Step 1 of 2</span>
                <h1 class="sechead__title">Your cart</h1>
                <p class="sechead__sub">
                    <?= $totals['is_empty']
                        ? 'Nothing here yet.'
                        : plural($totals['item_count'], 'item') . ' from ' . plural($totals['line_count'], 'product') . '.' ?>
                </p>
            </div>
            <?php if (!$totals['is_empty']): ?>
                <form method="post" action="cart.php"
                      onsubmit="return confirm('Remove every item from your cart?');">
                    <input type="hidden" name="cart_action" value="clear">
                    <button type="submit" class="btn btn--ghost btn--sm">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i> Empty cart
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($totals['is_empty']): ?>

            <div class="empty">
                <span class="empty__icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span>
                <h2 class="empty__title">Your cart is empty</h2>
                <p class="empty__text">Once you add something it will show up here, along with the live total.</p>
                <a class="btn btn--lg" href="products.php">
                    <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Start shopping
                </a>
            </div>

        <?php else: ?>

            <?php if ($totals['has_issue']): ?>
                <div class="notice notice--warn">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Some items need your attention</strong>
                        <p>Adjust the quantities flagged below &mdash; you can&rsquo;t check out until every item is available.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="cart"
                 data-cart
                 data-free-over="<?= e((string) $config['order']['free_shipping_over']) ?>"
                 data-shipping-fee="<?= e((string) $config['order']['shipping_fee']) ?>">

                <div>
                    <?php foreach ($totals['items'] as $item): ?>
                        <?php
                        $unit    = $item['unit_price'];
                        $qty     = (int) $item['quantity'];
                        $stock   = (int) $item['stock'];
                        $maxQty  = max(1, min($stock > 0 ? $stock : 1, (int) $config['order']['max_qty_per_item']));
                        ?>
                        <article class="citem<?= $item['problem'] !== null ? ' is-issue' : '' ?>" data-line>
                            <a class="citem__media" href="quick_view.php?pid=<?= (int) $item['pid'] ?>"
                               aria-label="View <?= e($item['name']) ?>">
                                <img src="<?= e(product_image($item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
                            </a>

                            <div class="citem__body">
                                <h2 class="citem__name">
                                    <a href="quick_view.php?pid=<?= (int) $item['pid'] ?>"><?= e($item['name']) ?></a>
                                </h2>

                                <div class="citem__meta">
                                    <?php if ($item['category'] !== ''): ?>
                                        <a class="tag" href="category.php?category=<?= urlencode($item['category']) ?>">
                                            <?= e($item['category']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($item['on_sale']): ?>
                                        <span class="tag tag--sale">
                                            -<?= discount_percent($item) ?>% &middot; save <?= money($item['save']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="citem__price">
                                    <b><?= money($unit) ?></b>
                                    <?php if ($item['on_sale']): ?><s><?= money($item['list_price']) ?></s><?php endif; ?>
                                    <span class="citem__each">per item</span>
                                </div>

                                <?php if ($item['problem'] !== null): ?>
                                    <p class="citem__warn">
                                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                        <?= e($item['problem']) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="citem__controls">
                                    <form method="post" action="cart.php" class="citem__qty">
                                        <input type="hidden" name="cart_action" value="update">
                                        <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                                        <label class="sr-only" for="qty-<?= (int) $item['cart_id'] ?>">
                                            Quantity for <?= e($item['name']) ?>
                                        </label>
                                        <input type="number" id="qty-<?= (int) $item['cart_id'] ?>" name="qty"
                                               class="qty-input" value="<?= $qty ?>" min="1" max="<?= $maxQty ?>"
                                               inputmode="numeric"
                                               data-line-unit="<?= e((string) $unit) ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm">Update</button>
                                    </form>

                                    <form method="post" action="cart.php">
                                        <input type="hidden" name="cart_action" value="remove">
                                        <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                                        <button type="submit" class="iconbtn iconbtn--danger"
                                                aria-label="Remove <?= e($item['name']) ?> from cart">
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="citem__side">
                                <span class="citem__total" data-line-total><?= peso($item['line_total']) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="panel summary" aria-label="Order summary">
                    <div class="panel__body summary__body">
                        <h2 class="summary__title">Order summary</h2>

                        <div class="shipbar">
                            <p class="shipbar__text" data-ship-text>
                                <?php if ($totals['subtotal'] >= $config['order']['free_shipping_over']): ?>
                                    You have unlocked free shipping.
                                <?php else: ?>
                                    Add <?= money($config['order']['free_shipping_over'] - $totals['subtotal']) ?>
                                    more for free shipping.
                                <?php endif; ?>
                            </p>
                            <div class="shipbar__track" role="presentation">
                                <div class="shipbar__fill" data-ship-fill style="width:<?= $totals['subtotal'] > 0
                                    ? min(100, (int) round(($totals['subtotal'] / $config['order']['free_shipping_over']) * 100))
                                    : 0 ?>%"></div>
                            </div>
                        </div>

                        <dl class="dl">
                            <div class="dl__row">
                                <dt>Items (<span data-cart-count-total><?= $totals['item_count'] ?></span>)</dt>
                                <dd><?= peso($totals['subtotal']) ?></dd>
                            </div>
                            <?php if ($totals['savings'] > 0): ?>
                                <div class="dl__row">
                                    <dt>You save</dt>
                                    <dd class="dl__save">-<?= peso($totals['savings']) ?></dd>
                                </div>
                            <?php endif; ?>
                            <div class="dl__row">
                                <dt>Delivery</dt>
                                <dd data-cart-shipping><?= $totals['shipping'] > 0 ? peso($totals['shipping']) : 'Free' ?></dd>
                            </div>
                            <div class="dl__row dl__row--total">
                                <dt>Total</dt>
                                <dd><?= e(currency_symbol()) ?><span data-cart-total><?= peso($totals['total']) ?></span></dd>
                            </div>
                        </dl>

                        <div class="summary__foot">
                            <?php if ($totals['has_issue']): ?>
                                <button type="button" class="btn btn--block is-disabled"
                                        aria-disabled="true" tabindex="-1">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Resolve items above
                                </button>
                            <?php else: ?>
                                <a class="btn btn--lg btn--block" href="checkout.php">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Proceed to checkout
                                </a>
                            <?php endif; ?>
                            <a class="btn btn--ghost btn--block" href="products.php">
                                Continue shopping
                            </a>
                        </div>

                        <p class="summary__note">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            Prices and stock are checked again at checkout.
                        </p>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>

