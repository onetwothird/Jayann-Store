<?php declare(strict_types=1);

$store = $config['store'];
?>
</main>

<footer class="footer">
    <section class="trust" aria-label="Why shop with us">
        <div class="container trust__grid">
            <div class="trust__item">
                <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                <div><strong>Fast delivery</strong><span>Metro Manila in 1â€“2 days</span></div>
            </div>
            <div class="trust__item">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div><strong>Secure checkout</strong><span>Your details stay private</span></div>
            </div>
            <div class="trust__item">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                <div><strong>Easy returns</strong><span>7-day replacement policy</span></div>
            </div>
            <div class="trust__item">
                <i class="fa-solid fa-headset" aria-hidden="true"></i>
                <div><strong>Support <?= e($store['phone']) ?></strong><span><?= e($store['hours']) ?></span></div>
            </div>
        </div>
    </section>

    <div class="container footer__grid">
        <div class="footer__brand">
            <a href="/Jayann_Store/public/home.php" class="brand brand--footer">
                <img src="/Jayann_Store/assets/img/storenijayann.png" alt="" class="brand__logo" width="52" height="52">
                <span class="brand__text"><strong><?= e($store['name']) ?></strong></span>
            </a>
            <p><?= e($store['tagline']) ?>. Supporting a neighbourhood business in <?= e($store['address']) ?>.</p>
            <ul class="footer__contact">
                <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= e($store['address']) ?></li>
                <li><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Delivers to <?= e($store['service_area']) ?></li>
                <li><i class="fa-solid fa-clock" aria-hidden="true"></i> <?= e($store['hours']) ?></li>
                <li><i class="fa-solid fa-phone" aria-hidden="true"></i>
                    <a href="tel:<?= e($store['phone_raw']) ?>"><?= e($store['phone']) ?></a></li>
                <li><i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a></li>
            </ul>
        </div>

        <nav class="footer__col" aria-labelledby="ftShop">
            <h3 id="ftShop">Shop</h3>
            <ul>
                <li><a href="/Jayann_Store/public/products.php">All products</a></li>
                <li><a href="/Jayann_Store/public/discounted_products.php">On sale</a></li>
                <?php foreach (array_slice(product_categories(), 0, 4) as $catRow): ?>
                    <li><a href="/Jayann_Store/public/category.php?category=<?= urlencode($catRow['category']) ?>"><?= e($catRow['category']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <nav class="footer__col" aria-labelledby="ftAccount">
            <h3 id="ftAccount">My account</h3>
            <ul>
                <li><a href="/Jayann_Store/public/login.php">Log in</a></li>
                <li><a href="/Jayann_Store/public/register.php">Create account</a></li>
                <li><a href="/Jayann_Store/public/profile.php">My profile</a></li>
                <li><a href="/Jayann_Store/public/orders.php">My orders</a></li>
                <li><a href="/Jayann_Store/public/cart.php">My cart</a></li>
            </ul>
        </nav>

        <nav class="footer__col" aria-labelledby="ftHelp">
            <h3 id="ftHelp">Customer care</h3>
            <ul>
                <li><a href="/Jayann_Store/public/contact.php">Contact us</a></li>
                <li><a href="/Jayann_Store/public/about.php">About the store</a></li>
                <li><a href="contact.php#faq">Shipping &amp; delivery</a></li>
                <li><a href="contact.php#faq">Returns &amp; refunds</a></li>
                <li><a href="contact.php#faq">Track my order</a></li>
                <li><a href="/Jayann_Store/admin/pages/admin_login.php">Staff portal</a></li>
            </ul>
        </nav>
    </div>

    <div class="footer__bar">
        <div class="container footer__bar-inner">
            <p>&copy; <?= date('Y') ?> <?= e($store['legal']) ?>. All rights reserved.</p>

            <?php

            $footerMethods = available_payment_methods();
            $freeOver      = (float) config_value('order', 'free_shipping_over', 0);
            ?>
            <div class="footer__pay">
                <span class="footer__pay-label">
                    We accept<?= $freeOver > 0
                        ? ' â€” free delivery over ' . e($store['currency'] . number_format($freeOver))
                        : '' ?>
                </span>
                <ul class="footer__pay-list">
                    <?php foreach ($footerMethods as $m): ?>
                        <li title="<?= e($m['label']) ?>">
                            <i class="fa-solid <?= e($m['icon']) ?>" aria-hidden="true"></i>
                            <span class="sr-only"><?= e($m['label']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php

            $channels = array_filter([
                'facebook'  => ['fa-facebook-f',  $store['facebook']  ?? ''],
                'instagram' => ['fa-instagram',   $store['instagram'] ?? ''],
                'x'         => ['fa-x-twitter',   $store['x']         ?? ''],
            ], static fn(array $c): bool => trim((string) $c[1]) !== '');
            ?>
            <?php if ($channels !== []): ?>
                <ul class="social">
                    <?php foreach ($channels as $key => [$icon, $url]): ?>
                        <li>
                            <a href="<?= e($url) ?>" rel="noopener noreferrer" target="_blank"
                               aria-label="<?= e(ucfirst($key)) ?>">
                                <i class="fa-brands <?= e($icon) ?>" aria-hidden="true"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li>
                        <a href="mailto:<?= e($store['email']) ?>" aria-label="Email us">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</footer>

<button type="button" class="to-top" id="toTop" aria-label="Back to top">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
<?php if (!empty($scripts)): foreach ((array) $scripts as $extra): ?>
<script src="<?= e($extra) ?>" defer></script>
<?php endforeach; endif; ?>
<script src="assets/js/script.js?v=2.0" defer></script>
</body>
</html>

