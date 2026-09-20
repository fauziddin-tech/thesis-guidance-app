<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';

$page = isset($_GET['page']) ? (string)$_GET['page'] : 'home';
$allowedPages = ['home', 'login', 'register', 'dashboard'];

if ($page === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ?page=home');
    exit;
}

if (!in_array($page, $allowedPages, true)) $page = 'home';
if ($page === 'dashboard' && !isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$pageTitles = ['home'=>'Beranda','login'=>'Masuk','register'=>'Pendaftaran','dashboard'=>'Dashboard'];
$assetVersion = substr(md5((string)filemtime(__DIR__ . '/public/css/style.css') . (string)filemtime(__DIR__ . '/public/js/script.js')), 0, 10);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="MyThesis - Platform bimbingan skripsi mahasiswa dan dosen.">
    <meta name="theme-color" content="#FFFFFF">
    <title><?=htmlspecialchars($pageTitles[$page] ?? 'MyThesis', ENT_QUOTES, 'UTF-8')?> | MyThesis</title>
    <script>(function(){var d=document.documentElement,t='light';d.classList.add('js');try{var s=localStorage.getItem('mythesis-theme');if(s==='light'||s==='dark')t=s;}catch(e){}d.setAttribute('data-theme',t);})();</script>
    <link rel="stylesheet" href="public/css/style.css?v=<?=$assetVersion?>">
</head>
<body data-page="<?=htmlspecialchars($page, ENT_QUOTES, 'UTF-8')?>">
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<header class="navbar">
    <div class="container nav-inner">
        <a class="brand" href="?page=home" aria-label="MyThesis, kembali ke beranda"><span class="brand-mark" aria-hidden="true">M</span><span class="brand-copy"><strong>MyThesis</strong><small>Ruang kerja skripsi</small></span></a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle><span class="sr-only">Buka menu navigasi</span><span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span></button>
        <nav id="primary-navigation" class="primary-navigation" aria-label="Navigasi utama" data-nav><ul class="nav-menu">
            <li><a class="<?=$page==='home'?'is-active':''?>" <?=$page==='home'?'aria-current="page"':''?> href="?page=home">Beranda</a></li>
            <?php if (isset($_SESSION['user'])): ?>
                <li><a class="<?=$page==='dashboard'?'is-active':''?>" <?=$page==='dashboard'?'aria-current="page"':''?> href="?page=dashboard">Dashboard</a></li><li><a href="?page=logout">Keluar</a></li>
            <?php else: ?>
                <li><a class="<?=$page==='login'?'is-active':''?>" <?=$page==='login'?'aria-current="page"':''?> href="?page=login">Masuk</a></li><li><a class="nav-cta <?=$page==='register'?'is-active':''?>" <?=$page==='register'?'aria-current="page"':''?> href="?page=register">Daftar</a></li>
            <?php endif; ?>
            <li><button class="theme-toggle" type="button" data-theme-toggle aria-label="Ganti tema" title="Ganti tema"><span class="icon-sun" aria-hidden="true">☀</span><span class="icon-moon" aria-hidden="true">☾</span></button></li>
        </ul></nav>
    </div>
</header>
<main id="main-content" class="container main-content" tabindex="-1"><?php include __DIR__ . '/src/views/' . $page . '.php'; ?></main>
<footer class="footer"><div class="container footer-inner"><div><strong>MyThesis</strong><small>Platform bimbingan skripsi yang terstruktur.</small></div><small>&copy; <?=date('Y')?> MyThesis &middot; Bantuan melalui administrator.</small></div></footer>
<script src="public/js/script.js?v=<?=$assetVersion?>" defer></script>
</body>
</html>
