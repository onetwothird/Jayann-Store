<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

if (is_logged_in()) {
    redirect('home.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'number' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old['name']   = trim((string) ($_POST['name'] ?? ''));
    $old['email']  = trim((string) ($_POST['email'] ?? ''));
    $old['number'] = trim((string) ($_POST['number'] ?? ''));
    $password      = (string) ($_POST['password'] ?? '');
    $confirm       = (string) ($_POST['confirm'] ?? '');
    $address       = trim((string) ($_POST['address'] ?? ''));

    if (mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Please enter your full name.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ($db->value('SELECT 1 FROM users WHERE email = ?', [$old['email']])) {
        $errors['email'] = 'An account with that email already exists.';
    }
    if (!preg_match('/^[0-9+\s\-()]{7,20}$/', $old['number'])) {
        $errors['number'] = 'Enter a valid mobile number (e.g. 09XX XXX XXXX).';
    }
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters for your password.';
    } elseif ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $db->insert(
            'INSERT INTO users (name, email, number, password, address) VALUES (?,?,?,?,?)',
            [$old['name'], $old['email'], $old['number'], hash_password($password), $address]
        );

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $conn->lastInsertId();

        flash('success', 'Welcome to Jayann\'s Store, ' . explode(' ', $old['name'])[0] . '!');
        redirect('home.php');
    }
}

$pageTitle = 'Create account';
$pageDesc  = 'Create a Jayann\'s Store account for faster checkout and order history.';
$pageClass = 'page-auth';

require '../app/views/layout/head.php';
?>

<section class="auth">
    <div class="auth__card panel">
        <div class="panel__body">
            <div class="auth__head">
                <img class="auth__logo" src="<?= BASE_URL ?>assets/img/storenijayann.png" alt="" width="56" height="56">
                <h1>Create your account</h1>
                <p>It takes a minute and makes checkout a lot faster.</p>
            </div>

            <?php if ($errors): ?>
                <div class="notice notice--error">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Please fix the following</strong>
                        <ul class="notice__list">
                            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php" novalidate>
                <div class="field">
                    <label class="field__label" for="name">Full name</label>
                    <input class="input" type="text" id="name" name="name" required maxlength="30"
                           autocomplete="name" placeholder="Juan Dela Cruz"
                           value="<?= e($old['name']) ?>" <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['name'])): ?><p class="field__error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" required maxlength="120"
                           autocomplete="email" placeholder="you@example.com"
                           value="<?= e($old['email']) ?>" <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['email'])): ?><p class="field__error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="number">Mobile number</label>
                    <input class="input" type="tel" id="number" name="number" required maxlength="20"
                           autocomplete="tel" placeholder="09XX XXX XXXX"
                           value="<?= e($old['number']) ?>" <?= isset($errors['number']) ? 'aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['number'])): ?><p class="field__error"><?= e($errors['number']) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="address">Delivery address <span class="field__opt">optional</span></label>
                    <textarea class="textarea" id="address" name="address" rows="2" maxlength="500"
                              autocomplete="street-address"
                              placeholder="House no. &amp; street, barangay, Naic, Cavite"></textarea>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
                    <div class="passfield">
                        <input class="input" type="password" id="password" name="password" required
                               autocomplete="new-password" placeholder="At least 8 characters"
                               <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>>
                        <button type="button" class="passfield__toggle" data-toggle-password
                                aria-label="Show password" aria-pressed="false">
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?><p class="field__error"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="confirm">Confirm password</label>
                    <input class="input" type="password" id="confirm" name="confirm" required
                           autocomplete="new-password" placeholder="Repeat your password"
                           <?= isset($errors['confirm']) ? 'aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['confirm'])): ?><p class="field__error"><?= e($errors['confirm']) ?></p><?php endif; ?>
                </div>

                <button type="submit" class="btn btn--lg btn--block">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create account
                </button>
            </form>

            <p class="auth__foot">
                Already have an account? <a href="login.php">Log in</a>
            </p>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>


