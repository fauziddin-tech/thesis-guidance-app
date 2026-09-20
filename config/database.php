<?php
// Gunakan environment variable di server atau salin database.example.php
// menjadi database.local.php. File lokal tidak boleh diunggah ke GitHub.
$localConfig = [];
$localConfigPath = __DIR__ . '/database.local.php';
if (is_file($localConfigPath)) {
    $loadedConfig = require $localConfigPath;
    if (is_array($loadedConfig)) $localConfig = $loadedConfig;
}

$dbHost = getenv('DB_HOST') ?: ($localConfig['host'] ?? '');
$dbUser = getenv('DB_USER') ?: ($localConfig['user'] ?? '');
$dbPass = getenv('DB_PASS') ?: ($localConfig['pass'] ?? '');
$dbName = getenv('DB_NAME') ?: ($localConfig['name'] ?? '');

if ($dbHost === '' || $dbUser === '' || $dbName === '') {
    error_log('Konfigurasi database MyThesis belum lengkap.');
    http_response_code(500);
    exit('Aplikasi belum terhubung ke basis data. Hubungi administrator.');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    error_log('Koneksi database MyThesis gagal: ' . $conn->connect_error);
    http_response_code(500);
    exit('Layanan basis data sedang tidak tersedia. Silakan coba kembali.');
}

$conn->set_charset('utf8mb4');
