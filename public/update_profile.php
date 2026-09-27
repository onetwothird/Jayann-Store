<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login('update_profile.php');

$user = current_user();

$errors = [];
$old = [
    'name'   => (string) $user['name'],
    'email'  => (string) $user['email'],
    'number' => (string) $user['number'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old['name']   = trim((string) ($_POST['name'] ?? ''));
    $old['email']  = trim((string) ($_POST['email'] ?? ''));
    $old['number'] = trim((string) ($_POST['number'] ?? ''));

    if (mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Please enter your full name.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $taken = $db->value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$old['email'], current_user_id()]);
        if ($taken) {
            $errors['email'] = 'Another account already uses that email address.';
        }
    }
    if (!preg_match('/^[0-9+\s\-()]{7,20}$/', $old['number'])) {
        $errors['number'] = 'Enter a valid mobile number (e.g. 09XX XXX XXXX).';
    }

    if (!$errors) {
        $db->run('UPDATE users SET name = ?, email = ?, number = ? WHERE id = ?',
            [$old['name'], $old['email'], $old['number'], current_user_id()]);
        flash('success', 'Your details have been updated.');
        redirect('profile.php');
    }
}

$pageTitle = 'Edit profile';
$pageDesc  = 'Update your name, email and mobile number.';
$pageClass = 'page-auth';

require '../app/views/layout/head.php';
?>

<section class="auth">
    <div class="auth__card panel">
        <div class="panel__body">
            <div class="auth__head">
                <span class="auth__icon"><i class="fa-solid fa-user-pen" aria-hidden="true"></i></span>
                <h1>Edit your details</h1>
                <p>These details appear on your orders and receipts.</p>
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

            <form method="post" action="update_profile.php" novalidate>
                <div class="field">
                    <label class="field__label" for="name">Full name</label>
                    <input class="input" type="text" id="name" name="name" required maxlength="30"
                           autocomplete="name" value="<?= e($old['name']) ?>"
                           <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['name'])): ?><p class="field__error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" required maxlength="120"
                           autocomplete="email" value="<?= e($old['email']) ?>"
                           <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['email'])): ?><p class="field__error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="number">Mobile number</label>
                    <input class="input" type="tel" id="number" name="number" required maxlength="20"
                           autocomplete="tel" placeholder="09XX XXX XXXX" value="<?= e($old['number']) ?>"
                           <?= isset($errors['number']) ? 'aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['number'])): ?><p class="field__error"><?= e($errors['number']) ?></p><?php endif; ?>
                </div>

                <div class="auth__cta">
                    <button type="submit" class="btn btn--lg">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save changes
                    </button>
                    <a class="btn btn--ghost btn--lg" href="profile.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>

