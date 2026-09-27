<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

include '../app/cart_actions.php';

$featured = $db->all(
    'SELECT * FROM products WHERE stock > 0 ORDER BY (discount > 0) DESC, id DESC LIMIT 8'
);
$saleItems = $db->all(
    'SELECT * FROM products WHERE discount > 0 AND discount_price > 0 AND discount_price < price
     ORDER BY (price - discount_price) / NULLIF(price, 0) DESC, id DESC LIMIT 8'
);

$cats = $db->all(
    "SELECT category, COUNT(*) AS n FROM products GROUP BY category ORDER BY category"
);
$catImages = [
    'Beverages'   => 'assets/img/drinks.png',
    'Snacks'      => 'assets/img/snack.png',
    'Essentials'  => 'assets/img/must-have.png',
    'Personal Care' => 'assets/img/personal-care.png',
];
$catBlurbs = [
    'Beverages'     => 'Drinks & refreshers',
    'Snacks'        => 'Chips & treats',
    'Essentials'    => 'Everyday staples',
    'Personal Care' => 'Hygiene & beauty',
];

$promos = [
    ['assets/img/promo2.png', 'Fresh restocks every week', 'Beverages, snacks and household staples delivered to your door in Ternate, Cavite.', 'Shop beverages'],
    ['assets/img/promo4.png', 'Groceries without the trip', 'Skip the queue. Browse our full catalogue, fill your cart, and we handle the rest.', 'Browse all products'],
    ['assets/img/promo3.png', 'Deals you can actually use', 'Real discounts on everyday favourites Ã¢â‚¬â€ updated weekly, while stocks last.', 'See todayÃ¢â‚¬â„¢s deals'],
];

$productCount = (int) $db->value('SELECT COUNT(*) FROM products');
$categoryCount = count($cats);

$pageTitle = 'Everyday essentials, delivered';
$pageDesc  = 'Shop beverages, snacks, essentials and personal care at Jayann\'s Store. Fast local delivery in Ternate, Cavite.';
$pageClass = 'page-home';

require '../app/views/layout/head.php';
?>

<!-- ============================== Hero ============================== -->
<section class="hero">
    <div class="swiper hero__swiper">
        <div class="swiper-wrapper">
            <?php foreach ($promos as $promo): ?>
            <div class="swiper-slide hero__slide">
                <img src="<?= e($promo[0]) ?>" alt="" fetchpriority="high">
                <div class="hero__overlay">
                    <h2 class="hero__title"><?= e($promo[1]) ?></h2>
                    <p class="hero__text"><?= e($promo[2]) ?></p>
                    <div class="hero__cta">
                        <a href="products.php" class="btn btn--lg btn--light">
                            <?= e($promo[3]) ?>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
</section>

<!-- =========================== Categories =========================== -->
<section class="section container">
    <div class="sechead sechead--split">
        <div>
            <span class="eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Shop by aisle</span>
            <h2 class="sechead__title">What are you looking for?</h2>
        </div>
        <a href="products.php" class="btn btn--ghost btn--sm">
            View everything <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    </div>

    <div class="cats">
        <?php foreach ($cats as $row):
            $cat = $row['category'];
            $img = $catImages[$cat] ?? 'assets/img/storenijayann.png';
        ?>
        <a class="cats__tile" href="category.php?category=<?= urlencode($cat) ?>">
            <img src="<?= e($img) ?>" alt="" loading="lazy" decoding="async">
            <strong><?= e($cat) ?></strong>
            <span><?= e($catBlurbs[$cat] ?? 'Browse range') ?> &middot; <?= (int) $row['n'] ?> items</span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================ Featured ============================ -->
<section class="section section--flush-top">
    <div class="container">
        <div class="sechead sechead--split">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-fire" aria-hidden="true"></i> Popular right now</span>
                <h2 class="sechead__title">Featured products</h2>
                <p class="sechead__sub">Hand-picked staples that keep getting reordered.</p>
            </div>
            <a href="products.php" class="btn btn--ghost btn--sm">
                All products <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <?php if ($featured): ?>
            <?php product_grid($featured, ['minWidth' => '210px']); ?>
        <?php else: ?>
            <div class="empty">
                <span class="empty__icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                <h3 class="empty__title">No products yet</h3>
                <p class="empty__text">Once products are added they will show up here.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== Promo ============================= -->
<section class="section section--tight">
    <div class="container">
        <div class="promo">
            <h2>Free delivery on orders over <?= money($config['order']['free_shipping_over']) ?></h2>
            <p>Stock up on the whole shop and we&rsquo;ll cover the delivery fee. Order before 6&nbsp;PM for next-day drop-off across Ternate.</p>
            <a href="products.php" class="btn btn--lg btn--light">Start shopping</a>
        </div>
    </div>
</section>

<!-- ============================== On sale =========================== -->
<?php if ($saleItems): ?>
<section class="section section--flush-top">
    <div class="container">
        <div class="sechead sechead--split">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-tags" aria-hidden="true"></i> Limited time</span>
                <h2 class="sechead__title">On sale today</h2>
                <p class="sechead__sub">Up to <?= max(array_map('discount_percent', $saleItems)) ?>% off while stocks last.</p>
            </div>
            <a href="discounted_products.php" class="btn btn--ghost btn--sm">
                All deals <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <?php product_grid($saleItems, ['minWidth' => '210px']); ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== Steps ============================= -->
<section class="section">
    <div class="container">
        <div class="sechead">
            <span class="eyebrow"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> How it works</span>
            <h2 class="sechead__title">Three steps to your doorstep</h2>
        </div>

        <div class="steps">
            <article class="steps__item">
                <span class="steps__num">01</span>
                <div class="steps__icon"><i class="fa-solid fa-store" aria-hidden="true"></i></div>
                <h3>Browse the store</h3>
                <p>Search or filter through <?= plural($productCount, 'product') ?> across <?= plural($categoryCount, 'category') ?>.</p>
            </article>
            <article class="steps__item">
                <span class="steps__num">02</span>
                <div class="steps__icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></div>
                <h3>Fill your cart</h3>
                <p>Set your quantities, review the live total, then check out with your saved delivery address.</p>
            </article>
            <article class="steps__item">
                <span class="steps__num">03</span>
                <div class="steps__icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
                <h3>We deliver it</h3>
                <p>Pay on delivery and we&rsquo;ll drop it off at your door within 1&ndash;2 days.</p>
            </article>
        </div>
    </div>
</section>

<?php include '../app/views/shop/quick_view_modal.php'; ?>
<?php require '../app/views/layout/footer.php'; ?>


