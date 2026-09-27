<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

include '../app/cart_actions.php';
include '../app/views/shop/catalog.php';

$pageTitle = 'All products';
$pageDesc  = 'Browse every product at Jayann\'s Store — beverages, snacks, essentials and personal care.';
$pageClass = 'page-products';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>All products</span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">
        <div class="sechead sechead--split">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-box-open" aria-hidden="true"></i> Catalogue</span>
                <h1 class="sechead__title">All products</h1>
                <p class="sechead__sub">
                    <?= plural($total, 'product') ?> available
                    <?= $filters['category'] !== '' ? 'in ' . e($filters['category']) : 'across ' . plural(count(product_categories()), 'category') ?>.
                </p>
            </div>

            <form class="sortbar" method="get" action="products.php" data-auto-submit>
                <?php foreach (['q' => $filters['q'], 'category' => $filters['category'],
                                'min' => $filters['min'], 'max' => $filters['max'],
                                'stock' => $filters['stock'] ?: null, 'sale' => $filters['sale'] ?: null] as $k => $v): ?>
                    <?php if ($v !== null && $v !== ''): ?>
                        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label class="sr-only" for="sortSelect">Sort products</label>
                <select class="select" name="sort" id="sortSelect">
                    <?php foreach ($sortOptions as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="filters" role="group" aria-label="Filter by category">
            <a class="chip<?= $filters['category'] === '' ? ' is-active' : '' ?>"
               href="<?= e(catalog_url(['category' => null, 'page' => 1])) ?>">
                <i class="fa-solid fa-grip" aria-hidden="true"></i> All
            </a>
            <?php foreach (product_categories() as $catRow): $cat = $catRow['category']; ?>
                <a class="chip<?= $filters['category'] === $cat ? ' is-active' : '' ?>"
                   href="<?= e(catalog_url(['category' => $filters['category'] === $cat ? null : $cat, 'page' => 1])) ?>">
                    <?= e($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="layout-split">
            <aside class="filterside" aria-label="Refine results">
                <form method="get" action="products.php" class="filterside__form">
                    <?php if ($filters['q'] !== ''): ?>
                        <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                    <?php endif; ?>
                    <?php if ($filters['category'] !== ''): ?>
                        <input type="hidden" name="category" value="<?= e($filters['category']) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="sort" value="<?= e($sort) ?>">

                    <div class="panel filterside__panel">
                        <div class="panel__head">
                            <h2 class="panel__title">Refine</h2>
                        </div>
                        <div class="panel__body">
                            <div class="field">
                                <label class="field__label" for="fMin">Price range</label>
                                <div class="pricerange">
                                    <input class="input" type="number" id="fMin" name="min" min="0" step="1"
                                           placeholder="Min" value="<?= $filters['min'] !== null ? e((string) $filters['min']) : '' ?>">
                                    <span aria-hidden="true">&ndash;</span>
                                    <input class="input" type="number" name="max" min="0" step="1"
                                           placeholder="Max" value="<?= $filters['max'] !== null ? e((string) $filters['max']) : '' ?>">
                                </div>
                            </div>

                            <label class="check">
                                <input type="checkbox" name="stock" value="1"<?= $filters['stock'] ? ' checked' : '' ?>>
                                <span class="check__mark" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                In stock only
                            </label>

                            <label class="check">
                                <input type="checkbox" name="sale" value="1"<?= $filters['sale'] ? ' checked' : '' ?>>
                                <span class="check__mark" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                On sale
                            </label>

                            <button type="submit" class="btn btn--block">
                                <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                            </button>

                            <a class="btn btn--ghost btn--block" href="products.php">Reset</a>
                        </div>
                    </div>
                </form>
            </aside>

            <div>
                <?php if ($items): ?>
                    <div class="filters">
                        <span class="filters__count">
                            Showing <strong><?= (int) ($offset + 1) ?>&ndash;<?= (int) min($offset + $perPage, $total) ?></strong>
                            of <strong><?= $total ?></strong>
                        </span>
                    </div>

                    <?php product_grid($items, ['minWidth' => '210px']); ?>

                    <?php if ($pages > 1): ?>
                        <nav class="pager" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a class="pager__btn" href="<?= e(catalog_url(['page' => $page - 1])) ?>">
                                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                    <span class="sr-only">Previous page</span>
                                </a>
                            <?php endif; ?>

                            <?php
                            $window = 2;
                            for ($i = 1; $i <= $pages; $i++):
                                if ($i === 1 || $i === $pages || abs($i - $page) <= $window):
                                    $isCurrent = $i === $page;
                                    echo $isCurrent
                                        ? '<span class="pager__btn is-current" aria-current="page">' . $i . '</span>'
                                        : '<a class="pager__btn" href="' . e(catalog_url(['page' => $i])) . '">' . $i . '</a>';
                                elseif ($i === 2 || $i === $pages - 1):
                                    echo '<span class="pager__gap">&hellip;</span>';
                                endif;
                            endfor;
                            ?>

                            <?php if ($page < $pages): ?>
                                <a class="pager__btn" href="<?= e(catalog_url(['page' => $page + 1])) ?>">
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                    <span class="sr-only">Next page</span>
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty">
                        <span class="empty__icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <h2 class="empty__title">No products match those filters</h2>
                        <p class="empty__text">Try widening your price range or clearing a filter to see more.</p>
                        <a class="btn" href="products.php">Clear all filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include '../app/views/shop/quick_view_modal.php'; ?>
<?php require '../app/views/layout/footer.php'; ?>

