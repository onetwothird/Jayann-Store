<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

boot_session();

if (is_admin_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$name   = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');

    if ($name === '' || $pass === '') {
        $errors[] = 'Please enter both your username and password.';
    } else {
        $row = $db->one('SELECT * FROM admin WHERE name = ?', [$name]);

        if ($row && verify_and_rehash('admin', (int) $row['id'], $pass)) {

            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $row['id'];
            $_SESSION['admin_name'] = (string) $row['name'];
            flash('success', 'Welcome back, ' . $row['name'] . '.');
            redirect('dashboard.php');
        }

        $errors[] = 'That username and password combination is not recognised.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin sign in &middot; <?= e($config['store']['legal']) ?></title>
<link rel="icon" type="image/png" href="../assets/img/storenijayann.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin_style.css?v=2.0">
</head>
<body>

<div class="auth">
    <div class="auth__card">
        <div class="auth__head">
            <img src="../assets/img/storenijayann.png" alt="" width="56" height="56">
            <h1>Admin sign in</h1>
            <p><?= e($config['store']['legal']) ?> &middot; management console</p>
        </div>

        <?php if ($errors): ?>
            <div class="notice notice--error" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>We couldn't sign you in</strong>
                    <?php foreach ($errors as $err): ?>
                        <p><?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="admin_login.php" data-validate novalidate>
            <div class="field">
                <label class="field__label" for="name">Username</label>
                <input class="input" type="text" id="name" name="name" value="<?= e($name) ?>"
                       autocomplete="username" required autofocus placeholder="admin">
            </div>

            <div class="field">
                <label class="field__label" for="pass">Password</label>
                <div class="passfield">
                    <input class="input" type="password" id="pass" name="pass"
                           autocomplete="current-password" required placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                    <button class="passfield__toggle" type="button" data-toggle-password="pass" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button class="btn btn--lg btn--block" type="submit">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in
            </button>
        </form>

        <p class="auth__back">
            <a href="../index.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to the store
            </a>
        </p>
    </div>
</div>

</body>
</html>
