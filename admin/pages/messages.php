<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'messages';
$page_title = 'Messages';
$page_sub   = 'Submissions from the storefront contact form';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (($_POST['form_action'] ?? '') === 'delete') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $id > 0 ? $db->one('SELECT * FROM messages WHERE id = ?', [$id]) : null;
        if ($row) {
            $db->run('DELETE FROM messages WHERE id = ?', [$id]);
            flash('success', 'The message from ' . $row['name'] . ' was deleted.');
        }
    }
    redirect('messages.php');
}

$q        = admin_q('q');
$perPage  = 20;
$whereSql = '';
$params   = [];

if ($q !== '') {
    $whereSql = 'WHERE name LIKE ? OR email LIKE ? OR message LIKE ?';
    $params   = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
}

$total  = (int) ($db->value("SELECT COUNT(*) FROM messages $whereSql", $params) ?? 0);
$page   = admin_page_no($total, $perPage);
$offset = ($page - 1) * $perPage;

$messages = $db->all(
    "SELECT * FROM messages $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset",
    $params
);

require __DIR__ . '/../../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Messages</h1>
        <p><?= e(plural($total, 'message')) ?> from the contact form.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="../contact.php" target="_blank" rel="noopener">
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View the form
        </a>
    </div>
</div>

<form class="filters" method="get" action="messages.php">
    <div class="searchinline">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input" type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search messages" aria-label="Search messages" data-search>
    </div>
    <div class="filters__count"><strong><?= e(number_format($total)) ?></strong> shown</div>
</form>

<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$messages): ?>
            <?= admin_empty(
                'fa-envelope-open',
                $q !== '' ? 'Nothing matches' : 'No messages yet',
                $q !== ''
                    ? 'Try a different search term.'
                    : 'Submissions from the storefront contact form land here.'
            ) ?>
        <?php else: ?>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">From</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Message</th>
                        <th scope="col" class="num">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td>
                            <strong><?= e($m['name']) ?></strong>
                            <small style="display:block;color:var(--text-muted)">
                        </td>
                        <td style="white-space:nowrap">
                            <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                            <small style="display:block;color:var(--text-soft)"><?= e($m['number']) ?></small>
                        </td>
                        <td style="max-width:28rem;white-space:pre-wrap"><?= e($m['message']) ?></td>
                        <td class="num">
                            <div class="rowactions" style="justify-content:flex-end">
                                <a class="btn btn--sm btn--ghost"
                                   href="mailto:<?= e($m['email']) ?>?subject=Re:%20your%20message%20to%20<?= e(rawurlencode($config['store']['legal'])) ?>">
                                    <i class="fa-solid fa-reply" aria-hidden="true"></i>
                                </a>
                                <form method="post" action="messages.php"
                                      data-confirm="Delete the message from <?= e($m['name']) ?>?">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <button class="btn btn--sm btn--danger" type="submit">
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
    <?= admin_pager($page, $total, $perPage) ?>
</section>

<?php require __DIR__ . '/../../app/views/admin/footer.php'; ?>
