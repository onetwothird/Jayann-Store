<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();
require_login();

$orderId = (int) ($_GET['order'] ?? 0);
$order = $orderId > 0
    ? $db->one('SELECT id, order_ref FROM orders WHERE id = ? AND user_id = ?', [$orderId, current_user_id()])
    : null;

if ($order) {
    redirect('receipt.php?order=' . (int) $order['id']);
}

redirect('orders.php');

