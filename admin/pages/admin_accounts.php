<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'admins';
$page_title = 'Administrators';
$page_sub   = 'Who can sign in to this panel';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['form_action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $row = $id > 0 ? $db->one('SELECT * FROM admin WHERE id = ?', [$id]) : null;

    if (!$row) {
        flash('error', 'That account no longer exists.');
    } elseif ($id === current_admin_id()) {
        flash('error', 'You cannot delete the account you are signed in with.');
    } elseif ((int) ($db->value('SELECT COUNT(*) FROM admin') ?? 0) <= 1) {
        flash('error', 'This is the only administrator account, so it cannot be deleted.');
    } else {
        $db->run('DELETE FROM admin WHERE id = ?', [$id]);
        flash('success', $row['name'] . "'s administrator access was removed.");
    }

    redirect('admin_accounts.php');
}

$admins = $db->all('SELECT id, name FROM admin ORDER BY id');

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Administrators</h1>
        <p><?= e(plural(count($admins), 'account')) ?> with access to this panel.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn" href="register_admin.php">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add administrator
        </a>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card__head"><h2 class="card__title">Team</h2></div>
        <div class="card__body card__body--flush">
            <?php if (!$admins): ?>
                <?= admin_empty('fa-user-slash', 'No administrators', 'Add an account so someone can manage the store.') ?>
            <?php else: ?>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Administrator</th>
                            <th scope="col">Access</th>
                            <th scope="col" class="num">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $a): ?>
                        <?php $isMe = (int) $a['id'] === current_admin_id(); ?>
                        <tr>
                            <td>
                                <span class="cellproduct">
                                    <span class="sidebar__avatar" aria-hidden="true"><?= e(strtoupper(substr((string) $a['name'], 0, 1))) ?></span>
                                    <span>
                                        <strong><?= e($a['name']) ?></strong>
                                        <small>ID <?= (int) $a['id'] ?></small>
                                    </span>
                                </span>
                            </td>
                            <td><?= $isMe ? '<span class="tag tag--info">You</span>' : '<span class="tag">Administrator</span>' ?></td>
                            <td class="num">
                                <div class="rowactions" style="justify-content:flex-end">
                                    <form method="post" action="admin_accounts.php"
                                          data-confirm="Remove <?= e($a['name']) ?>'s access to the admin panel?">
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <button class="btn btn--sm btn--danger" type="submit"
                                                <?= $isMe || count($admins) <= 1 ? 'disabled aria-disabled="true"' : '' ?>
                                                title="<?= $isMe ? 'You cannot delete your own account' : 'Remove access' ?>">
                                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><h2 class="card__title">Good to know</h2></div>
        <div class="card__body">
            <div class="notice notice--info" style="margin-bottom:0">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div>
                    <strong>Passwords</strong>
                    <p>New accounts use bcrypt. Older sha1 hashes still sign in and are upgraded automatically on the next successful login.</p>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
