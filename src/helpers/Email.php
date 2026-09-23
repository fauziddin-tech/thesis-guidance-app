<?php
/**
 * Resend email helper.
 * Konfigurasi dibaca dari konstanta di config/database.local.php (atau environment variable):
 * RESEND_API_KEY, MAIL_FROM; opsional MAIL_FROM_NAME, APP_URL.
 * API key tidak boleh ditulis di file ini karena file ini tersimpan di GitHub.
 */
// Alasan kegagalan pengiriman terakhir, untuk ditampilkan pada tes email admin.
function resend_last_error(?string $message = null): string {
    static $last = '';
    if ($message !== null) $last = $message;
    return $last;
}

function send_resend_email(string $to, string $subject, string $html): bool {
    resend_last_error('');
    $apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : (getenv('RESEND_API_KEY') ?: '');
    $from = defined('MAIL_FROM') ? MAIL_FROM : (getenv('MAIL_FROM') ?: '');
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (getenv('MAIL_FROM_NAME') ?: 'MyThesis');
    if ($apiKey === '' || $from === '') {
        error_log('Resend email is not configured. Set RESEND_API_KEY and MAIL_FROM.');
        resend_last_error('RESEND_API_KEY atau MAIL_FROM belum diisi di config/database.local.php.');
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('Resend email failed: PHP cURL extension is not enabled.');
        resend_last_error('Ekstensi PHP cURL belum aktif di hosting.');
        return false;
    }

    $payload = json_encode([
        'from' => $fromName . ' <' . $from . '>',
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log('Resend email failed. HTTP ' . $httpCode . ($curlError ? ' - ' . $curlError : '') . ($response ? ' - ' . substr($response, 0, 500) : ''));
        $apiMessage = '';
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            $apiMessage = is_array($decoded) ? (string)($decoded['message'] ?? '') : substr($response, 0, 200);
        }
        resend_last_error(trim(($httpCode ? 'HTTP ' . $httpCode : 'Tidak dapat terhubung ke Resend') . ($curlError ? ' — ' . $curlError : '') . ($apiMessage !== '' ? ' — ' . $apiMessage : '')));
        return false;
    }

    return true;
}

function email_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function send_user_email(mysqli $conn, int $userId, string $subject, string $title, string $message, ?string $link = null, ?string $buttonText = null): bool {
    $stmt = $conn->prepare('SELECT email,nama_lengkap FROM users WHERE id=? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) return false;

    $name = email_escape($user['nama_lengkap'] ?? 'Pengguna');
    $safeTitle = email_escape($title);
    $safeMessage = nl2br(email_escape($message));
    $button = '';
    if ($link) {
        $url = $link;
        if (preg_match('#^https?://#i', $url) !== 1) {
            $base = rtrim((defined('APP_URL') && APP_URL !== '') ? APP_URL : (getenv('APP_URL') ?: 'https://mythesis.my.id'), '/');
            if ($base !== '') $url = $base . '/' . ltrim($url, '/');
        }
        $button = '<p style="margin:24px 0"><a href="' . email_escape($url) . '" style="display:inline-block;padding:10px 16px;background:#985E6D;color:#fff;text-decoration:none;border-radius:5px;font-weight:700">' . email_escape($buttonText ?: 'Buka MyThesis') . '</a></p>';
    }

    $html = '<!doctype html><html lang="id"><body style="margin:0;padding:24px;background:#F2EFF1;font-family:Arial,Segoe UI,sans-serif;color:#192231">'
          . '<div style="max-width:640px;margin:auto;background:#fff;border:1px solid #E3DEE1;border-radius:8px;padding:28px">'
          . '<div style="font-size:20px;font-weight:700;color:#192231;margin-bottom:22px">MyThesis</div>'
          . '<p>Halo ' . $name . ',</p>'
          . '<h2 style="font-size:21px;margin:16px 0 10px">' . $safeTitle . '</h2>'
          . '<p style="line-height:1.6">' . $safeMessage . '</p>'
          . $button
          . '<p style="font-size:12px;color:#6B5F66;border-top:1px solid #E3DEE1;padding-top:18px;margin-top:26px">Email ini dikirim otomatis oleh MyThesis. Jika Anda tidak melakukan aktivitas tersebut, silakan hubungi administrator.</p>'
          . '</div></body></html>';

    return send_resend_email($user['email'], $subject, $html);
}

/**
 * Menyimpan notifikasi di aplikasi sekaligus mengirimkannya ke email pengguna melalui Resend.
 * Kegagalan email tidak membatalkan notifikasi di aplikasi.
 */
function notify_user(mysqli $conn, int $userId, string $type, string $message, string $link, string $subject, string $title, string $buttonText = 'Buka MyThesis'): void {
    $stmt = $conn->prepare('INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,?,?,?)');
    if ($stmt) {
        $stmt->bind_param('isss', $userId, $type, $message, $link);
        $stmt->execute();
        $stmt->close();
    }
    send_user_email($conn, $userId, $subject, $title, $message, $link, $buttonText);
}
