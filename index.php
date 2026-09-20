<?php
ob_start();
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/config/database.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    return $token !== '' && hash_equals(csrf_token(), $token);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

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

if (isset($_GET['action']) && $_GET['action'] === 'avatar') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        exit('Silakan masuk untuk melihat foto profil.');
    }

    $currentUser = $_SESSION['user'];
    $currentUserId = (int)$currentUser['id'];
    $requestedUserId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $requestedUserId = $requestedUserId ?: $currentUserId;
    $isAllowed = $requestedUserId === $currentUserId || $currentUser['role'] === 'admin';

    if (!$isAllowed && $currentUser['role'] === 'dosen') {
        $stmt = $conn->prepare('SELECT id FROM bimbingan WHERE dosen_id=? AND mahasiswa_id=? LIMIT 1');
        $stmt->bind_param('ii', $currentUserId, $requestedUserId);
        $stmt->execute();
        $isAllowed = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$isAllowed) {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke foto profil ini.');
    }

    $avatarDirectory = __DIR__ . '/storage/avatars';
    $avatarPath = false;
    $avatarMime = '';
    foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $extension => $mime) {
        $candidate = $avatarDirectory . '/user-' . $requestedUserId . '.' . $extension;
        if (is_file($candidate)) {
            $avatarPath = $candidate;
            $avatarMime = $mime;
            break;
        }
    }

    if (!$avatarPath) {
        http_response_code(404);
        exit('Foto profil belum tersedia.');
    }

    header('Content-Type: ' . $avatarMime);
    header('Content-Length: ' . filesize($avatarPath));
    header('Cache-Control: private, max-age=3600');
    readfile($avatarPath);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'download') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        exit('Silakan masuk untuk mengunduh dokumen.');
    }

    $documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$documentId) {
        http_response_code(400);
        exit('Dokumen tidak valid.');
    }

    $stmt = $conn->prepare('SELECT bs.file_path, bs.nama_bab, bs.versi, b.mahasiswa_id, b.dosen_id FROM bab_skripsi bs JOIN bimbingan b ON bs.bimbingan_id=b.id WHERE bs.id=? LIMIT 1');
    $stmt->bind_param('i', $documentId);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentUser = $_SESSION['user'];
    $isAllowed = $document && (
        $currentUser['role'] === 'admin' ||
        ($currentUser['role'] === 'mahasiswa' && (int)$document['mahasiswa_id'] === (int)$currentUser['id']) ||
        ($currentUser['role'] === 'dosen' && (int)$document['dosen_id'] === (int)$currentUser['id'])
    );
    $storageRoot = realpath(__DIR__ . '/storage/uploads');
    $filePath = $document ? realpath(__DIR__ . '/' . ltrim($document['file_path'], '/')) : false;

    if (!$isAllowed || !$storageRoot || !$filePath || strpos($filePath, $storageRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan atau tidak dapat diakses.');
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $document['nama_bab']);
    $downloadName = trim($safeName, '-') . '-v' . (int)$document['versi'] . '.' . $extension;
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
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
