# SMS Sender PHP

A clean, lightweight PHP toolkit for sending SMS messages via the [SendAfrica API](https://docs.sendafrica.online). Send to one or multiple recipients with a polished web UI — no frameworks required.

![SMS Sender UI](screenshots/image.png)

## Features

- Send SMS to single or multiple recipients at once
- Real-time character counter with SMS part detection (GSM-7 / Unicode)
- Live per-recipient status (pending, sending, sent, failed)
- Animated progress bar during bulk sends
- Toast notifications for success/failure feedback
- Glowing blue button with loading spinner
- Clean white UI — mobile responsive
- API key stored in `.env` (never hardcoded)

## Quick Start

### 1. Clone the repo

```bash
git clone https://github.com/mohamed-sinani/send-sms-api-php.git
cd send-sms-api-php
```

### 2. Install dependencies

```bash
composer require vlucas/phpdotenv
```

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` with your [SendAfrica API key](https://app.sendafrica.online):

```
SMS_API_KEY=SA-your-key-here
SMS_API_URL=https://api.sendafrica.online/v1/sms/
```

### 4. Run

Start your PHP server and open `send-sms.php` in your browser:

```bash
php -S localhost:8000
```

## Usage as a Library

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

## Files

| File | Purpose |
|------|---------|
| `smsAPI.php` | Reusable PHP function — `require_once` it into your project |
| `send-sms.php` | Browser-based SMS sender with multi-recipient support |
| `.env.example` | Template for environment variables |
| `developer.md` | Full API guide (SendAfrica + Africa's Talking) |

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `SMS_API_KEY` | Yes | Your SendAfrica API key |
| `SMS_API_URL` | No | API endpoint (defaults to `https://api.sendafrica.online/v1/sms/`) |

## API Providers

This toolkit uses **SendAfrica** which wraps [Africa's Talking](https://africastalking.com) — the underlying SMS gateway for Tanzania. See [`developer.md`](developer.md) for:

- SendAfrica API reference (recommended — simpler setup)
- Direct Africa's Talking integration (alternative)

## License

MIT
