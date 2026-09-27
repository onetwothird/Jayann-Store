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

$store  = $config['store'];
$lines  = order_lines($order);
$ref    = (string) ($order['order_ref'] ?: ('JYS-' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT)));
$subtotal = (float) ($order['subtotal'] ?: 0);
$shipping = (float) ($order['shipping_fee'] ?? 0);
if ($subtotal <= 0 && (float) $order['total_price'] > 0) {

    $shipping = (float) $order['total_price'];
    $subtotal = $shipping;
}

$currency = $store['currency'];
$peso     = static fn($n) => $currency . number_format((float) $n, 2);

$pdf = new FPDF();
$pdf->SetTitle('Receipt ' . $ref);
$pdf->SetAuthor($store['legal']);
$pdf->AddPage();

$pdf->SetFont('Helvetica', 'B', 20);
$pdf->Cell(0, 10, $store['legal'], 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(0, 6, $store['address'], 0, 1, 'C');
$pdf->Cell(0, 6, $store['phone'] . '  |  ' . $store['email'], 0, 1, 'C');
$pdf->Ln(6);

$pdf->SetDrawColor(238, 77, 45);
$pdf->SetLineWidth(0.6);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(8);

$pdf->SetFont('Helvetica', 'B', 16);
$pdf->Cell(0, 10, 'ORDER RECEIPT', 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 11);
$pdf->Cell(0, 6, 'Reference: ' . $ref, 0, 1, 'C');
$pdf->Ln(8);

$pdf->SetDrawColor(220, 223, 228);
$pdf->SetLineWidth(0.3);

$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Cell(0, 8, 'CUSTOMER DETAILS', 0, 1, 'L');
$pdf->SetFont('Helvetica', '', 11);

$leftX  = 10;
$rightX = 105;
$y      = $pdf->GetY();

$pdf->SetXY($leftX, $y);
$pdf->Cell(90, 6, 'Name:  ' . $order['name'], 0, 1);
$pdf->Cell(90, 6, 'Phone:  ' . $order['number'], 0, 1);
$pdf->Cell(90, 6, 'Email:  ' . $order['email'], 0, 1);
$pdf->Cell(90, 6, 'Placed:  ' . nice_date((string) $order['order_date'], true), 0, 1);

$pdf->SetXY($rightX, $y);
$pdf->Cell(90, 6, 'Payment:  ' . payment_method_label((string) $order['method']), 0, 1);
$pdf->Cell(90, 6, 'Status:  ' . order_status_meta((string) $order['payment_status'])['label'], 0, 1);
if (!empty($order['payment_reference'])) {
    $pdf->Cell(90, 6, 'Gateway ref:  ' . $order['payment_reference'], 0, 1);
}

$pdf->SetXY($leftX, $pdf->GetY());
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(90, 6, 'Delivery address:', 0, 1);
$pdf->SetFont('Helvetica', '', 11);
$pdf->MultiCell(0, 6, (string) $order['address'], 0, 'L');

$pdf->Ln(4);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(8);

$columnX = [10, 34, 128, 152, 200];

$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetFillColor(247, 248, 250);
$pdf->Rect(10, $pdf->GetY(), 190, 8, 'F');
$pdf->SetXY($columnX[0], $pdf->GetY());
$pdf->Cell(0, 8, 'ITEM', 0, 1, 'L');
$pdf->SetXY($columnX[1], $pdf->GetY() - 8);
$pdf->Cell(90, 8, 'QTY', 0, 0, 'C');
$pdf->SetXY($columnX[2], $pdf->GetY());
$pdf->Cell(22, 8, 'PRICE', 0, 0, 'R');
$pdf->SetXY($columnX[3], $pdf->GetY());
$pdf->Cell(46, 8, 'AMOUNT', 0, 1, 'R');
$pdf->Ln(2);

$pdf->SetFont('Helvetica', '', 10);
foreach ($lines as $line) {
    $unit   = $line['unit'] ?? 0.0;
    $amount = $unit * (int) $line['quantity'];
    $rowY   = $pdf->GetY();

    $name = (string) $line['name'];
    $pdf->SetXY($columnX[0], $rowY);
    $pdf->MultiCell(90, 6, $name, 0, 'L');
    $rowH = max(6, $pdf->GetY() - $rowY);

    $pdf->SetXY($columnX[1], $rowY);
    $pdf->Cell(90, $rowH, (string) $line['quantity'], 0, 0, 'C');
    $pdf->SetXY($columnX[2], $rowY);
    $pdf->Cell(22, 6, $unit > 0 ? $peso($unit) : '-', 0, 0, 'R');
    $pdf->SetXY($columnX[3], $rowY);
    $pdf->Cell(46, 6, $unit > 0 ? $peso($amount) : '-', 0, 0, 'R');
    $pdf->SetY($rowY + $rowH);
    $pdf->Ln(2);

    $pdf->SetDrawColor(238, 240, 243);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
}

$pdf->Ln(6);

$pdf->SetFont('Helvetica', '', 11);
$totalX = 120;

$pdf->SetX($totalX);
$pdf->Cell(45, 7, 'Subtotal', 0, 0, 'L');
$pdf->Cell(35, 7, $peso($subtotal), 0, 1, 'R');
$pdf->SetX($totalX);
$pdf->Cell(45, 7, 'Delivery', 0, 0, 'L');
$pdf->Cell(35, 7, $shipping > 0 ? $peso($shipping) : 'Free', 0, 1, 'R');

$pdf->SetX($totalX);
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetDrawColor(238, 77, 45);
$pdf->Line($totalX, $pdf->GetY() + 1, 200, $pdf->GetY() + 1);
$pdf->Ln(2);
$pdf->SetX($totalX);
$pdf->Cell(45, 9, 'TOTAL', 0, 0, 'L');
$pdf->Cell(35, 9, $peso($order['total_price']), 0, 1, 'R');

$pdf->Ln(10);

$pdf->SetFont('Helvetica', 'I', 11);
$pdf->Cell(0, 8, 'Thank you for your purchase!', 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 9);
$pdf->Cell(0, 6, $store['name'] . '  -  ' . $store['address'], 0, 1, 'C');
$pdf->Cell(0, 6, 'Questions? ' . $store['phone'] . '  |  Generated ' . date('d M Y, g:i A'), 0, 1, 'C');

$pdf->Output('D', 'receipt_' . preg_replace('/[^A-Za-z0-9_-]/', '', $ref) . '.pdf');

