<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/views/admin/helpers.php';

require_admin();
boot_session();

$admin_page = 'messages';
$page_title = 'Customer messages';
$page_sub   = 'Contact form submissions from the storefront';

$statusFilter = admin_q('status');
$search       = admin_q('q');

$where  = [];
$params = [];

if ($statusFilter === 'unread' || $statusFilter === 'read') {
    $where[]  = 'm.is_read = ?';
    $params[] = $statusFilter === 'unread' ? 0 : 1;
}

if ($search !== '') {
    $where[]  = '(m.name LIKE ? OR m.email LIKE ? OR m.message LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = (int) ($db->value("SELECT COUNT(*) FROM messages m $whereSql", $params) ?? 0);
$perPage = 20;
$page    = admin_page_no($total, $perPage);
$offset  = ($page - 1) * $perPage;

$messages = $db->all(
    "SELECT m.* FROM messages m $whereSql ORDER BY m.id DESC LIMIT $perPage OFFSET $offset",
    $params
);

$unreadCount = (int) ($db->value('SELECT COUNT(*) FROM messages WHERE is_read = 0') ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['form_action'])) {
    $action = (string) $_POST['form_action'];
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle_read') {
        $msg = $db->one('SELECT is_read FROM messages WHERE id = ?', [$id]);
        if ($msg) {
            $newStatus = (int) $msg['is_read'] === 1 ? 0 : 1;
            $db->run('UPDATE messages SET is_read = ? WHERE id = ?', [$newStatus, $id]);
            flash('success', $newStatus ? 'Message marked as read.' : 'Message marked as unread.');
        }
        redirect('contact.php' . ($statusFilter ? '?status=' . urlencode($statusFilter) : ''));
    } elseif ($action === 'delete') {
        $db->run('DELETE FROM messages WHERE id = ?', [$id]);
        flash('success', 'Message deleted.');
        redirect('contact.php' . ($statusFilter ? '?status=' . urlencode($statusFilter) : ''));
    }
}

require __DIR__ . '/../app/views/admin/head.php';
?>

<div class="page-head">
    <div>
        <h1>Customer messages</h1>
        <p>
            <?= e(plural($total, 'message')) ?> from the contact form
            <?= $statusFilter ? ' — filtered: ' . ($statusFilter === 'unread' ? 'unread' : 'read') : '' ?>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="contact.php">
            <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Clear filters
        </a>
    </div>
</div>

<!-- Filters -->
<form class="filters" method="get" action="contact.php">
    <div class="searchinline">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input" type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search name, email or message" aria-label="Search messages" data-search>
    </div>

    <a class="chip<?= $statusFilter === '' ? ' is-active' : '' ?>" href="contact.php">All</a>
    <a class="chip<?= $statusFilter === 'unread' ? ' is-active' : '' ?>" href="contact.php?status=unread">
        Unread
        <?php if ($unreadCount > 0): ?><span class="sidebar__count"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <a class="chip<?= $statusFilter === 'read' ? ' is-active' : '' ?>" href="contact.php?status=read">Read</a>

    <div class="filters__count">
        <strong><?= e(number_format($total)) ?></strong> shown
    </div>
</form>

<!-- Listing -->
<section class="card">
    <div class="card__body card__body--flush">
        <?php if (!$messages): ?>
            <?= admin_empty(
                'fa-envelope',
                'No messages',
                $search !== '' || $statusFilter !== ''
                    ? 'No message matches the current filter. Try clearing it.'
                    : 'Customer contact form submissions will appear here.',
                '<a class="btn btn--ghost btn--sm" href="contact.php">Clear filters</a>'
            ) ?>
        <?php else: ?>
        <div class="tablewrap messages-table">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">From</th>
                        <th scope="col">Message</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Received</th>
                        <th scope="col" class="num">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr<?= (int) $m['is_read'] === 0 ? ' style="background:var(--info-50);"' : '' ?>>
                        <td data-label="From">
                            <strong><?= e($m['name']) ?></strong>
                            <small style="display:block;color:var(--text-muted)"><?= e($m['email']) ?></small>
                            <?php if ($m['number'] !== ''): ?>
                                <small style="display:block;color:var(--text-soft)"><?= e($m['number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Message" style="max-width: 30rem;">
                            <span style="display:block;color:var(--text-muted);font-size:var(--fs-sm)">
                                <?= e(mb_strimwidth((string) $m['message'], 0, 120, '…')) ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <span class="status status--<?= (int) $m['is_read'] === 1 ? 'success' : 'info' ?>">
                                <i class="fa-solid <?= (int) $m['is_read'] === 1 ? 'fa-envelope-open' : 'fa-envelope' ?>" aria-hidden="true"></i>
                                <?= (int) $m['is_read'] === 1 ? 'Read' : 'Unread' ?>
                            </span>
                        </td>
                        <td class="num" data-label="Received"><?= e(nice_date((string) $m['id'], true)) ?></td>
                        <td class="num" data-label="Actions">
                            <div class="rowactions" style="justify-content:flex-end; gap: var(--sp-1);">
                                <form method="post" action="contact.php" style="display:inline">
                                    <input type="hidden" name="form_action" value="toggle_read">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                                    <button class="btn btn--sm btn--ghost" type="submit" title="<?= (int) $m['is_read'] === 1 ? 'Mark unread' : 'Mark read' ?>">
                                        <i class="fa-solid <?= (int) $m['is_read'] === 1 ? 'fa-envelope' : 'fa-envelope-open' ?>" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <form method="post" action="contact.php"
                                      data-confirm="Delete this message permanently?"
                                      style="display:inline">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                                    <button class="btn btn--sm btn--danger" type="submit" title="Delete">
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

<?php require __DIR__ . '/../app/views/admin/footer.php'; ?>