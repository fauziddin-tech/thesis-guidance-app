<?php
// Konfigurasi Database untuk cPanel

define('DB_HOST', 'localhost');
define('DB_USER', 'your_cpanel_username');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'your_database_name');

// Koneksi ke database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

// Set charset UTF-8
$conn->set_charset("utf8mb4");

?>