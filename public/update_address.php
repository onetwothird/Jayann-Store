<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login('update_address.php');

$user = current_user();
$address = trim((string) $user['address']);
$error  = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $line1 = trim((string) ($_POST['line1'] ?? ''));
    $line2 = trim((string) ($_POST['line2'] ?? ''));
    $city  = trim((string) ($_POST['city'] ?? ''));
    $zip   = trim((string) ($_POST['zip'] ?? ''));

    if ($line1 === '') {
        $error = 'Please enter your house number and street.';
    } elseif ($city === '') {
        $error = 'Please enter your city or municipality.';
    } else {

        $address = implode("\n", [
            $line1,
            $line2,
            $city . ($zip !== '' ? ' ' . $zip : ''),
        ]);

        $db->run('UPDATE users SET address = ? WHERE id = ?', [$address, current_user_id()]);
        flash('success', 'Your delivery address has been saved.');
        redirect('profile.php');
    }
}

[$fLine1, $fLine2, $fCity, $fZip] = array_pad(
    preg_split('/\r\n|\r|\n/', $address) ?: [''],
    4,
    ''
);
if (count(preg_split('/\r\n|\r|\n/', $address) ?: []) === 1) {

    $bits = array_map('trim', explode(',', $address));
    $fLine1 = $bits[0] ?? '';
    $fLine2 = implode(', ', array_slice($bits, 1, max(0, count($bits) - 2)));
    $fCity  = count($bits) > 1 ? (string) end($bits) : '';
    if (preg_match('/(\d{4})\s*$/', $fCity, $m)) {
        $fZip  = $m[1];
        $fCity = trim(preg_replace('/\d{4}\s*$/', '', $fCity));
    }
}

$pageTitle = 'Delivery address';
$pageDesc  = 'Set the address we deliver your orders to.';
$pageClass = 'page-auth';

require '../app/views/layout/head.php';
?>

<section class="auth">
    <div class="auth__card panel">
        <div class="panel__body">
            <div class="auth__head">
                <span class="auth__icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                <h1>Delivery address</h1>
                <p>Saved once, prefilled at every checkout.</p>
            </div>

            <?php if ($error !== null): ?>
                <div class="notice notice--error">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div><strong>Please fix the following</strong><ul class="notice__list"><li><?= e($error) ?></li></ul></div>
                </div>
            <?php endif; ?>

            <form method="post" action="update_address.php" novalidate>
                <div class="field">
                    <label class="field__label" for="line1">House no. &amp; street</label>
                    <input class="input" type="text" id="line1" name="line1" required maxlength="200"
                           autocomplete="address-line1" placeholder="123 Mabini Street"
                           value="<?= e($fLine1) ?>">
                </div>

                <div class="field">
                    <label class="field__label" for="line2">
                        Barangay / landmark <span class="field__opt">optional</span>
                    </label>
                    <input class="input" type="text" id="line2" name="line2" maxlength="200"
                           autocomplete="address-line2" placeholder="Near the covered court"
                           value="<?= e($fLine2) ?>">
                </div>

                <div class="field">
                    <label class="field__label" for="city">City / municipality</label>
                    <input class="input" type="text" id="city" name="city" required maxlength="80"
                           autocomplete="address-level2" placeholder="Naic"
                           value="<?= e($fCity) ?>">
                </div>

                <div class="field">
                    <label class="field__label" for="zip">
                        ZIP code <span class="field__opt">optional</span>
                    </label>
                    <input class="input" type="text" id="zip" name="zip" maxlength="4" inputmode="numeric"
                           autocomplete="postal-code" placeholder="1105" value="<?= e($fZip) ?>">
                </div>

                <div class="auth__cta">
                    <button type="submit" class="btn btn--lg">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save address
                    </button>
                    <a class="btn btn--ghost btn--lg" href="profile.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require '../app/views/layout/footer.php'; ?>

