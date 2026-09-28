<?php declare(strict_types=1);

include '../app/bootstrap.php';
boot_session();

$orderId = (int) ($_GET['order'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Missing order number.');
}

if (is_admin_logged_in()) {
    $order = $db->one('SELECT * FROM orders WHERE id = ?', [$orderId]);
} elseif (is_logged_in()) {
    $order = $db->one('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$orderId, current_user_id()]);
} else {
    require_login('download_receipt.php?order=' . $orderId);
    $order = null;
}

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

require_once __DIR__ . '/../libs/fpdf.php';
require_once __DIR__ . '/../app/receipt_pdf.php';

$store = $config['store'];
$lines = order_lines($order);
$ref   = (string) ($order['order_ref'] ?: ('JYS-' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT)));

// Older orders stored the goods as plain text rather than JSON, and some rows
// have no stored breakdown at all. Prefer the sum of the actual lines, because
// the previous fallback copied the grand total into both Subtotal and Delivery,
// printing a receipt whose own rows did not add up to its own total.
$total = (float) ($order['total_price'] ?? 0);

$lineSum = 0.0;
foreach ($lines as $line) {
    $lineSum += (float) ($line['unit'] ?? 0) * (int) ($line['quantity'] ?? 0);
}

if ($lineSum > 0) {
    $subtotal = $lineSum;
    // Whatever is left of the paid total is the delivery charge.
    $shipping = $total - $subtotal;
} else {
    $subtotal = (float) ($order['subtotal'] ?: 0);
    $shipping = (float) ($order['shipping_fee'] ?? 0);
}

// A receipt whose own rows do not add up to its own total is worse than one
// that quietly rounds: the customer would be billed a figure the document does
// not show. If the stored figures disagree, trust the amount actually charged.
if ($subtotal <= 0 && $total > 0) {
    $subtotal = $total;
    $shipping = 0.0;
} elseif ($shipping < 0) {
    // Lines add up to more than was charged (a discount applied after the
    // fact, or stale prices). Show the charged total as the goods total.
    $subtotal = $total;
    $shipping = 0.0;
}

$amount = static fn(float $n): string => money($n);

$statusMeta = order_status_meta((string) $order['payment_status']);
$statusTone = match ($statusMeta['class']) {
    'paid', 'completed' => 'good',
    'failed'            => 'bad',
    'cancelled'         => 'warn',
    default             => 'info',
};

$view = [
    'date_label'     => nice_date((string) $order['order_date'], true),
    'status_label'   => (string) $statusMeta['label'],
    'status_tone'    => $statusTone,
    'customer_name'  => (string) $order['name'],
    'customer_phone' => (string) $order['number'],
    'customer_email' => (string) $order['email'],
    'method_label'   => payment_method_label((string) $order['method']),
    'gateway_ref'    => (string) ($order['payment_reference'] ?? ''),
    'address'        => (string) $order['address'],
];

$totals = [
    'subtotal' => $amount($subtotal),
    'shipping' => $shipping > 0 ? $amount($shipping) : 'Free',
    'total'    => $amount($total),
];

// Paper size and orientation are part of the URL, so a receipt can be printed
// on whatever the customer actually has. Anything unrecognised falls back to
// A4 portrait rather than erroring.
$format = strtolower((string) ($_GET['format'] ?? 'a4'));
$allowedFormats = ['a3', 'a4', 'a5', 'letter', 'legal'];
if (!in_array($format, $allowedFormats, true)) {
    $format = 'a4';
}

$orientation = strtoupper((string) ($_GET['orientation'] ?? 'P')) === 'L' ? 'L' : 'P';

$receipt = new ReceiptPdf($store, [
    'format'      => $format,
    'orientation' => $orientation,
    // A filesystem path, not BASE_URL: FPDF embeds the logo by reading it from
    // disk, so a URL here would silently resolve to nothing.
    'logo'        => ROOT_PATH,
]);

// 'D' is a file download; 'I' renders in the browser. Both are a one-click
// print from there, which is what most people actually want from a receipt.
$inline = strtolower((string) ($_GET['view'] ?? '')) === 'inline';
$receipt->setDisposition($inline ? 'I' : 'D');

$receipt->render($view, $lines, $totals, $ref);
