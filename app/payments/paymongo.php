<?php

declare(strict_types=1);

function paymongo_config(): array
{
    global $config;
    return $config['paymongo'];
}

function paymongo_base_url(): string
{

    return 'https://api.paymongo.com';
}

function paymongo_is_enabled(): bool
{
    $pm = paymongo_config();
    return trim((string) $pm['secret_key']) !== '';
}

function paymongo_payment_methods(): array
{
    return [
        'gcash'     => ['label' => 'GCash',                 'icon' => 'fa-mobile-screen-button', 'hint' => 'Pay with your GCash balance'],
        'paymaya'   => ['label' => 'Maya',                  'icon' => 'fa-wallet',              'hint' => 'Pay with your Maya wallet'],
        'grab_pay'  => ['label' => 'GrabPay',               'icon' => 'fa-car',                  'hint' => 'Pay with GrabPay'],
        'shopeepay' => ['label' => 'ShopeePay',             'icon' => 'fa-bag-shopping',         'hint' => 'Pay with ShopeePay'],
        'qrph'      => ['label' => 'QR Ph',                 'icon' => 'fa-qrcode',               'hint' => 'Scan with any PH app'],
        'card'      => ['label' => 'Credit / Debit Card',   'icon' => 'fa-credit-card',          'hint' => 'Visa, Mastercard, JCB, Amex'],
        'dob'       => ['label' => 'Online Banking',        'icon' => 'fa-building-columns',     'hint' => 'Pay from your bank account'],
    ];
}

function is_paymongo_method(string $method): bool
{
    return array_key_exists($method, paymongo_payment_methods());
}

class PayMongoException extends RuntimeException
{
    public array $payload;

    public function __construct(string $message, array $payload = [])
    {
        parent::__construct($message);
        $this->payload = $payload;
    }
}

function paymongo_request(string $method, string $path, array $body = []): array
{
    $pm = paymongo_config();
    $secret = trim((string) $pm['secret_key']);

    if ($secret === '') {
        throw new PayMongoException('PayMongo is not configured. Add your secret key to components/config.php.');
    }
    if (!function_exists('curl_init')) {
        throw new PayMongoException('The cURL PHP extension is required for PayMongo payments.');
    }

    $url = paymongo_base_url() . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_USERPWD        => $secret . ':',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $body === [] ? '{}' : json_encode($body),
    ]);

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new PayMongoException('Could not reach PayMongo: ' . $error);
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        $decoded = ['raw' => $response];
    }

    if ($status < 200 || $status >= 300) {
        $errors = $decoded['errors'][0]['detail'] ?? ($decoded['message'] ?? 'Unknown PayMongo error');
        throw new PayMongoException(is_array($errors) ? json_encode($errors) : (string) $errors, $decoded);
    }

    return $decoded;
}

function paymongo_create_checkout_session(
    array $lineItems,
    string $reference,
    string $successUrl,
    string $cancelUrl,
    array $methodTypes,
    ?string $customerEmail = null,
    array $billing = []
): array {
    $pm = paymongo_config();

    $payloadItems = [];
    foreach ($lineItems as $item) {
        $payloadItems[] = [
            'name'     => mb_substr((string) $item['name'], 0, 120),
            'amount'   => (int) round($item['amount']),
            'currency' => 'PHP',
            'quantity' => max(1, (int) $item['quantity']),
        ];
    }
    if ($payloadItems === []) {
        throw new PayMongoException('Cannot create a checkout session with no items.');
    }

    $attributes = [
        'line_items'          => $payloadItems,
        'payment_method_types'=> array_values($methodTypes),
        'success_url'         => $successUrl,
        'cancel_url'          => $cancelUrl,
        'reference_number'    => mb_substr($reference, 0, 120),
        'send_email_receipt'  => false,
        'show_description'    => true,
        'show_line_items'     => true,
        'metadata'            => ['reference' => $reference],
    ];

    if ($customerEmail !== null && $customerEmail !== '') {
        $attributes['customer_email'] = $customerEmail;
    }

    if ($billing !== []) {
        $attributes['billing'] = array_filter([
            'name'     => $billing['name']     ?? null,
            'email'    => $billing['email']    ?? null,
            'phone'    => $billing['phone']    ?? null,
            'address'  => array_filter([
                'line1'       => $billing['address'] ?? null,
                'country'     => 'PH',
                'postal_code' => $billing['postal_code'] ?? null,
                'state'       => $billing['state'] ?? null,
                'city'        => $billing['city'] ?? null,
            ]),
        ], static fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    $response = paymongo_request('POST', '/v2/checkout_sessions', [
        'data' => ['attributes' => $attributes],
    ]);

    $id           = $response['data']['id'] ?? '';
    $checkoutUrl  = $response['data']['attributes']['checkout_url'] ?? '';

    if ($id === '' || $checkoutUrl === '') {
        throw new PayMongoException('PayMongo did not return a checkout URL.', $response);
    }

    return [
        'id'           => $id,
        'checkout_url' => $checkoutUrl,
        'raw'          => $response,
    ];
}

function paymongo_get_checkout_session(string $checkoutSessionId): array
{
    return paymongo_request('GET', '/v1/checkout_sessions/' . rawurlencode($checkoutSessionId));
}

function paymongo_checkout_outcome(string $checkoutSessionId): array
{
    $session = paymongo_get_checkout_session($checkoutSessionId);
    $attrs   = $session['data']['attributes'] ?? [];

    $payments    = $attrs['payments'] ?? [];
    $status      = (string) ($attrs['status'] ?? 'unknown');
    $paid        = false;
    $paymentId   = null;

    foreach ($payments as $payment) {
        $paymentId = $payment['id'] ?? $paymentId;
        $pStatus   = (string) ($payment['attributes']['status'] ?? '');
        if (in_array($pStatus, ['paid', 'succeeded'], true)) {
            $paid = true;
            break;
        }
    }

    if (!$paid && in_array($status, ['paid', 'completed', 'succeeded'], true)) {
        $paid = true;
    }

    return [
        'paid'            => $paid,
        'status'          => $status,
        'payment_id'      => $paymentId,
        'reference_number'=> $attrs['reference_number'] ?? null,
        'raw'             => $session,
    ];
}

function paymongo_verify_webhook(string $rawBody, ?string $signatureHeader): bool
{
    $secret = trim((string) paymongo_config()['webhook_secret']);
    if ($secret === '') {

        return false;
    }
    if ($signatureHeader === null || $signatureHeader === '') {
        return false;
    }

    $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    return hash_equals($expected, $signatureHeader);
}

function paymongo_webhook_status(string $eventType): ?string
{
    return match ($eventType) {
        'checkout_session.payment.paid',
        'payment.paid'            => 'paid',
        'checkout_session.payment.failed',
        'payment.failed'          => 'failed',
        'checkout_session.expired' => 'cancelled',
        default                   => null,
    };
}
