# SendAfrica API — PHP Developer Guide

A lightweight PHP toolkit for sending SMS messages via the [SendAfrica API](https://docs.sendafrica.online). Drop `smsAPI.php` into any project, set an environment variable, and start sending.

**Base URL:** `https://api.sendafrica.online`  
**Protocol:** HTTPS only  
**Format:** JSON (`Content-Type: application/json`)

---

## Quick Start

### 1. Get your API key

1. Create an account at [app.sendafrica.online](https://app.sendafrica.online)
2. Verify your email with the OTP sent to you
3. Go to **Settings → API Keys**
4. Click **Create API Key**, name it, copy the key

The key is shown **only once**. Store it in your `.env` immediately.

### 2. Install dependencies

```bash
composer require vlucas/phpdotenv
```

### 3. Set environment variables

Copy `.env.example` to `.env` and fill in your key:

```
SMS_API_KEY=SA-your-key-here
SMS_API_URL=https://api.sendafrica.online/v1/sms/
```

### 4. Send an SMS

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/smsAPI.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $result = sendSMS('0712345678', 'Hello from PHP!');
    echo "Sent: {$result['message_id']} | Credits: {$result['credits_used']}";
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## Alternative: Using Africa's Talking Directly

This toolkit uses **SendAfrica** under the hood, which wraps [Africa's Talking](https://africastalking.com) — the underlying SMS gateway for Tanzania. You can also use Africa's Talking directly if you prefer.

### Why SendAfrica is recommended

| | SendAfrica | Africa's Talking (direct) |
|---|---|---|
| **Setup** | 1 API key, done | API key + username + optional hash |
| **Auth header** | `X-API-Key` | `apiKey` in body or `Authorization: Bearer` |
| **Balance check** | Simple JSON endpoint | Separate SDK or AT dashboard |
| **Topping up** | Mobile money via API | Bank/card via AT dashboard |
| **Pricing** | Pay-per-credit, any amount | Fixed packages |
| **Dashboard** | Modern, developer-focused | Legacy interface |
| **Docs** | Single-page, copy-paste ready | Multi-page, can be confusing |

**Bottom line:** If you just want to send SMS in Tanzania, SendAfrica is simpler. If you need multi-country support, other AT products (voice, airtime, etc.), or already have an AT account, use Africa's Talking directly.

### Africa's Talking — Quick example (PHP)

```php
<?php
/**
 * Direct Africa's Talking SMS — for users who already have an AT account.
 *
 * Env vars: AT_API_KEY, AT_USERNAME, AT_SENDER_ID
 */

function sendViaAT(string $to, string $message, string $from = null): string {
    $apiKey  = getenv('AT_API_KEY');
    $username = getenv('AT_USERNAME') ?: 'sandbox';

    if (!$apiKey) {
        throw new RuntimeException('AT_API_KEY not set');
    }

    // Africa's Talking uses a form-encoded POST (not JSON)
    $payload = [
        'username'    => $username,
        'to'          => $to,
        'message'     => $message,
    ];
    if ($from) {
        $payload['from'] = $from;
    }

    $ch = curl_init('https://api.africastalking.com/version1/messaging');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "apiKey: $apiKey",
            "Accept: application/json",
        ],
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_TIMEOUT        => 10,
    ]);

    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("cURL Error: $err");
    }

    $data = json_decode($body, true);

    if (isset($data['SMSMessageData']['Recipients'][0]['status'])) {
        return $data['SMSMessageData']['Recipients'][0]['messageId'];
    }

    $msg = $data['SMSMessageData']['Message'] ?? json_encode($data);
    throw new RuntimeException("AT Error: $msg");
}

// Usage:
// $msgId = sendViaAT('+255712345678', 'Hello from Africa\'s Talking!');
```

### Africa's Talking — Node.js example

```javascript
const Africastalking = require('africastalking');

const at = Africastalking({
    apiKey:  process.env.AT_API_KEY,
    username: process.env.AT_USERNAME || 'sandbox',
});

async function sendViaAT(to, message) {
    try {
        const result = await at.SMS.send({
            to: [to],
            message,
            from: process.env.AT_SENDER_ID, // optional
        });
        return result.SMSMessageData.Recipients[0];
    } catch (err) {
        throw new Error(`AT Error: ${err}`);
    }
}
```

### Africa's Talking — env vars needed

```
AT_API_KEY=your-at-api-key
AT_USERNAME=your-at-username        # 'sandbox' for testing
AT_SENDER_ID=YourSender            # optional, must be registered with AT
```

> **Tip:** Africa's Talking has a [sandbox environment](https://sandbox.africastalking.com) for testing — use `username: 'sandbox'` and their test API key. SendAfrica also uses AT under the hood, but handles the sandbox/live complexity for you.

---

## Files

| File | Purpose |
|------|---------|
| `smsAPI.php` | Reusable PHP function — `require_once` it into your project |
| `send-sms.php` | Browser-based SMS sending form (web UI) |
| `.env.example` | Template for environment variables |
| `developer.md` | This file |

---

## Authentication

Every request requires the `X-API-Key` header:

```
X-API-Key: SA-your-key-here
```

The `Authorization: Bearer` header is reserved for dashboard JWT sessions — do not use it with API keys.

---

## Sending SMS

```
POST /v1/sms/
```

### Request body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | **Yes** | Recipient phone number |
| `message` | string | **Yes** | The SMS text to send |
| `from` | string | No | Custom sender ID (must be approved by SendAfrica) |

### Example (cURL)

```bash
curl -X POST https://api.sendafrica.online/v1/sms/ \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "0712345678",
    "message": "Hello! Your order has been confirmed."
  }'
```

### Success response `200`

```json
{
  "success": true,
  "data": {
    "message_id": "f3b1c2d4-9e8a-4f2b-b1c2-d3e4f5a6b7c8",
    "status": "sent",
    "cost": "TZS 30.00",
    "credits_used": 1
  },
  "request_id": "dfffa252-4781-43ff-8e1a-bf01a754d66a",
  "timestamp": "2026-06-11T16:24:05Z"
}
```

---

## Phone Number Format

Only **Tanzania mobile numbers** are accepted:

| Format | Example | Accepted |
|--------|---------|----------|
| Local (07xx / 06xx) | `0712345678` | Yes |
| International with + | `+255712345678` | Yes |
| International without + | `255712345678` | Yes |
| Other countries | `+254712345678` | No |

**Valid prefixes:** 071, 072, 073, 074, 075, 076, 077, 078

---

## SMS Parts and Credits

You are charged **1 credit per SMS part**.

### GSM-7 (standard characters)

| Parts | Character limit |
|-------|----------------|
| 1 | 1–160 chars |
| 2 | 161–306 chars |
| 3 | 307–459 chars |

### Unicode / UCS-2 (emoji, Arabic, etc.)

| Parts | Character limit |
|-------|----------------|
| 1 | 1–70 chars |
| 2 | 71–134 chars |
| 3 | 135–201 chars |

---

## Check Balance

```
GET /v1/credits/balance
```

```bash
curl https://api.sendafrica.online/v1/credits/balance \
  -H "X-API-Key: YOUR_API_KEY"
```

```json
{
  "success": true,
  "data": {
    "account_id": "486f8a6e-ea75-47ea-b176-c8e931aed058",
    "balance": 5000
  }
}
```

---

## Error Handling

| HTTP | Code | Cause | Fix |
|------|------|-------|-----|
| `400` | `validation_error` | Missing `to` or `message` | Include both fields |
| `400` | `invalid_phone` | Not a valid Tanzania number | Use `07XXXXXXXX` or `+255XXXXXXXX` |
| `401` | `unauthorized` | Invalid API key | Check key in dashboard |
| `402` | `insufficient_credits` | Not enough credits | Top up |
| `429` | `rate_limit_exceeded` | Too many requests | Slow down or upgrade |
| `500` | `server_error` | Server-side error | Retry with backoff |

---

## Rate Limits

| Plan | Requests/min |
|------|-------------|
| Free | 60 |
| Pro | 600 |
| Enterprise | 6,000 |

---

## Delivery Status

`status: "sent"` means the network accepted the message. Actual delivery is confirmed asynchronously. Check final status in your message logs at `/v1/sms/logs` (requires JWT auth).

| Status | Meaning |
|--------|---------|
| `sent` | Accepted by network |
| `delivered` | Confirmed on handset |
| `failed` | Delivery failed |

---

## Sending in Bulk

Space out requests to avoid rate limits:

```php
$numbers = ['0711111111', '0722222222', '0733333333'];
foreach ($numbers as $number) {
    try {
        $result = sendSMS($number, 'Bulk message from PHP');
        echo "Sent to {$number}: {$result['message_id']}\n";
    } catch (RuntimeException $e) {
        echo "Failed for {$number}: " . $e->getMessage() . "\n";
    }
    usleep(100000); // 100ms delay between sends
}
```

---

## Security Best Practices

- Never hardcode API keys — use environment variables
- Rotate keys regularly
- Use separate keys for dev/staging/production
- Validate phone numbers before sending
- Monitor your credit balance

---

## Full API Documentation

For complete API reference, webhooks, bulk sending, message logs, and more:

**[docs.sendafrica.online](https://docs.sendafrica.online)**

---

*SMS Sender PHP Toolkit*
