<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

$productCount  = (int) $db->value('SELECT COUNT(*) FROM products');
$categoryCount = count(product_categories());
$customerCount = (int) $db->value('SELECT COUNT(DISTINCT user_id) FROM orders');
$orderCount    = (int) $db->value('SELECT COUNT(*) FROM orders');
$years          = max(1, (int) date('Y') - 2023);

$pageTitle = 'About us';
$pageDesc  = 'Jayann\'s Store is a neighbourhood sari-sari and grocery in Naic, Cavite.';
$pageClass = 'page-about';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>About us</span>
    </nav>
</div>

<!-- Hero -->
<section class="about-hero">
    <div class="container">
        <div class="about-hero__inner">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-store" aria-hidden="true"></i> Our story</span>
                <h1 class="about-hero__title">Your neighbourhood store, online</h1>
                <p class="about-hero__text">
                    <?= e($config['store']['name']) ?> started as a small counter in
                    <?= e($config['store']['address']) ?>. Customers kept asking why they had to
                    cross town for a bottle of water or a pack of shampoo &mdash; so we built
                    this store to put the whole shelf list online and bring it to your door.
                </p>
                <div class="about-hero__cta">
                    <a class="btn btn--lg" href="products.php">
                        <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Shop the catalogue
                    </a>
                    <a class="btn btn--ghost btn--lg" href="contact.php">Get in touch</a>
                </div>
            </div>
            <div class="about-hero__media">
                <img src="<?= BASE_URL ?>assets/img/grocery-cart.png" alt="A packed grocery cart ready for delivery">
            </div>
        </div>
    </div>
</section>

<!-- Numbers -->
<section class="section section--tight">
    <div class="container">
        <div class="stats">
            <div class="panel stats__item">
                <div class="stats__num"><?= $productCount ?>+</div>
                <div class="stats__label">Products in stock</div>
            </div>
            <div class="panel stats__item">
                <div class="stats__num"><?= $categoryCount ?></div>
                <div class="stats__label">Departments</div>
            </div>
            <div class="panel stats__item">
                <div class="stats__num"><?= $orderCount ?></div>
                <div class="stats__label">Orders fulfilled</div>
            </div>
            <div class="panel stats__item">
                <div class="stats__num"><?= $years ?>d</div>
                <div class="stats__label">Serving Naic</div>
            </div>
        </div>
    </div>
</section>

<!-- What we do -->
<section class="section section--flush-top">
    <div class="container">
        <div class="split">
            <div class="split__media">
                <img src="<?= BASE_URL ?>assets/img/order.png" alt="An order packed and ready to go">
            </div>
            <div class="split__body">
                <span class="eyebrow"><i class="fa-solid fa-heart" aria-hidden="true"></i> What we do</span>
                <h2>Everyday essentials, without the trip</h2>
                <p>
                    We carry the things you actually buy every week &mdash; drinking water, instant
                    noodles, snacks, toothpaste, soap, laundry powder and a few extras. Nothing
                    fancy, everything in stock.
                </p>
                <ul class="checklist">
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span>Live stock counts, so you never order something we&rsquo;ve run out of.</span></li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span>One checkout that shows the real total &mdash; discounts and delivery included.</span></li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span>Pay on delivery, no minimum order, no surprises.</span></li>
                </ul>
                <a class="btn" href="products.php">See what&rsquo;s in stock</a>
            </div>
        </div>
    </div>
</section>

<!-- How we operate -->
<section class="section section--flush-top">
    <div class="container">
        <div class="sechead">
            <span class="eyebrow"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> How we operate</span>
            <h2 class="sechead__title">Ordering, packing, delivering</h2>
            <p class="sechead__sub">Three simple steps, no minimum spend.</p>
        </div>

        <div class="steps">
            <article class="steps__item">
                <span class="steps__num">01</span>
                <div class="steps__icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></div>
                <h3>Fill your cart</h3>
                <p>Set the quantity you want. The total updates as you go.</p>
            </article>
            <article class="steps__item">
                <span class="steps__num">02</span>
                <div class="steps__icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
                <h3>We pack it</h3>
                <p>We confirm stock, reserve your items and pack them the same day.</p>
            </article>
            <article class="steps__item">
                <span class="steps__num">03</span>
                <div class="steps__icon"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i></div>
                <h3>Pay on delivery</h3>
                <p>The rider hands it over and collects cash. Receipt in your account.</p>
            </article>
        </div>
    </div>
</section>

<!-- Promise -->
<section class="section section--flush-top">
    <div class="container">
        <div class="promo">
            <h2>Free delivery on orders over <?= money($config['order']['free_shipping_over']) ?></h2>
            <p>
                Stock up on the whole shop and we cover the delivery fee &mdash;
                anywhere in <?= e($config['store']['address']) ?>, usually next day.
            </p>
            <a class="btn btn--lg btn--light" href="products.php">Start shopping</a>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section section--flush-top" id="faq">
    <div class="container">
        <div class="sechead">
            <span class="eyebrow"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> FAQ</span>
            <h2 class="sechead__title">Questions we get asked</h2>
        </div>

        <div class="acc panel">
            <div class="acc__item">
                <button type="button" class="acc__btn" data-acc-btn aria-expanded="false" aria-controls="faq1">
                    Do you deliver outside Naic?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="acc__panel" id="faq1">
                    We deliver across <?= e($config['store']['address']) ?> and the neighbouring
                    barangays. If you&rsquo;re just outside the list, message us on Facebook with
                    your address and we&rsquo;ll tell you straight away.
                </div>
            </div>
            <div class="acc__item">
                <button type="button" class="acc__btn" data-acc-btn aria-expanded="false" aria-controls="faq2">
                    How do I pay?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="acc__panel" id="faq2">
                    Cash on delivery. You hand the rider the exact amount if you can
                    &mdash; it saves everyone a round trip.
                </div>
            </div>
            <div class="acc__item">
                <button type="button" class="acc__btn" data-acc-btn aria-expanded="false" aria-controls="faq3">
                    Something arrived damaged or wrong. Now what?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="acc__panel" id="faq3">
                    Message us within 7 days with your order reference and a photo. We&rsquo;ll
                    replace the item or refund it on the spot. No forms, no argument.
                </div>
            </div>
            <div class="acc__item">
                <button type="button" class="acc__btn" data-acc-btn aria-expanded="false" aria-controls="faq4">
                    Can I change or cancel my order?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="acc__panel" id="faq4">
                    Yes &mdash; as long as we haven&rsquo;t dispatched it. Call or message us with
                    your order reference before 6&nbsp;PM and we&rsquo;ll sort it out.
                </div>
            </div>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>


