<?php
session_start();
require_once 'config/database.php';

// Routing sederhana
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Bimbingan Skripsi</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>📚 Bimbingan Skripsi</h1>
            <ul class="nav-menu">
                <li><a href="?page=home">Beranda</a></li>
                <?php if (isset($_SESSION['user'])): ?>
                    <li><a href="?page=dashboard">Dashboard</a></li>
                    <li><a href="?page=logout">Logout</a></li>
                <?php else: ?>
                    <li><a href="?page=login">Login</a></li>
                    <li><a href="?page=register">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php
        switch ($page) {
            case 'home':
                include 'src/views/home.php';
                break;
            case 'login':
                include 'src/views/login.php';
                break;
            case 'register':
                include 'src/views/register.php';
                break;
            case 'dashboard':
                if (!isset($_SESSION['user'])) {
                    header('Location: ?page=login');
                    exit();
                }
                include 'src/views/dashboard.php';
                break;
            default:
                include 'src/views/home.php';
        }
        ?>
    </div>

    <footer>
        <p>&copy; 2024 Aplikasi Bimbingan Skripsi. All rights reserved.</p>
    </footer>

    <script src="public/js/script.js"></script>
</body>
</html>
