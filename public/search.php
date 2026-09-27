<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

include '../app/cart_actions.php';
include '../app/views/shop/catalog.php';

$pageTitle = $filters['q'] !== '' ? 'Results for Ã¢â‚¬Å“' . $filters['q'] . 'Ã¢â‚¬Â' : 'Search';
$pageDesc  = 'Search Jayann\'s Store for drinks, snacks, essentials and personal care.';
$pageClass = 'page-search';

require '../app/views/layout/head.php';

$suggestions = array_slice(product_categories(), 0, 6);
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Search</span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">

        <div class="sechead">
            <span class="eyebrow"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</span>
            <h1 class="sechead__title">
                <?= $filters['q'] !== '' ? 'Results for &ldquo;' . e($filters['q']) . '&rdquo;' : 'What are you looking for?' ?>
            </h1>
            <p class="sechead__sub">
                <?= $filters['q'] === ''
                    ? 'Search the full catalogue by product name or department.'
                    : plural($total, 'match', 'matches') . ' found.' ?>
            </p>
        </div>

        <?php if ($filters['q'] === ''): ?>
            <div class="filters" role="group" aria-label="Browse by category">
                <span class="filters__count">Or browse by department</span>
                <?php foreach ($suggestions as $row): $cat = $row['category']; ?>
                    <a class="chip" href="category.php?category=<?= urlencode($cat) ?>"><?= e($cat) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($items): ?>
            <div class="filters">
                <span class="filters__count">
                    <?php if ($filters['q'] !== ''): ?>
                        Showing <strong><?= (int) ($offset + 1) ?>&ndash;<?= (int) min($offset + $perPage, $total) ?></strong>
                        of <strong><?= $total ?></strong>
                    <?php endif; ?>
                </span>
                <form class="sortbar" method="get" action="search.php" data-auto-submit>
                    <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                    <label class="sr-only" for="sortSelect">Sort results</label>
                    <select class="select" name="sort" id="sortSelect">
                        <?php foreach ($sortOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

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
        <?php elseif ($filters['q'] !== ''): ?>
            <div class="empty">
                <span class="empty__icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                <h2 class="empty__title">No results for &ldquo;<?= e($filters['q']) ?>&rdquo;</h2>
                <p class="empty__text">Check the spelling, try a shorter phrase, or browse the full catalogue instead.</p>
                <a class="btn" href="products.php">Browse all products</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include '../app/views/shop/quick_view_modal.php'; ?>
<?php require '../app/views/layout/footer.php'; ?>

