<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login('checkout.php');

$user   = current_user();
$userId = current_user_id();
$totals = cart_totals($userId);

$methods = available_payment_methods();
$errors  = [];

if ($totals['is_empty']) {
    flash('info', 'Your cart is empty Ã¢â‚¬â€ add something before checking out.');
    redirect('cart.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    $totals = cart_totals($userId);
    if ($totals['is_empty']) {
        flash('warning', 'Your cart is empty.');
        redirect('cart.php');
    }
    if ($totals['has_issue']) {
        $errors[] = 'Some items are no longer available in the quantity you asked for.';
    }

    $name    = trim((string) ($_POST['name'] ?? ''));
    $number  = trim((string) ($_POST['number'] ?? ''));
    $email   = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $method  = trim((string) ($_POST['payment_method'] ?? 'cod'));

    if ($name === '' || mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter the recipient&rsquo;s full name.';
    }
    if (!preg_match('/^[0-9+\s\-()]{7,20}$/', $number)) {
        $errors['number'] = 'Enter a valid contact number (mobile or landline).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address for your receipt.';
    }
    if (mb_strlen($address) < 10) {
        $errors['address'] = 'Please give a complete delivery address including barangay.';
    }
    if (!is_available_payment_method($method)) {
        $method = 'cod';
    }

    if (!$errors) {
        $db->pdo()->beginTransaction();
        try {

            $ref = generate_order_ref();

            foreach ($totals['items'] as $item) {
                $locked = $db->one('SELECT stock FROM products WHERE id = ? FOR UPDATE', [(int) $item['pid']]);
                $stock  = (int) ($locked['stock'] ?? 0);
                $qty    = (int) $item['quantity'];
                if ($stock < $qty) {
                    throw new RuntimeException(
                        e($item['name']) . ' now has only ' . $stock . ' left in stock.'
                    );
                }

                $sale = adjust_stock((int) $item['pid'], -$qty, 'sale', [
                    'note'      => 'Sold online',
                    'actor'     => 'storefront',
                    'reference' => $ref,
                ]);
                if (!$sale['ok']) {
                    throw new RuntimeException($sale['message']);
                }
            }

            $orderId = $db->insert(
                'INSERT INTO orders
                    (order_ref, user_id, name, number, email, method, address,
                     total_products, subtotal, shipping_fee, total_price,
                     payment_status, order_date, placed_on)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),CURDATE())',
                [
                    $ref,
                    $userId,
                    $name,
                    $number,
                    $email,
                    $method,
                    $address,

                    json_encode(array_map(
                        static fn($i) => [
                            'pid'      => (int) $i['pid'],
                            'name'     => (string) $i['name'],
                            'quantity' => (int) $i['quantity'],
                            'unit'     => (float) $i['unit_price'],
                        ],
                        $totals['items']
                    ), JSON_UNESCAPED_UNICODE),
                    $totals['subtotal'],
                    $totals['shipping'],
                    $totals['total'],

                    'pending',
                ]
            );

            $db->run('DELETE FROM cart WHERE user_id = ?', [$userId]);

            $db->pdo()->commit();

            flash('success', 'Order ' . $ref . ' placed successfully.');
            redirect('receipt.php?order=' . $orderId);

        } catch (Throwable $e) {
            if ($db->pdo()->inTransaction()) {
                $db->pdo()->rollBack();
            }
            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'We could not place your order. Please try again.';
        }
    }
}

$pageTitle = 'Checkout';
$pageDesc  = 'Complete your order at Jayann\'s Store.';
$pageClass = 'page-checkout';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <a href="cart.php">Cart</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Checkout</span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">

        <div class="sechead">
            <span class="eyebrow"><i class="fa-solid fa-lock" aria-hidden="true"></i> Step 2 of 2</span>
            <h1 class="sechead__title">Checkout</h1>
            <p class="sechead__sub">Confirm where we&rsquo;ll deliver and how you&rsquo;d like to pay.</p>
        </div>

        <?php if ($errors): ?>
            <div class="notice notice--error">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>We couldn&rsquo;t place your order</strong>
                    <ul class="notice__list">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($totals['has_issue']): ?>
            <div class="notice notice--warn">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>Some items are no longer available</strong>
                    <p>Please <a href="cart.php">review your cart</a> before continuing.</p>
                </div>
            </div>
        <?php endif; ?>

        <form class="checkout" method="post" action="checkout.php" novalidate>
            <div class="checkout__form">

                <div class="panel">
                    <div class="panel__head">
                        <h2 class="panel__title"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Delivery details</h2>
                        <a class="btn btn--ghost btn--sm" href="update_address.php">Edit saved address</a>
                    </div>
                    <div class="panel__body">
                        <div class="addrgrid">
                            <div class="field">
                                <label class="field__label" for="coName">Full name</label>
                                <input class="input" type="text" id="coName" name="name" required
                                       maxlength="30" autocomplete="name"
                                       value="<?= e($name ?? $user['name'] ?? '') ?>"
                                       <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
                                <?php if (isset($errors['name'])): ?>
                                    <p class="field__error"><?= $errors['name'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="field">
                                <label class="field__label" for="coNumber">Contact number</label>
                                <input class="input" type="tel" id="coNumber" name="number" required
                                       maxlength="20" autocomplete="tel" placeholder="09XX XXX XXXX"
                                       value="<?= e($number ?? $user['number'] ?? '') ?>"
                                       <?= isset($errors['number']) ? 'aria-invalid="true"' : '' ?>>
                                <?php if (isset($errors['number'])): ?>
                                    <p class="field__error"><?= $errors['number'] ?></p>
                                <?php else: ?>
                                    <p class="field__hint">The rider may call this number on delivery.</p>
                                <?php endif; ?>
                            </div>

                            <div class="field field--full">
                                <label class="field__label" for="coEmail">Email for your receipt</label>
                                <input class="input" type="email" id="coEmail" name="email" required
                                       maxlength="120" autocomplete="email"
                                       value="<?= e($email ?? $user['email'] ?? '') ?>"
                                       <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                                <?php if (isset($errors['email'])): ?>
                                    <p class="field__error"><?= $errors['email'] ?></p>
                                <?php else: ?>
                                    <p class="field__hint">We&rsquo;ll send your order summary here.</p>
                                <?php endif; ?>
                            </div>

                            <div class="field field--full">
                                <label class="field__label" for="coAddress">Delivery address</label>
                                <textarea class="textarea" id="coAddress" name="address" required rows="3"
                                          maxlength="500" autocomplete="street-address"
                                          placeholder="House no. &amp; street, barangay, city, province, ZIP"
                                          <?= isset($errors['address']) ? 'aria-invalid="true"' : '' ?>><?= e($address ?? $user['address'] ?? '') ?></textarea>
                                <?php if (isset($errors['address'])): ?>
                                    <p class="field__error"><?= $errors['address'] ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel__head">
                        <h2 class="panel__title"><i class="fa-solid fa-wallet" aria-hidden="true"></i> Payment method</h2>
                    </div>
                    <div class="panel__body">
                        <div class="paylist" role="radiogroup" aria-label="Payment method">
                            <?php foreach ($methods as $i => $m): ?>
                                <label class="payopt">
                                    <input type="radio" name="payment_method" value="<?= e($m['code']) ?>"
                                           <?= (($method ?? 'cod') === $m['code'] || ($i === 0 && !isset($method))) ? 'checked' : '' ?>>
                                    <span class="payopt__mark" aria-hidden="true"></span>
                                    <span class="payopt__icon"><i class="fa-solid <?= e($m['icon']) ?>" aria-hidden="true"></i></span>
                                    <span class="payopt__text">
                                        <strong><?= e($m['label']) ?></strong>
                                        <small><?= e($m['note']) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="panel summary" aria-label="Order summary">
                <div class="panel__body summary__body">
                    <h2 class="summary__title">Your order</h2>

                    <ul class="orderlist orderlist--tight">
                        <?php foreach ($totals['items'] as $item): ?>
                            <li class="orderitem">
                                <img class="orderitem__img" src="<?= e(product_image($item['image'])) ?>" alt="" loading="lazy">
                                <div class="orderitem__body">
                                    <span class="orderitem__name"><?= e($item['name']) ?></span>
                                    <span class="orderitem__qty">Qty <?= (int) $item['quantity'] ?></span>
                                </div>
                                <span class="orderitem__price"><?= peso($item['line_total']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <dl class="dl">
                        <div class="dl__row">
                            <dt>Subtotal</dt>
                            <dd><?= peso($totals['subtotal']) ?></dd>
                        </div>
                        <?php if ($totals['savings'] > 0): ?>
                            <div class="dl__row">
                                <dt>Discounts</dt>
                                <dd class="dl__save">-<?= peso($totals['savings']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="dl__row">
                            <dt>Delivery</dt>
                            <dd><?= $totals['shipping'] > 0 ? peso($totals['shipping']) : 'Free' ?></dd>
                        </div>
                        <div class="dl__row dl__row--total">
                            <dt>Total due</dt>
                            <dd><?= money($totals['total']) ?></dd>
                        </div>
                    </dl>

                    <div class="summary__foot">
                        <button type="submit" class="btn btn--lg btn--block"
                                <?= $totals['has_issue'] ? 'disabled aria-disabled="true"' : '' ?>>
                            <i class="fa-solid fa-lock" aria-hidden="true"></i> Place order
                        </button>
                        <a class="btn btn--ghost btn--block" href="cart.php">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to cart
                        </a>
                    </div>

                    <p class="summary__note">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        Stock is re-checked the moment you place the order.
                    </p>
                </div>
            </aside>
        </form>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>

