<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login('profile.php');

$user   = current_user();
$userId = current_user_id();

$stats = $db->one(
    'SELECT COUNT(*)                                   AS orders,
            COALESCE(SUM(total_price), 0)              AS spent,
            COALESCE(SUM(payment_status = "pending"),0) AS pending
     FROM orders WHERE user_id = ?',
    [$userId]
) ?: ['orders' => 0, 'spent' => 0, 'pending' => 0];

$cartLines   = cart_count($userId);
$cartTotals  = cart_totals($userId);
$recentOrders = $db->all(
    'SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 3',
    [$userId]
);

$pageTitle = 'My profile';
$pageDesc  = 'Manage your Jayann\'s Store account details and preferences.';
$pageClass = 'page-profile';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>My profile</span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">

        <div class="sechead">
            <span class="eyebrow"><i class="fa-regular fa-user" aria-hidden="true"></i> My account</span>
            <h1 class="sechead__title">My profile</h1>
            <p class="sechead__sub">Your details prefill every checkout, so keep them up to date.</p>
        </div>

        <div class="profile">

            <aside class="panel profile__card">
                <span class="profile__avatar" aria-hidden="true">
                    <?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?>
                </span>
                <h2 class="profile__name"><?= e($user['name']) ?></h2>
                <p class="profile__email"><?= e($user['email']) ?></p>

                <div class="profile__stats">
                    <div class="profile__stat">
                        <b><?= (int) $stats['orders'] ?></b>
                        <span><?= (int) $stats['orders'] === 1 ? 'Order' : 'Orders' ?></span>
                    </div>
                    <div class="profile__stat">
                        <b><?= (int) $stats['pending'] ?></b>
                        <span>Pending</span>
                    </div>
                </div>

                <div class="profile__actions">
                    <a class="btn btn--block" href="update_profile.php">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit details
                    </a>
                    <a class="btn btn--ghost btn--block" href="orders.php">
                        <i class="fa-solid fa-receipt" aria-hidden="true"></i> My orders
                    </a>
                    <a class="btn btn--ghost btn--block"
                       href="logout.php"
                       onclick="return confirm('Log out of Jayann\'s Store?');">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Log out
                    </a>
                </div>
            </aside>

            <div class="profile__main">
                <div class="panel">
                    <div class="panel__head">
                        <h2 class="panel__title">Account details</h2>
                        <a class="btn btn--ghost btn--sm" href="update_profile.php">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                        </a>
                    </div>
                    <div class="panel__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__icon"><i class="fa-regular fa-user" aria-hidden="true"></i></span>
                                <div class="info-row__body">
                                    <span class="info-row__label">Full name</span>
                                    <span class="info-row__value"><?= e($user['name']) ?></span>
                                </div>
                            </div>
                            <div class="info-row">
                                <span class="info-row__icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                                <div class="info-row__body">
                                    <span class="info-row__label">Email</span>
                                    <span class="info-row__value"><?= e($user['email']) ?></span>
                                </div>
                            </div>
                            <div class="info-row">
                                <span class="info-row__icon"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></span>
                                <div class="info-row__body">
                                    <span class="info-row__label">Mobile number</span>
                                    <?php if (trim((string) $user['number']) !== ''): ?>
                                        <span class="info-row__value"><?= e($user['number']) ?></span>
                                    <?php else: ?>
                                        <span class="info-row__value is-empty">Not set yet</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel__head">
                        <h2 class="panel__title">Delivery address</h2>
                        <a class="btn btn--ghost btn--sm" href="update_address.php">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                        </a>
                    </div>
                    <div class="panel__body">
                        <?php if (trim((string) $user['address']) !== ''): ?>
                            <p class="address"><?= nl2br(e($user['address'])) ?></p>
                        <?php else: ?>
                            <div class="notice notice--info">
                                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                <div>
                                    <strong>No delivery address saved</strong>
                                    <p>Add one so checkout is a single click next time.</p>
                                </div>
                            </div>
                            <a class="btn" href="update_address.php">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add address
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel__head">
                        <h2 class="panel__title">Recent orders</h2>
                        <a class="btn btn--ghost btn--sm" href="orders.php">View all</a>
                    </div>
                    <?php if (!$recentOrders): ?>
                        <div class="panel__body">
                            <p class="muted">You haven&rsquo;t placed an order yet.</p>
                            <a class="btn" href="products.php">
                                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Start shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <ul class="minilist">
                            <?php foreach ($recentOrders as $order):
                                $meta = order_status_meta((string) $order['payment_status']);
                            ?>
                                <li class="minilist__row">
                                    <div>
                                        <strong><?= e($order['order_ref'] ?: ('#' . $order['id'])) ?></strong>
                                        <small><?= nice_date((string) $order['order_date']) ?></small>
                                    </div>
                                    <span class="status status--<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
                                    <b><?= money($order['total_price']) ?></b>
                                    <a class="btn btn--ghost btn--sm" href="receipt.php?order=<?= (int) $order['id'] ?>">
                                        View
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>

