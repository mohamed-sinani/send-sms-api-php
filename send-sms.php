<?php
require_once __DIR__ . '/smsAPI.php';

$apiKey = $_ENV['SMS_API_KEY'] ?? null;
$apiUrl = $_ENV['SMS_API_URL'] ?? 'https://api.sendafrica.online/v1/sms/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $phone   = $_POST['phone']   ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Phone number and message are required.']);
        exit;
    }
    if (!$apiKey) {
        echo json_encode(['success' => false, 'error' => 'SMS_API_KEY is not set. Check your .env file.']);
        exit;
    }

    $payload = ['to' => $phone, 'message' => $message];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 10,
    ]);

    $body  = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo json_encode(['success' => false, 'error' => 'cURL Error: ' . $error]);
        exit;
    }

    $data = json_decode($body, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo json_encode([
            'success'    => true,
            'message'    => 'Message sent!',
            'credits'    => $data['data']['credits_used'] ?? '?',
            'message_id' => $data['data']['message_id'] ?? '',
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error'   => $data['error']['message'] ?? 'Unknown API error',
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Sender </title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f0f4ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 44px 40px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 4px 24px rgba(59,130,246,0.08), 0 1px 4px rgba(0,0,0,0.04);
            animation: slideUp 0.5s cubic-bezier(0.16,1,0.3,1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        h1 { font-size: 26px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .subtitle { color: #64748b; margin-bottom: 28px; font-size: 15px; }

        .form-group { margin-bottom: 22px; }

        label {
            display: block; margin-bottom: 8px; font-size: 13px;
            font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.3px;
        }

        input[type="text"], textarea {
            width: 100%; padding: 13px 16px; background: #f8fafc;
            border: 1.5px solid #e2e8f0; border-radius: 12px;
            color: #1e293b; font-size: 15px; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        input[type="text"]:focus, textarea:focus {
            outline: none; border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15); background: #fff;
        }

        input::placeholder, textarea::placeholder { color: #94a3b8; }
        textarea { resize: vertical; min-height: 100px; }

        .hint {
            font-size: 12px; color: #94a3b8; margin-top: 6px;
            display: flex; justify-content: space-between;
        }

        .char-count { color: #3b82f6; font-weight: 600; }
        .char-count.warn { color: #f59e0b; }
        .char-count.over { color: #ef4444; }

        /* ── Recipient rows ── */
        .recipients-header {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;
        }

        .recipient-count {
            font-size: 13px; color: #64748b; font-weight: 500;
        }

        .recipient-count strong { color: #3b82f6; }

        .add-row-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: none; border: 1.5px dashed #cbd5e1; border-radius: 10px;
            padding: 8px 14px; font-size: 13px; font-weight: 600; color: #64748b;
            cursor: pointer; transition: all 0.2s; font-family: inherit;
        }

        .add-row-btn:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }

        .recipients-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 22px; }

        .recipient-row {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px; background: #f8fafc; border: 1.5px solid #e2e8f0;
            border-radius: 12px; animation: rowIn 0.25s ease;
        }

        @keyframes rowIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .recipient-row.removing {
            animation: rowOut 0.2s ease forwards;
        }

        @keyframes rowOut {
            from { opacity: 1; transform: scale(1); }
            to   { opacity: 0; transform: scale(0.95); height: 0; padding: 0; margin: 0; overflow: hidden; }
        }

        .row-num {
            font-size: 12px; font-weight: 700; color: #94a3b8;
            min-width: 20px; text-align: center;
        }

        .recipient-row input[type="text"] {
            flex: 1; padding: 10px 14px; border: none; background: transparent;
            box-shadow: none; font-size: 14px; border-radius: 8px;
        }

        .recipient-row input[type="text"]:focus {
            box-shadow: 0 0 0 2px rgba(59,130,246,0.2); background: #fff;
        }

        .remove-row-btn {
            flex-shrink: 0; width: 28px; height: 28px; border-radius: 8px;
            border: none; background: transparent; color: #cbd5e1; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s; font-size: 18px;
        }

        .remove-row-btn:hover { background: #fef2f2; color: #ef4444; }

        /* ── Row status badge ── */
        .row-status {
            flex-shrink: 0; width: 24px; height: 24px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.3s;
        }

        .row-status svg { width: 12px; height: 12px; }

        .row-status.pending { color: #cbd5e1; }
        .row-status.sending { color: #3b82f6; }
        .row-status.sent    { color: #16a34a; }
        .row-status.failed  { color: #ef4444; }

        .row-status.sending svg { animation: spin 0.6s linear infinite; }

        .mini-spinner {
            width: 16px; height: 16px; border: 2px solid #e2e8f0;
            border-top-color: #3b82f6; border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Buttons ── */
        .btn-row { display: flex; gap: 10px; }

        .btn-send {
            flex: 1; padding: 15px; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 600; cursor: pointer; font-family: inherit;
            color: #fff; background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 0 20px rgba(59,130,246,0.35), 0 4px 12px rgba(37,99,235,0.25);
            animation: glow 2s ease-in-out infinite alternate;
            position: relative; overflow: hidden;
            transition: transform 0.15s, box-shadow 0.3s, opacity 0.2s;
        }

        @keyframes glow {
            from { box-shadow: 0 0 16px rgba(59,130,246,0.3), 0 4px 12px rgba(37,99,235,0.2); }
            to   { box-shadow: 0 0 28px rgba(59,130,246,0.55), 0 4px 16px rgba(37,99,235,0.35); }
        }

        .btn-send:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 0 32px rgba(59,130,246,0.6), 0 8px 20px rgba(37,99,235,0.35);
        }

        .btn-send:active:not(:disabled) { transform: translateY(0); }

        .btn-send:disabled { opacity: 0.85; cursor: not-allowed; animation: none; }

        .btn-text, .btn-spinner-text { transition: opacity 0.2s; }
        .btn-spinner-wrap {
            position: absolute; inset: 0; display: flex; align-items: center;
            justify-content: center; gap: 8px; opacity: 0; transition: opacity 0.2s;
        }

        .btn-send.loading .btn-text        { opacity: 0; }
        .btn-send.loading .btn-spinner-wrap { opacity: 1; }

        .spinner {
            width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite;
        }

        .btn-clear {
            flex-shrink: 0; padding: 15px 18px; border: 1.5px solid #e2e8f0;
            border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600;
            color: #64748b; cursor: pointer; font-family: inherit; transition: all 0.2s;
        }

        .btn-clear:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

        /* ── Progress bar ── */
        .progress-wrap {
            margin-top: 18px; display: none;
        }

        .progress-wrap.visible { display: block; }

        .progress-bar {
            width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;
        }

        .progress-fill {
            height: 100%; background: linear-gradient(90deg, #3b82f6, #818cf8);
            border-radius: 3px; width: 0%; transition: width 0.3s ease;
        }

        .progress-label {
            font-size: 12px; color: #64748b; margin-top: 6px; text-align: center; font-weight: 500;
        }

        /* ── Toasts ── */
        .toast-container {
            position: fixed; top: 24px; right: 24px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px; pointer-events: none;
        }

        .toast {
            pointer-events: auto; display: flex; align-items: flex-start; gap: 12px;
            padding: 16px 20px; border-radius: 14px; font-size: 14px; font-weight: 500;
            line-height: 1.45; max-width: 380px; position: relative; overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            animation: toastIn 0.4s cubic-bezier(0.16,1,0.3,1) forwards;
        }

        .toast.removing { animation: toastOut 0.3s ease forwards; }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(40px) scale(0.95); }
            to   { opacity: 1; transform: translateX(0) scale(1); }
        }

        @keyframes toastOut {
            from { opacity: 1; transform: translateX(0) scale(1); }
            to   { opacity: 0; transform: translateX(40px) scale(0.95); }
        }

        .toast.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .toast.error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .toast.info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

        .toast-icon {
            flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; margin-top: 1px;
        }

        .toast.success .toast-icon { background: #16a34a; }
        .toast.error   .toast-icon { background: #dc2626; }
        .toast.info    .toast-icon { background: #3b82f6; }

        .toast-icon svg { width: 12px; height: 12px; }
        .toast-body { flex: 1; }
        .toast-title { font-weight: 700; margin-bottom: 2px; font-size: 14px; }
        .toast-msg   { font-size: 13px; opacity: 0.85; }

        .toast-close {
            flex-shrink: 0; background: none; border: none; cursor: pointer;
            padding: 0; margin: -2px -4px 0 0; opacity: 0.5; transition: opacity 0.15s;
        }

        .toast-close:hover { opacity: 1; }
        .toast-close svg   { width: 14px; height: 14px; }

        .toast-progress {
            position: absolute; bottom: 0; left: 0; height: 3px;
            border-radius: 0 0 14px 14px; animation: shrink 4s linear forwards;
        }

        .toast.success .toast-progress { background: #16a34a; }
        .toast.error   .toast-progress { background: #dc2626; }
        .toast.info    .toast-progress { background: #3b82f6; }

        @keyframes shrink { from { width: 100%; } to { width: 0%; } }

        .footer {
            text-align: center; margin-top: 28px; font-size: 12px; color: #94a3b8;
        }

        .footer a { color: #3b82f6; text-decoration: none; font-weight: 500; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="toast-container" id="toasts"></div>

<div class="container">
    <h1>SMS Sender Using SMS API</h1>
    <p class="subtitle">Send SMS to one or multiple recipients.</p>

    <form id="smsForm" action="" method="">
        <!-- Shared message -->
        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" placeholder="Type your message here..." required></textarea>
            <span class="hint">
                <span>1 credit per 160 chars (GSM-7) or 70 chars (Unicode).</span>
                <span class="char-count" id="charCount">0 chars</span>
            </span>
        </div>

        <!-- Recipients -->
        <div class="recipients-header">
            <span class="recipient-count">Recipients: <strong id="recipientCount">1</strong></span>
            <button type="button" class="add-row-btn" id="addRowBtn">+ Add number</button>
        </div>

        <div class="recipients-list" id="recipientsList">
            <div class="recipient-row" data-idx="0">
                <span class="row-num">1</span>
                <input type="text" class="phone-input" placeholder="e.g. 0712345678 or +255712345678" required>
                <span class="row-status pending" title="Pending">
                    <svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg>
                </span>
                <button type="button" class="remove-row-btn" title="Remove">&times;</button>
            </div>
        </div>

        <!-- Buttons -->
        <div class="btn-row">
            <button type="submit" class="btn-send" id="sendBtn">
                <span class="btn-text">Send to 1 recipient</span>
                <span class="btn-spinner-wrap"><span class="spinner"></span><span class="btn-spinner-text">Sending...</span></span>
            </button>
            <button type="button" class="btn-clear" id="clearBtn" title="Clear all">Clear</button>
        </div>

        <!-- Progress -->
        <div class="progress-wrap" id="progressWrap">
            <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
            <div class="progress-label" id="progressLabel">0 / 0 sent</div>
        </div>
    </form>

    <div class="footer">
        <a href="https://docs.sendafrica.online" target="_blank">API Docs</a> &middot;
        <a href="https://github.com/mohamed-sinani/send-sms-api-php" target="_blank">GitHub</a>
    </div>
</div>

<script>
(function () {
    const form        = document.getElementById('smsForm');
    const btn         = document.getElementById('sendBtn');
    const btnText     = btn.querySelector('.btn-text');
    const clearBtn    = document.getElementById('clearBtn');
    const addRowBtn   = document.getElementById('addRowBtn');
    const list        = document.getElementById('recipientsList');
    const countEl     = document.getElementById('recipientCount');
    const msgIn       = document.getElementById('message');
    const counter     = document.getElementById('charCount');
    const progWrap    = document.getElementById('progressWrap');
    const progFill    = document.getElementById('progressFill');
    const progLabel   = document.getElementById('progressLabel');

    let rowIdx = 0;

    /* ── SMS counter ── */
    function countSMS(text) {
        const gsm7 = /^[\x00-\x7F\u00C0-\u00C5\u00C7-\u00D6\u00D8-\u00FC\u20AC]*$/;
        const isGSM = gsm7.test(text);
        const limit = isGSM ? 160 : 70;
        const part  = isGSM ? 153 : 67;
        const len   = text.length;
        if (len === 0) return { chars: 0, parts: 0, limit, over: false };
        const parts = len <= limit ? 1 : Math.ceil(len / part);
        return { chars: len, parts, limit, over: len > limit };
    }

    msgIn.addEventListener('input', function () {
        const r = countSMS(this.value);
        counter.textContent = r.parts > 0
            ? `${r.chars} chars \u00B7 ${r.parts} SMS part${r.parts > 1 ? 's' : ''}`
            : '0 chars';
        counter.className = 'char-count' + (r.over ? ' over' : r.chars > r.limit - 20 ? ' warn' : '');
    });

    /* ── Toast system ── */
    const toastContainer = document.getElementById('toasts');
    let toastId = 0;

    function showToast(type, title, msg, duration = 4200) {
        const id = ++toastId;
        const el = document.createElement('div');
        el.className = 'toast ' + type;

        const checkSVG = '<svg viewBox="0 0 12 12" fill="none"><path d="M2.5 6.5L5 9l4.5-6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const crossSVG = '<svg viewBox="0 0 12 12" fill="none"><path d="M3 3l6 6M9 3l-6 6" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
        const infoSVG  = '<svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#fff" stroke-width="1.5"/><path d="M6 5.5v3M6 3.5v.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></svg>';

        const icon = type === 'success' ? checkSVG : type === 'error' ? crossSVG : infoSVG;

        el.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <div class="toast-body">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${msg}</div>
            </div>
            <button class="toast-close" aria-label="Close">${crossSVG}</button>
            <span class="toast-progress" style="animation-duration:${duration}ms"></span>
        `;

        el.querySelector('.toast-close').addEventListener('click', () => dismiss(el));
        toastContainer.appendChild(el);
        setTimeout(() => dismiss(el), duration + 200);
    }

    function dismiss(el) {
        if (el.classList.contains('removing')) return;
        el.classList.add('removing');
        el.addEventListener('animationend', () => el.remove());
    }

    /* ── Row management ── */
    function getRows()     { return [...list.querySelectorAll('.recipient-row')]; }
    function updateCount() { countEl.textContent = getRows().length; updateBtnLabel(); }

    function updateBtnLabel() {
        const n = getRows().length;
        btnText.textContent = `Send to ${n} recipient${n !== 1 ? 's' : ''}`;
    }

    function createRow() {
        rowIdx++;
        const row = document.createElement('div');
        row.className = 'recipient-row';
        row.dataset.idx = rowIdx;
        row.innerHTML = `
            <span class="row-num"></span>
            <input type="text" class="phone-input" placeholder="e.g. 0712345678 or +255712345678" required>
            <span class="row-status pending" title="Pending">
                <svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg>
            </span>
            <button type="button" class="remove-row-btn" title="Remove">&times;</button>
        `;
        list.appendChild(row);
        renumber();
        updateCount();
        row.querySelector('input').focus();
    }

    function removeRow(row) {
        if (getRows().length <= 1) return;
        row.classList.add('removing');
        row.addEventListener('animationend', () => { row.remove(); renumber(); updateCount(); });
    }

    function renumber() {
        getRows().forEach((r, i) => {
            r.querySelector('.row-num').textContent = i + 1;
        });
    }

    function setRowStatus(row, status) {
        const el = row.querySelector('.row-status');
        el.className = 'row-status ' + status;
        const icons = {
            pending: '<svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg>',
            sending: '<span class="mini-spinner"></span>',
            sent:    '<svg viewBox="0 0 12 12" fill="none"><path d="M2.5 6.5L5 9l4.5-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            failed:  '<svg viewBox="0 0 12 12" fill="none"><path d="M3 3l6 6M9 3l-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        };
        el.innerHTML = icons[status] || '';
        el.title = status.charAt(0).toUpperCase() + status.slice(1);
    }

    /* ── Events ── */
    addRowBtn.addEventListener('click', createRow);

    list.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-row-btn');
        if (btn) removeRow(btn.closest('.recipient-row'));
    });

    clearBtn.addEventListener('click', function () {
        list.innerHTML = '';
        rowIdx = 0;
        createRow();
        progWrap.classList.remove('visible');
        showToast('info', 'Cleared', 'All recipients removed.');
    });

    /* Enter in phone input adds a new row */
    list.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.classList.contains('phone-input')) {
            e.preventDefault();
            createRow();
        }
    });

    /* ── Send logic ── */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const message = msgIn.value.trim();
        if (!message) {
            showToast('error', 'Missing message', 'Please type a message to send.');
            return;
        }

        const rows = getRows();
        const phones = rows.map(r => r.querySelector('input').value.trim()).filter(Boolean);

        if (phones.length === 0) {
            showToast('error', 'No recipients', 'Add at least one phone number.');
            return;
        }

        const total = phones.length;
        let sent = 0, failed = 0;

        btn.classList.add('loading');
        btn.disabled = true;
        addRowBtn.disabled = true;
        progWrap.classList.add('visible');
        progFill.style.width = '0%';
        progLabel.textContent = `0 / ${total} sent`;
        btnText.textContent = `Sending to ${total}...`;

        for (let i = 0; i < rows.length; i++) {
            const row   = rows[i];
            const phone = row.querySelector('input').value.trim();
            if (!phone) continue;

            setRowStatus(row, 'sending');

            try {
                const fd = new FormData();
                fd.append('phone', phone);
                fd.append('message', message);

                const resp = await fetch('', { method: 'POST', body: fd });
                const data = await resp.json();

                if (data.success) {
                    setRowStatus(row, 'sent');
                    sent++;
                } else {
                    setRowStatus(row, 'failed');
                    failed++;
                    row.title = data.error;
                }
            } catch {
                setRowStatus(row, 'failed');
                failed++;
                row.title = 'Network error';
            }

            const done = i + 1;
            progFill.style.width = Math.round((done / total) * 100) + '%';
            progLabel.textContent = `${done} / ${total} sent` + (failed ? ` \u00B7 ${failed} failed` : '');

            /* small delay between sends to respect rate limits */
            if (i < rows.length - 1) await new Promise(r => setTimeout(r, 120));
        }

        btn.classList.remove('loading');
        btn.disabled = false;
        addRowBtn.disabled = false;
        btnText.textContent = `Send to ${total} recipient${total !== 1 ? 's' : ''}`;

        if (failed === 0) {
            showToast('success', 'All sent!', `${sent} message${sent !== 1 ? 's' : ''} delivered successfully.`);
        } else {
            showToast('error', 'Partial failure', `${sent} sent, ${failed} failed. Hover red rows for details.`);
        }
    });

    /* ── Init ── */
    updateCount();
})();
</script>

</body>
</html>
