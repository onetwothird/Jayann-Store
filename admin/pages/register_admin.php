<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'admins';
$page_title = 'Add administrator';
$page_sub   = 'Create another login for this panel';

$errors = [];
$name   = '';
$pass   = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['submit'])) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    $cpass = (string) ($_POST['cpass'] ?? '');

    if ($name === '') {
        $errors[] = 'Choose a username.';
    } elseif (mb_strlen($name) > 60) {
        $errors[] = 'That username is too long (60 characters maximum).';
    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
        $errors[] = 'Use letters, numbers, dots, dashes and underscores only.';
    } elseif ($db->value('SELECT 1 FROM admin WHERE name = ?', [$name])) {
        $errors[] = 'That username is already taken.';
    }

    if (strlen($pass) < 6) {
        $errors[] = 'The password needs to be at least 6 characters.';
    }
    if ($pass !== $cpass) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $db->run('INSERT INTO admin (name, password) VALUES (?, ?)', [$name, hash_password($pass)]);
        flash('success', $name . ' can now sign in to the admin panel.');
        redirect('admin_accounts.php');
    }
}

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">
            <a href="admin_accounts.php" style="color:inherit">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Administrators
            </a>
        </p>
        <h1>Add administrator</h1>
        <p>Share the panel only with people you trust.</p>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card__head"><h2 class="card__title">Account details</h2></div>
        <form class="card__body" method="post" action="register_admin.php" data-validate novalidate>
            <?php foreach ($errors as $err): ?>
                <div class="notice notice--error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div><strong>Check the form</strong><p><?= e($err) ?></p></div>
                </div>
            <?php endforeach; ?>

            <div class="field">
                <label class="field__label" for="name">Username</label>
                <input class="input" type="text" id="name" name="name" value="<?= e($name) ?>" required
                       maxlength="60" autocomplete="off" placeholder="e.g. jayann">
                <p class="field__hint">Letters, numbers, dots, dashes and underscores.</p>
            </div>

            <div class="field">
                <label class="field__label" for="pass">Password</label>
                <div class="passfield">
                    <input class="input" type="password" id="pass" name="pass" required minlength="6"
                           autocomplete="new-password" placeholder="At least 6 characters">
                    <button class="passfield__toggle" type="button" data-toggle-password="pass" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="cpass">Confirm password</label>
                <div class="passfield">
                    <input class="input" type="password" id="cpass" name="cpass" required minlength="6"
                           autocomplete="new-password" placeholder="Type it again">
                    <button class="passfield__toggle" type="button" data-toggle-password="cpass" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button class="btn btn--lg" type="submit" name="submit" value="1">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create account
            </button>
        </form>
    </section>

    <section class="card">
        <div class="card__head"><h2 class="card__title">Security notes</h2></div>
        <div class="card__body">
            <div class="notice notice--warn" style="margin-bottom:var(--sp-3)">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>This panel is not customer-facing</strong>
                    <p>Anyone who signs in can change prices, stock and order statuses. Give out accounts sparingly.</p>
                </div>
            </div>
            <p class="field__hint">
                Passwords are stored as bcrypt hashes, never in plain text, and are compared in constant time.
            </p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
