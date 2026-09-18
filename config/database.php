<?php
/**
 * Database configuration.
 *
 * Production credentials MUST be supplied through environment variables:
 * DB_HOST, DB_USER, DB_PASS, DB_NAME.
 *
 * For local development you may create config/database.local.php (ignored by Git)
 * that defines the same four constants.
 */

$localConfig = __DIR__ . '/database.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: '');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: '');
if (!defined('APP_URL')) define('APP_URL', rtrim(getenv('APP_URL') ?: '', '/'));
if (!defined('MAIL_FROM')) define('MAIL_FROM', getenv('MAIL_FROM') ?: '');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'MyThesis');

if (DB_USER === '' || DB_NAME === '') {
    error_log('Database configuration is incomplete. Set DB_HOST, DB_USER, DB_PASS and DB_NAME.');
    http_response_code(500);
    exit('Konfigurasi database belum lengkap.');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Koneksi database gagal. Silakan hubungi administrator.');
}

$conn->set_charset('utf8mb4');
