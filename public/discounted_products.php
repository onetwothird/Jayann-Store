<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

include '../app/cart_actions.php';
include '../app/views/shop/catalog.php';

$onSale  = true;
$filters['sale'] = true;

$bestOff = (int) $db->value(
    'SELECT COALESCE(MAX((price - discount_price) / NULLIF(price, 0) * 100), 0) FROM products
     WHERE discount > 0 AND discount_price > 0 AND discount_price < price'
);

$pageTitle = 'On sale today';
$pageDesc  = 'Live discounts on drinks, snacks, essentials and personal care at Jayann\'s Store.';
$pageClass = 'page-sale';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>On sale</span>
    </nav>
</div>

<section class="promo-strip">
    <div class="container">
        <div class="promo-strip__inner">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-tags" aria-hidden="true"></i> Limited time</span>
                <h1 class="promo-strip__title">Today&rsquo;s deals</h1>
                <p class="promo-strip__text">
                    <?php if ($bestOff > 0): ?>
                        Up to <strong><?= $bestOff ?>% off</strong> on <?= plural($total, 'item') ?>. While stocks last.
                    <?php else: ?>
                        Fresh markdowns added every week.
                    <?php endif; ?>
                </p>
            </div>
            <a href="products.php" class="btn btn--light btn--lg">
                Browse everything <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</section>

<section class="section section--flush-top">
    <div class="container">
        <div class="filters">
            <span class="filters__count"><strong><?= $total ?></strong> discounted <?= $total === 1 ? 'item' : 'items' ?></span>
            <form class="sortbar" method="get" action="discounted_products.php" data-auto-submit>
                <label class="sr-only" for="sortSelect">Sort deals</label>
                <select class="select" name="sort" id="sortSelect">
                    <?php foreach ($sortOptions as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($items): ?>
            <?php product_grid($items, ['minWidth' => '210px']); ?>

            <?php if ($pages > 1): ?>
                <nav class="pager" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a class="pager__btn" href="<?= e(catalog_url(['page' => $page - 1])) ?>">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span class="sr-only">Previous</span>
                        </a>
                    <?php endif; ?>
                    <?php
                    for ($i = 1; $i <= $pages; $i++):
                        if ($i === 1 || $i === $pages || abs($i - $page) <= 2):
                            echo $i === $page
                                ? '<span class="pager__btn is-current" aria-current="page">' . $i . '</span>'
                                : '<a class="pager__btn" href="' . e(catalog_url(['page' => $i])) . '">' . $i . '</a>';
                        elseif ($i === 2 || $i === $pages - 1):
                            echo '<span class="pager__gap">&hellip;</span>';
                        endif;
                    endfor;
                    ?>
                    <?php if ($page < $pages): ?>
                        <a class="pager__btn" href="<?= e(catalog_url(['page' => $page + 1])) ?>">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span class="sr-only">Next</span>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty">
                <span class="empty__icon"><i class="fa-solid fa-tag" aria-hidden="true"></i></span>
                <h2 class="empty__title">No active discounts right now</h2>
                <p class="empty__text">New markdowns go up every week &mdash; check back soon or browse the full catalogue.</p>
                <a class="btn" href="products.php">Browse all products</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include '../app/views/shop/quick_view_modal.php'; ?>
<?php require '../app/views/layout/footer.php'; ?>

