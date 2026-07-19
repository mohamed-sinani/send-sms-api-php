<?php
/**
 * SMS Helper Library (zero dependencies)
 *
 * Usage:
 *   require_once __DIR__ . '/smsAPI.php';
 *   $result = sendSMS('0712345678', 'Your OTP is 123456.');
 *   echo $result['message_id'];
 *
 * Set your API key in .env file as SMS_API_KEY=your-key
 */

function loadEnv(string $path) {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) $_ENV[$key] = $val;
        putenv("$key=$val");
    }
}

loadEnv(__DIR__ . '/.env');

function sendSMS(string $to, string $message, string $from = null): array {
    $apiKey = getenv('SMS_API_KEY') ?: ($_ENV['SMS_API_KEY'] ?? null);
    if (!$apiKey) {
        throw new RuntimeException('SMS_API_KEY not set. Add it to your .env file.');
    }

    $apiUrl = getenv('SMS_API_URL') ?: ($_ENV['SMS_API_URL'] ?? 'https://api.sendafrica.online/v1/sms/');

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
