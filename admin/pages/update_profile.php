<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'admins';
$page_title = 'My profile';
$page_sub   = 'Your administrator account';

$me    = current_admin() ?? ['id' => current_admin_id(), 'name' => ''];
$name  = (string) $me['name'];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['submit'])) {
    $postedName = trim((string) ($_POST['name'] ?? ''));
    $oldPass    = (string) ($_POST['old_pass'] ?? '');
    $newPass    = (string) ($_POST['new_pass'] ?? '');
    $cPass      = (string) ($_POST['confirm_pass'] ?? '');

    if ($postedName === '') {
        $errors[] = 'Your username cannot be empty.';
    } elseif (mb_strlen($postedName) > 60) {
        $errors[] = 'That username is too long (60 characters maximum).';
    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $postedName)) {
        $errors[] = 'Use letters, numbers, dots, dashes and underscores only.';
    } elseif ($postedName !== $me['name'] && $db->value('SELECT 1 FROM admin WHERE name = ?', [$postedName])) {
        $errors[] = 'That username is already taken.';
    }

    $wantsNewPassword = $newPass !== '' || $cPass !== '';

    if ($wantsNewPassword) {
        if ($oldPass === '') {
            $errors[] = 'Enter your current password to set a new one.';
        } elseif (!verify_password($oldPass, (string) ($db->value('SELECT password FROM admin WHERE id = ?', [(int) $me['id']]) ?? ''))) {
            $errors[] = 'Your current password is not correct.';
        } elseif (strlen($newPass) < 6) {
            $errors[] = 'The new password needs to be at least 6 characters.';
        } elseif ($newPass !== $cPass) {
            $errors[] = 'The two new passwords do not match.';
        }
    }

    if (!$errors) {
        $db->run('UPDATE admin SET name = ? WHERE id = ?', [$postedName, (int) $me['id']]);
        $_SESSION['admin_name'] = $postedName;

        if ($wantsNewPassword) {
            $db->run('UPDATE admin SET password = ? WHERE id = ?', [hash_password($newPass), (int) $me['id']]);
            flash('success', 'Your profile and password were updated.');
        } else {
            flash('success', 'Your username was updated.');
        }

        redirect('update_profile.php');
    }

    $name = $postedName;
}

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>My profile</h1>
        <p>Signed in as <strong><?= e($me['name']) ?></strong>.</p>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card__head"><h2 class="card__title">Account details</h2></div>
        <form class="card__body" method="post" action="update_profile.php" data-validate novalidate>
            <?php foreach ($errors as $err): ?>
                <div class="notice notice--error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div><strong>Nothing was saved</strong><p><?= e($err) ?></p></div>
                </div>
            <?php endforeach; ?>

            <div class="field">
                <label class="field__label" for="name">Username</label>
                <input class="input" type="text" id="name" name="name" value="<?= e($name) ?>" required
                       maxlength="60" autocomplete="username">
            </div>

            <hr style="border:0;border-top:1px solid var(--border);margin:var(--sp-5) 0">

            <p class="field__label" style="margin-bottom:var(--sp-3)">Change password</p>
            <p class="field__hint" style="margin-top:0;margin-bottom:var(--sp-4)">
                Leave these blank to keep your current password.
            </p>

            <div class="field">
                <label class="field__label" for="old_pass">Current password</label>
                <div class="passfield">
                    <input class="input" type="password" id="old_pass" name="old_pass" autocomplete="current-password">
                    <button class="passfield__toggle" type="button" data-toggle-password="old_pass" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="new_pass">New password</label>
                <div class="passfield">
                    <input class="input" type="password" id="new_pass" name="new_pass" minlength="6" autocomplete="new-password">
                    <button class="passfield__toggle" type="button" data-toggle-password="new_pass" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="confirm_pass">Confirm new password</label>
                <div class="passfield">
                    <input class="input" type="password" id="confirm_pass" name="confirm_pass" minlength="6" autocomplete="new-password">
                    <button class="passfield__toggle" type="button" data-toggle-password="confirm_pass" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button class="btn btn--lg" type="submit" name="submit" value="1">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save changes
            </button>
        </form>
    </section>

    <div>
        <section class="card">
            <div class="card__head"><h2 class="card__title">Session</h2></div>
            <div class="card__body">
                <p class="field__hint" style="margin-top:0">
                    Signing out destroys the session completely on this device.
                </p>
                <a class="btn btn--danger btn--block" href="logout.php">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign out
                </a>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2 class="card__title">Account</h2></div>
            <div class="card__body">
                <table class="table">
                    <tbody>
                        <tr><td>Administrator ID</td><td class="num"><?= (int) $me['id'] ?></td></tr>
                        <tr><td>Store</td><td class="num"><?= e($config['store']['legal']) ?></td></tr>
                        <tr>
                            <td>Customer accounts</td>
                            <td class="num"><?= (int) ($db->value('SELECT COUNT(*) FROM users') ?? 0) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
