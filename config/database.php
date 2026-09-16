<?php
// Konfigurasi Database untuk cPanel

define('DB_HOST', 'localhost');
define('DB_USER', 'aulg3645_mythesis');
define('DB_PASS', 'p@frZIIlXHA60F&c');
define('DB_NAME', 'aulg3645_mythesis');

// Koneksi ke database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

// Set charset UTF-8
$conn->set_charset("utf8mb4");

?>