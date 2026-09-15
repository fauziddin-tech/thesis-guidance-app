<?php
/**
 * GitHub Webhook Handler
 * Setup di GitHub: Settings > Webhooks > Payload URL
 * Akses: http://yourdomain.com/deploy-webhook.php
 */

// ========== KONFIGURASI ==========
$github_secret = 'your_github_webhook_secret'; // Ganti dengan secret dari GitHub
$repository_path = dirname(__FILE__);
$branch = 'main';

// ========== LOGGING ==========
$log_file = dirname(__FILE__) . '/webhook.log';
$timestamp = date('Y-m-d H:i:s');

function log_message($message) {
    global $log_file, $timestamp;
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

log_message('=== Webhook request diterima ===' );

// ========== VALIDASI SIGNATURE ==========
$payload = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';

if (!empty($github_secret)) {
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $github_secret, false);
    
    if ($signature !== $hash) {
        log_message('ERROR: Signature tidak valid!');
        http_response_code(403);
        die('Signature tidak valid');
    }
    log_message('Signature valid');
}

// ========== PARSE PAYLOAD ==========
$data = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message('ERROR: JSON decode failed');
    http_response_code(400);
    die('JSON decode error');
}

log_message('Webhook event: ' . (isset($data['action']) ? $data['action'] : 'push'));

// ========== CEK BRANCH ==========
if (isset($data['ref'])) {
    $pushed_branch = str_replace('refs/heads/', '', $data['ref']);
    log_message('Pushed branch: ' . $pushed_branch);
    
    if ($pushed_branch !== $branch) {
        log_message('Skipped: Branch tidak sesuai');
        http_response_code(200);
        echo json_encode(['status' => 'skipped', 'message' => 'Branch tidak sesuai']);
        exit();
    }
}

// ========== JALANKAN GIT PULL ==========
if (!is_dir($repository_path . '/.git')) {
    log_message('ERROR: Folder .git tidak ditemukan!');
    http_response_code(500);
    die('Repository tidak ditemukan');
}

log_message('Menjalankan git pull...');
$output = shell_exec("cd {$repository_path} && git pull origin {$branch} 2>&1");
log_message('Git output: ' . $output);

// ========== CLEAR CACHE ==========
if (function_exists('opcache_reset')) {
    opcache_reset();
    log_message('OpCache cleared');
}

// ========== RESPONSE ==========
$response = [
    'status' => 'success',
    'message' => 'Deployment berhasil dari webhook',
    'timestamp' => $timestamp,
    'branch' => $pushed_branch ?? 'unknown'
];

log_message('=== Webhook selesai ===');
log_message('');

header('Content-Type: application/json');
http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);
?>
