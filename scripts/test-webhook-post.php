<?php

declare(strict_types=1);

/**
 * Local webhook POST smoke test (same signing as merchant webhooks).
 *
 * Usage:
 *   php scripts/test-webhook-post.php [callback_url]
 */
$url = $argv[1] ?? 'https://httpbin.org/post';
$secret = 'test-secret';

$body = json_encode([
    'event' => 'payment.updated',
    'timestamp' => gmdate('c'),
    'data' => [
        'payment_id' => '00000000-0000-0000-0000-000000000001',
        'status' => 'paid',
        'amount' => 100,
        'currency' => 'PHP',
        'gateway' => 'gcash',
        'reference' => 'TEST-REF',
        'provider_reference' => null,
        'paid_at' => gmdate('c'),
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

$timestamp = (string) time();
$signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: GatewayHub-Webhooks/1.0',
        'X-Merchant-Timestamp: '.$timestamp,
        'X-Merchant-Signature: '.$signature,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "POST {$url}\n";
echo "HTTP {$code}\n";
if ($err !== '') {
    echo "cURL error: {$err}\n";
    exit(1);
}

echo $response."\n";
