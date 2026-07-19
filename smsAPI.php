<?php
/**
 * SMS Helper Library
 *
 * Usage:
 *   require_once __DIR__ . '/smsAPI.php';
 *   $result = sendSMS('0712345678', 'Your OTP is 123456.');
 *   echo $result['message_id'];
 *
 * Set your API key in an environment variable `SMS_API_KEY`
 * and optionally `SMS_API_URL` (defaults to https://api.sendafrica.online/v1/sms/).
 */

function sendSMS(string $to, string $message, string $from = null): array {
    $apiKey = getenv('SMS_API_KEY');
    if (!$apiKey) {
        throw new RuntimeException('Environment variable SMS_API_KEY not set');
    }

    $apiUrl = getenv('SMS_API_URL') ?: 'https://api.sendafrica.online/v1/sms/';

    $payload = ['to' => $to, 'message' => $message];
    if ($from !== null) {
        $payload['from'] = $from;
    }

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "X-API-Key: $apiKey",
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("cURL Error: $err");
    }

    $data = json_decode($body, true);
    if (!($data['success'] ?? false)) {
        $code = $data['error']['code'] ?? 'unknown';
        $msg  = $data['error']['message'] ?? 'unknown error';
        throw new RuntimeException("[$code] $msg");
    }

    return $data['data'];
}
