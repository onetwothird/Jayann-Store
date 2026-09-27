<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    return;
}

$action = $_POST['cart_action'] ?? null;

if ($action === 'add') {
    if (!is_logged_in()) {
        flash('info', 'Please log in to start shopping.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'home.php'));
    }

    $pid = (int) ($_POST['pid'] ?? 0);
    $qty = max(1, min((int) ($_POST['qty'] ?? 1), (int) $config['order']['max_qty_per_item']));

    $product = $db->one('SELECT * FROM products WHERE id = ?', [$pid]);

    if (!$product) {
        flash('error', 'That product is no longer available.');
        redirect_if_post();
    }

    $stock = (int) $product['stock'];
    if ($stock <= 0) {
        flash('error', e($product['name']) . ' is out of stock.');
        redirect_if_post();
    }

    $existing = $db->one('SELECT * FROM cart WHERE user_id = ? AND pid = ?', [current_user_id(), $pid]);
    $newQty   = $qty + (int) ($existing['quantity'] ?? 0);

    if ($newQty > $stock) {
        flash('warning', 'Only ' . $stock . ' of ' . e($product['name']) . ' left in stock.');
        redirect_if_post();
    }

    if ($existing) {
        $db->run('UPDATE cart SET quantity = ?, name = ?, price = ?, image = ? WHERE id = ?', [
            $newQty, $product['name'], effective_price($product), $product['image'], (int) $existing['id'],
        ]);
        flash('success', e($product['name']) . ' quantity updated in your cart.');
    } else {
        $db->run('INSERT INTO cart (user_id, pid, name, price, quantity, image) VALUES (?,?,?,?,?,?)', [
            current_user_id(), $pid, $product['name'], effective_price($product), $qty, $product['image'],
        ]);
        flash('success', e($product['name']) . ' added to your cart.');
    }

    redirect_if_post();
}

if ($action === 'update') {
    if (!is_logged_in()) {
        redirect('login.php');
    }

    $cartId = (int) ($_POST['cart_id'] ?? 0);
    $qty    = (int) ($_POST['qty'] ?? 1);

    $row = $db->one(
        'SELECT c.id, c.pid, p.stock, p.name
         FROM cart c JOIN products p ON p.id = c.pid
         WHERE c.id = ? AND c.user_id = ?',
        [$cartId, current_user_id()]
    );

    if (!$row) {
        flash('error', 'That cart item no longer exists.');
        redirect_if_post();
    }

    $stock = (int) $row['stock'];
    $qty   = max(1, min($qty, (int) $config['order']['max_qty_per_item']));

    if ($stock <= 0) {
        $db->run('DELETE FROM cart WHERE id = ?', [$cartId]);
        flash('warning', e($row['name']) . ' sold out and was removed from your cart.');
        redirect_if_post();
    }

    if ($qty > $stock) {
        $qty = $stock;
        flash('warning', 'Only ' . $stock . ' of ' . e($row['name']) . ' available.');
    }

    $db->run('UPDATE cart SET quantity = ? WHERE id = ?', [$qty, $cartId]);
    flash('success', 'Cart updated.');
    redirect_if_post();
}

if ($action === 'remove') {
    if (!is_logged_in()) {
        redirect('login.php');
    }
    $db->run('DELETE FROM cart WHERE id = ? AND user_id = ?', [
        (int) ($_POST['cart_id'] ?? 0), current_user_id(),
    ]);
    flash('success', 'Item removed from your cart.');
    redirect_if_post();
}

if ($action === 'clear') {
    if (!is_logged_in()) {
        redirect('login.php');
    }
    $db->run('DELETE FROM cart WHERE user_id = ?', [current_user_id()]);
    flash('success', 'Your cart is now empty.');
    redirect_if_post();
}
