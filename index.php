<?php
session_start();

// Load required files FIRST
require_once 'config/database.php';
require_once 'src/helpers/Functions.php';

$page = isset($_GET['page']) ? sanitize($_GET['page']) : 'home';

// Define available pages
$pages = [
    'home' => 'src/views/home.php',
    'login' => 'src/views/login.php',
    'register' => 'src/views/register.php',
    'dashboard' => 'src/views/dashboard.php',
    'profile' => 'src/views/profile.php',
    'change-password' => 'src/views/change-password.php',
    'bimbingan-detail' => 'src/views/bimbingan-detail.php',
    'admin-dashboard' => 'src/views/admin-dashboard.php',
];

// Load page
if (array_key_exists($page, $pages) && file_exists($pages[$page])) {
    require_once $pages[$page];
} else {
    header('HTTP/1.1 404 Not Found');
    echo '<div style="text-align: center; padding: 50px;">';
    echo '<h1>404 - Halaman Tidak Ditemukan</h1>';
    echo '<p>Halaman yang Anda cari tidak ada.</p>';
    echo '<a href="?page=home" style="margin-top: 20px; display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; text-decoration: none; border-radius: 4px;">Kembali ke Home</a>';
    echo '</div>';
}
?>
