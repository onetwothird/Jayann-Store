<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

if (is_logged_in()) {
    redirect('home.php');
}

$errors  = [];
$email   = '';
$next    = (string) ($_GET['next'] ?? $_POST['next'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Enter both your email address and password.';
    } else {
        $user = $db->one('SELECT * FROM users WHERE email = ?', [$email]);

        if (!$user) {
            $errors[] = 'We could not find an account with that email address.';
        } elseif (!verify_and_rehash('users', (int) $user['id'], $password)) {
            $errors[] = 'That email and password combination is incorrect.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];

            $safeNext = (is_string($next) && str_starts_with($next, '/') === false
                         && preg_match('#^[\w\-./]+\.php(\?\S*)?$#', $next)) ? $next : 'home.php';

            flash('success', 'Welcome back, ' . explode(' ', (string) $user['name'])[0] . '!');
            redirect($safeNext);
        }
    }
}

$pageTitle = 'Log in';
$pageDesc  = 'Log in to your Jayann\'s Store account to check out and track orders.';
$pageClass = 'page-auth';

require '../app/views/layout/head.php';
?>

<section class="auth">
    <div class="auth__card panel">
        <div class="panel__body">
            <div class="auth__head">
                <img class="auth__logo" src="/Jayann_Store/assets/img/storenijayann.png" alt="" width="56" height="56">
                <h1>Welcome back</h1>
                <p>Log in to continue shopping with us.</p>
            </div>

            <?php if ($errors): ?>
                <div class="notice notice--error">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>We couldn&rsquo;t log you in</strong>
                        <ul class="notice__list">
                            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div class="field">
                    <label class="field__label" for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" required autocomplete="email"
                           maxlength="120" value="<?= e($email) ?>" placeholder="you@example.com"
                           <?= $errors ? 'aria-invalid="true"' : '' ?>>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
                    <div class="passfield">
                        <input class="input" type="password" id="password" name="password" required
                               autocomplete="current-password" placeholder="Your password">
                        <button type="button" class="passfield__toggle" data-toggle-password
                                aria-label="Show password" aria-pressed="false">
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn--lg btn--block">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Log in
                </button>
            </form>

            <div class="auth__divider">New here?</div>

            <a class="btn btn--ghost btn--block" href="register.php<?= $next !== '' ? '?next=' . urlencode($next) : '' ?>">
                Create an account
            </a>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>


