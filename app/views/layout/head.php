<?php declare(strict_types=1);

$pageTitle = $pageTitle ?? ($config['store']['name'] ?? 'Store');
$pageDesc  = $pageDesc  ?? ($config['store']['tagline'] ?? '');
$pageClass = $pageClass ?? '';

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'home.php');

$store   = $config['store'];
$user_id = $currentUserId = current_user_id();
$cartNum = cart_count($user_id);
$user    = current_user();
$flashes = take_flashes();
$searchQ = trim((string) ($_GET['q'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ee4d2d">
<title><?= e($pageTitle) ?> &middot; <?= e($store['name']) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">

<link rel="icon" type="image/png" href="/Jayann_Store/assets/img/storenijayann.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<link rel="stylesheet" href="/Jayann_Store/assets/css/style.css?v=2.0">
</head>
<body class="<?= e($pageClass) ?>">

<a class="skip-link" href="#main">Skip to main content</a>

<!-- ================= Flash messages ================= -->
<?php if ($flashes): ?>
<div class="toast-stack" id="toastStack" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash):
        $icon = match ($flash['type']) {
            'success' => 'fa-circle-check',
            'error'   => 'fa-circle-exclamation',
            'warning' => 'fa-triangle-exclamation',
            default   => 'fa-circle-info',
        };
    ?>
    <div class="toast toast--<?= e($flash['type']) ?>">
        <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i>
        <span><?= e($flash['message']) ?></span>
        <button type="button" class="toast__close" data-dismiss-toast aria-label="Dismiss">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================= Announcement bar ================= -->
<div class="topbar">
    <div class="container topbar__inner">
        <p class="topbar__note">
            <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
            Free shipping on orders over <strong><?= money($config['order']['free_shipping_over']) ?></strong>
        </p>
        <ul class="topbar__links">
            <li><a href="contact.php">Help</a></li>
            <li><a href="/Jayann_Store/public/orders.php">Track order</a></li>
            <li><a href="<?= e($store['facebook']) ?>" rel="noopener noreferrer" target="_blank">
                <i class="fa-brands fa-facebook-f" aria-hidden="true"></i> Facebook</a></li>
        </ul>
    </div>
</div>

<!-- ================= Header ================= -->
<header class="header" id="siteHeader">
    <div class="container header__inner">

        <a href="/Jayann_Store/public/home.php" class="brand" aria-label="<?= e($store['name']) ?> home">
            <img src="/Jayann_Store/assets/img/storenijayann.png" alt="" class="brand__logo" width="44" height="44">
            <span class="brand__text">
                <strong><?= e($store['name']) ?></strong>
                <small><?= e($store['tagline']) ?></small>
            </span>
        </a>

        <form class="search" action="search.php" method="get" role="search">
            <label class="sr-only" for="siteSearch">Search products</label>
            <input type="search" id="siteSearch" name="q" value="<?= e($searchQ) ?>"
                   placeholder="Search for coffee, shampoo, snacks&hellip;" autocomplete="off">
            <button type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
        </form>

        <div class="header__actions">
            <a href="/Jayann_Store/public/cart.php" class="iconbtn" aria-label="Cart, <?= (int) $cartNum ?> items">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span class="badge-count" data-cart-count><?= (int) $cartNum ?></span>
            </a>

            <div class="account" data-dropdown>
                <button type="button" class="account__trigger" data-dropdown-toggle aria-expanded="false"
                        aria-haspopup="true">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <span class="account__label"><?= $user ? e(explode(' ', $user['name'])[0]) : 'Account' ?></span>
                    <i class="fa-solid fa-chevron-down account__caret" aria-hidden="true"></i>
                </button>

                <div class="account__menu" data-dropdown-menu role="menu">
                    <?php if ($user): ?>
                        <div class="account__head">
                            <span class="account__avatar"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                            <div>
                                <strong><?= e($user['name']) ?></strong>
                                <small><?= e($user['email']) ?></small>
                            </div>
                        </div>
                        <a role="menuitem" href="/Jayann_Store/public/profile.php"><i class="fa-regular fa-user" aria-hidden="true"></i> My profile</a>
                        <a role="menuitem" href="/Jayann_Store/public/orders.php"><i class="fa-solid fa-box" aria-hidden="true"></i> My orders</a>
                        <a role="menuitem" href="/Jayann_Store/public/cart.php"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> My cart</a>
                        <a role="menuitem" class="is-danger" href="/Jayann_Store/public/logout.php"
                           onclick="return confirm('Log out of Jayann\'s Store?');">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Log out</a>
                    <?php else: ?>
                        <div class="account__head">
                            <span class="account__avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                            <div><strong>Welcome</strong><small>Sign in to start shopping</small></div>
                        </div>
                        <a role="menuitem" class="btn btn--block" href="/Jayann_Store/public/login.php">Log in</a>
                        <a role="menuitem" class="btn btn--ghost btn--block" href="/Jayann_Store/public/register.php">Create account</a>
                    <?php endif; ?>
                </div>
            </div>

            <button type="button" class="iconbtn burger" id="navToggle"
                    aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <nav class="catnav" aria-label="Product categories">
        <div class="container catnav__inner">
            <a href="/Jayann_Store/public/products.php" class="catnav__link<?= $currentPage === 'products.php' ? ' is-active' : '' ?>">
                <i class="fa-solid fa-grip" aria-hidden="true"></i> All products
            </a>
            <?php foreach (product_categories() as $catRow):
                $cat = $catRow['category'];
                $isActive = ($currentPage === 'category.php' && ($_GET['category'] ?? '') === $cat);
            ?>
            <a href="/Jayann_Store/public/category.php?category=<?= urlencode($cat) ?>"
               class="catnav__link<?= $isActive ? ' is-active' : '' ?>"><?= e($cat) ?></a>
            <?php endforeach; ?>
            <a href="/Jayann_Store/public/discounted_products.php" class="catnav__link catnav__link--sale<?= $currentPage === 'discounted_products.php' ? ' is-active' : '' ?>">
                <i class="fa-solid fa-tags" aria-hidden="true"></i> On sale
            </a>
        </div>
    </nav>
</header>

<!-- ================= Mobile drawer ================= -->
<div class="drawer" id="mobileNav" hidden>
    <div class="drawer__panel" role="dialog" aria-modal="true" aria-label="Menu">
        <div class="drawer__head">
            <?php if ($user): ?>
                <span class="account__avatar"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($user['name']) ?></strong>
                    <small><?= e($user['email']) ?></small>
                </div>
            <?php else: ?>
                <span class="account__avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                <div><strong>Welcome</strong><small><?= e($store['name']) ?></small></div>
            <?php endif; ?>
            <button type="button" class="drawer__close" data-drawer-close aria-label="Close menu">&times;</button>
        </div>

        <nav class="drawer__nav">
            <a href="/Jayann_Store/public/home.php"<?= $currentPage === 'home.php' ? ' class="is-active"' : '' ?>><i class="fa-solid fa-house" aria-hidden="true"></i> Home</a>
            <a href="/Jayann_Store/public/products.php"<?= $currentPage === 'products.php' ? ' class="is-active"' : '' ?>><i class="fa-solid fa-box-open" aria-hidden="true"></i> All products</a>
            <a href="/Jayann_Store/public/discounted_products.php"<?= $currentPage === 'discounted_products.php' ? ' class="is-active"' : '' ?>><i class="fa-solid fa-tags" aria-hidden="true"></i> On sale</a>
            <a href="about.php"<?= $currentPage === 'about.php' ? ' class="is-active"' : '' ?>><i class="fa-solid fa-circle-info" aria-hidden="true"></i> About us</a>
            <a href="contact.php"<?= $currentPage === 'contact.php' ? ' class="is-active"' : '' ?>><i class="fa-solid fa-headset" aria-hidden="true"></i> Contact</a>
            <a href="/Jayann_Store/public/orders.php"<?= $currentPage === 'orders.php' ? ' class="is-active"' : '' ?>><i class="fa-solid fa-receipt" aria-hidden="true"></i> My orders</a>
            <?php if ($user): ?>
                <a href="/Jayann_Store/public/profile.php"<?= $currentPage === 'profile.php' ? ' class="is-active"' : '' ?>><i class="fa-regular fa-user" aria-hidden="true"></i> My profile</a>
                <a href="/Jayann_Store/public/cart.php"<?= $currentPage === 'cart.php' ? ' class="is-active"' : '' ?>><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> My cart</a>
            <?php endif; ?>
        </nav>

        <?php if ($user): ?>
            <a class="btn btn--ghost btn--block" href="/Jayann_Store/public/logout.php"
               onclick="return confirm('Log out of Jayann\'s Store?');">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Log out</a>
        <?php else: ?>
            <div class="drawer__cta">
                <a class="btn btn--block" href="/Jayann_Store/public/login.php">Log in</a>
                <a class="btn btn--ghost btn--block" href="/Jayann_Store/public/register.php">Create account</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<main id="main">
