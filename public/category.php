<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

include '../app/cart_actions.php';
include '../app/views/shop/catalog.php';

$allCats = product_categories();
$category = $filters['category'];

$pageTitle = $category !== '' ? $category : 'Shop by category';
$pageDesc  = $category !== ''
    ? 'Browse ' . $category . ' at Jayann\'s Store.'
    : 'Every aisle at Jayann\'s Store Ã¢â‚¬â€ beverages, snacks, essentials and personal care.';
$pageClass = 'page-category';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <?php if ($category !== ''): ?>
            <a href="products.php">Products</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span><?= e($category) ?></span>
        <?php else: ?>
            <span>Categories</span>
        <?php endif; ?>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">

        <?php if ($category === ''): ?>
            <div class="sechead">
                <span class="eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Shop by aisle</span>
                <h1 class="sechead__title">All categories</h1>
                <p class="sechead__sub">Pick a department to see everything we carry.</p>
            </div>

            <div class="cats">
                <?php
                $counts = [];
                foreach ($db->all('SELECT category, COUNT(*) AS n FROM products GROUP BY category') as $r) {
                    $counts[$r['category']] = (int) $r['n'];
                }
                $catImages = [
                    'Beverages'     => 'assets/img/drinks.png',
                    'Snacks'        => 'assets/img/snack.png',
                    'Essentials'    => 'assets/img/must-have.png',
                    'Personal Care' => 'assets/img/personal-care.png',
                ];
                $catBlurbs = [
                    'Beverages'     => 'Drinks &amp; refreshers',
                    'Snacks'        => 'Chips &amp; treats',
                    'Essentials'    => 'Everyday staples',
                    'Personal Care' => 'Hygiene &amp; beauty',
                ];
                foreach ($allCats as $row):
                    $cat = $row['category'];
                ?>
                    <a class="cats__tile" href="category.php?category=<?= urlencode($cat) ?>">
                        <img src="<?= e($catImages[$cat] ?? 'assets/img/storenijayann.png') ?>" alt="" loading="lazy" decoding="async">
                        <strong><?= e($cat) ?></strong>
                        <span><?= $catBlurbs[$cat] ?? 'Browse range' ?> &middot; <?= $counts[$cat] ?? 0 ?> items</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>

            <div class="sechead sechead--split">
                <div>
                    <span class="eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Department</span>
                    <h1 class="sechead__title"><?= e($category) ?></h1>
                    <p class="sechead__sub"><?= plural($total, 'product') ?> in this aisle.</p>
                </div>

                <form class="sortbar" method="get" action="category.php" data-auto-submit>
                    <input type="hidden" name="category" value="<?= e($category) ?>">
                    <label class="sr-only" for="sortSelect">Sort products</label>
                    <select class="select" name="sort" id="sortSelect">
                        <?php foreach ($sortOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="filters" role="group" aria-label="Other categories">
                <?php foreach ($allCats as $row): $cat = $row['category']; ?>
                    <a class="chip<?= $cat === $category ? ' is-active' : '' ?>"
                       href="category.php?category=<?= urlencode($cat) ?>"><?= e($cat) ?></a>
                <?php endforeach; ?>
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
                    <span class="empty__icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                    <h2 class="empty__title">Nothing in <?= e($category) ?> right now</h2>
                    <p class="empty__text">We&rsquo;re restocking this aisle. Try another category in the meantime.</p>
                    <a class="btn" href="products.php">Browse all products</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include '../app/views/shop/quick_view_modal.php'; ?>
<?php require '../app/views/layout/footer.php'; ?>

