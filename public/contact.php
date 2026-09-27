<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

$store  = $config['store'];
$errors = [];
$old    = ['name' => '', 'email' => '', 'number' => '', 'message' => ''];
$sent   = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($old) as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if (mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Please tell us your name.';
    }
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address doesn&rsquo;t look right.';
    }
    if ($old['number'] !== '' && !preg_match('/^[0-9+\s\-()]{7,20}$/', $old['number'])) {
        $errors['number'] = 'Enter a valid contact number.';
    }
    if (mb_strlen($old['message']) < 10) {
        $errors['message'] = 'Please write at least a sentence so we can help.';
    } elseif (mb_strlen($old['message']) > 500) {
        $errors['message'] = 'Please keep your message under 500 characters.';
    }

    if (!$errors) {
        $db->insert(
            'INSERT INTO messages (user_id, name, email, number, message) VALUES (?,?,?,?,?)',
            [
                current_user_id(),
                $old['name'],
                $old['email'],
                $old['number'],
                $old['message'],
            ]
        );
        $sent = true;
        $old  = ['name' => '', 'email' => '', 'number' => '', 'message' => ''];
    }
}

$pageTitle = 'Contact us';
$pageDesc  = 'Message Jayann\'s Store about an order, a product or delivery.';
$pageClass = 'page-contact';

require '../app/views/layout/head.php';
?>

<div class="container">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="home.php">Home</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Contact</span>
    </nav>
</div>

<section class="section section--flush-top">
    <div class="container">

        <div class="sechead">
            <span class="eyebrow"><i class="fa-solid fa-headset" aria-hidden="true"></i> Get in touch</span>
            <h1 class="sechead__title">We&rsquo;re happy to help</h1>
            <p class="sechead__sub">
                Questions about an order, a product or delivery? Send us a message and we&rsquo;ll
                reply within the day.
            </p>
        </div>

        <div class="contact">

            <div class="contact__cards">
                <div class="contact__card">
                    <span class="info-row__icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                    <div class="info-row__body">
                        <span class="info-row__label">Visit the store</span>
                        <span class="info-row__value"><?= e($store['address']) ?></span>
                    </div>
                </div>
                <div class="contact__card">
                    <span class="info-row__icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
                    <div class="info-row__body">
                        <span class="info-row__label">Call or text</span>
                        <span class="info-row__value">
                            <a href="tel:<?= e($store['phone_raw']) ?>"><?= e($store['phone']) ?></a>
                        </span>
                        <small><?= e($store['hours']) ?></small>
                    </div>
                </div>
                <div class="contact__card">
                    <span class="info-row__icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <div class="info-row__body">
                        <span class="info-row__label">Email</span>
                        <span class="info-row__value">
                            <a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a>
                        </span>
                    </div>
                </div>
                <div class="contact__card">
                    <span class="info-row__icon"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></span>
                    <div class="info-row__body">
                        <span class="info-row__label">Messenger</span>
                        <span class="info-row__value">
                            <a href="<?= e($store['facebook']) ?>" target="_blank" rel="noopener noreferrer">
                                Send us a message
                            </a>
                        </span>
                        <small>Fastest way to reach us</small>
                    </div>
                </div>

                <div class="contact__media">
                    <img src="/Jayann_Store/assets/img/contact-img.svg" alt="Chat with the store">
                </div>
            </div>

            <div class="panel">
                <div class="panel__head">
                    <h2 class="panel__title"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send a message</h2>
                </div>
                <div class="panel__body">
                    <?php if ($sent): ?>
                        <div class="notice notice--success">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <div>
                                <strong>Message sent</strong>
                                <p>Thanks for reaching out &mdash; we&rsquo;ll get back to you shortly.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($errors): ?>
                        <div class="notice notice--error">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            <div>
                                <strong>Please fix the following</strong>
                                <ul class="notice__list">
                                    <?php foreach ($errors as $err): ?><li><?= $err ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="contact.php" novalidate>
                        <div class="addrgrid">
                            <div class="field">
                                <label class="field__label" for="cName">Your name</label>
                                <input class="input" type="text" id="cName" name="name" required maxlength="100"
                                       autocomplete="name" value="<?= e($old['name']) ?>"
                                       <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
                                <?php if (isset($errors['name'])): ?><p class="field__error"><?= e($errors['name']) ?></p><?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="field__label" for="cNumber">
                                    Number <span class="field__opt">optional</span>
                                </label>
                                <input class="input" type="tel" id="cNumber" name="number" maxlength="20"
                                       autocomplete="tel" value="<?= e($old['number']) ?>"
                                       <?= isset($errors['number']) ? 'aria-invalid="true"' : '' ?>>
                                <?php if (isset($errors['number'])): ?><p class="field__error"><?= e($errors['number']) ?></p><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label class="field__label" for="cEmail">
                                    Email <span class="field__opt">optional</span>
                                </label>
                                <input class="input" type="email" id="cEmail" name="email" maxlength="120"
                                       autocomplete="email" value="<?= e($old['email']) ?>"
                                       <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                                <?php if (isset($errors['email'])): ?><p class="field__error"><?= $errors['email'] ?></p><?php endif; ?>
                            </div>
                            <div class="field field--full">
                                <label class="field__label" for="cMessage">Message</label>
                                <textarea class="textarea" id="cMessage" name="message" required maxlength="500"
                                          rows="5" placeholder="Tell us what you need&hellip;"
                                          <?= isset($errors['message']) ? 'aria-invalid="true"' : '' ?>><?= e($old['message']) ?></textarea>
                                <?php if (isset($errors['message'])): ?>
                                    <p class="field__error"><?= $errors['message'] ?></p>
                                <?php else: ?>
                                    <p class="field__hint">Up to 500 characters. Include your order reference if it&rsquo;s about a delivery.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn--lg btn--block">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>


