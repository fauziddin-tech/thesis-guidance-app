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

// Mengecek apakah migrasi periode akademik sudah dijalankan agar aplikasi tetap berjalan sebelum migrasi.
function academic_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    $ready = true;
    foreach ([['bimbingan', 'periode_id'], ['users', 'nim']] as $check) {
        $result = $conn->query("SHOW COLUMNS FROM `" . $check[0] . "` LIKE '" . $check[1] . "'");
        $found = $result && $result->num_rows > 0;
        if ($result) $result->free();
        if (!$found) {$ready = false;break;}
    }
    return $ready;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $result = $conn->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE '" . $conn->real_escape_string($column) . "'");
    $cache[$key] = $result && $result->num_rows > 0;
    if ($result) $result->free();
    return $cache[$key];
}

function tanggal_id(?string $value, bool $withTime = false): string
{
    if (!$value) return '—';
    $timestamp = strtotime($value);
    if (!$timestamp) return '—';
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $text = date('j', $timestamp) . ' ' . $months[(int)date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    return $withTime ? $text . ', ' . date('H:i', $timestamp) : $text;
}

function status_label(string $status): string
{
    $labels = ['menunggu_review' => 'Menunggu review', 'direvisi' => 'Perlu revisi', 'disetujui' => 'Disetujui', 'draft' => 'Draf', 'aktif' => 'Aktif', 'selesai' => 'Selesai', 'ditangguhkan' => 'Ditangguhkan'];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host)) $host = 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $path . '/';
}

function period_label(?array $period): string
{
    if (!$period || empty($period['tahun_ajaran'])) return 'Belum ditentukan';
    return $period['tahun_ajaran'] . ' ' . ucfirst((string)$period['semester']);
}

// Usulan periode berdasarkan tanggal: Agustus–Januari = ganjil, Februari–Juli = genap.
function suggested_period(): array
{
    $year = (int)date('Y');
    $month = (int)date('n');
    if ($month >= 8) return [$year . '/' . ($year + 1), 'ganjil'];
    if ($month === 1) return [($year - 1) . '/' . $year, 'ganjil'];
    return [($year - 1) . '/' . $year, 'genap'];
}

function valid_nim(string $nim): bool
{
    return (bool)preg_match('/^[A-Za-z0-9.-]{5,30}$/', $nim);
}

function angkatan_options(): array
{
    $year = (int)date('Y');
    return range($year, $year - 15);
}

function prodi_label(array $row, string $nameKey = 'nama', string $levelKey = 'jenjang'): string
{
    if (empty($row[$nameKey])) return '';
    return trim((string)($row[$levelKey] ?? '') . ' ' . (string)$row[$nameKey]);
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

if (isset($_GET['action']) && $_GET['action'] === 'download_revision') {
    if (!isset($_SESSION['user'])) {http_response_code(401);exit('Silakan masuk untuk mengunduh lampiran revisi.');}
    $revisionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$revisionId) {http_response_code(400);exit('Lampiran revisi tidak valid.');}
    $stmt = $conn->prepare('SELECT r.file_path,bs.nama_bab,bs.versi,b.mahasiswa_id,b.dosen_id FROM revisi r JOIN bab_skripsi bs ON r.bab_id=bs.id JOIN bimbingan b ON bs.bimbingan_id=b.id WHERE r.id=? LIMIT 1');
    $stmt->bind_param('i', $revisionId);$stmt->execute();$revision = $stmt->get_result()->fetch_assoc();$stmt->close();
    $currentUser = $_SESSION['user'];
    $isAllowed = $revision && ($currentUser['role'] === 'admin' || ($currentUser['role'] === 'mahasiswa' && (int)$revision['mahasiswa_id'] === (int)$currentUser['id']) || ($currentUser['role'] === 'dosen' && (int)$revision['dosen_id'] === (int)$currentUser['id']));
    $storageRoot = realpath(__DIR__ . '/storage/uploads');
    $filePath = $revision && $revision['file_path'] ? realpath(__DIR__ . '/' . ltrim($revision['file_path'], '/')) : false;
    if (!$isAllowed || !$storageRoot || !$filePath || strpos($filePath, $storageRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {http_response_code(404);exit('Lampiran revisi tidak ditemukan atau tidak dapat diakses.');}
    $safeName = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $revision['nama_bab']), '-');
    $revisionExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'doc' ? 'doc' : 'docx';
    header('Content-Type: ' . ($revisionExtension === 'doc' ? 'application/msword' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: attachment; filename="Revisi-' . $safeName . '-v' . (int)$revision['versi'] . '.' . $revisionExtension . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'kartu') {
    require __DIR__ . '/src/views/kartu.php';
    exit;
}

if ($page === 'verifikasi') {
    require __DIR__ . '/src/views/verifikasi.php';
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
    <link rel="icon" type="image/png" href="logo.png<?=is_file(__DIR__ . '/logo.png') ? '?v=' . filemtime(__DIR__ . '/logo.png') : ''?>">
    <link rel="apple-touch-icon" href="logo.png">
    <link rel="stylesheet" href="public/css/style.css?v=<?=$assetVersion?>">
</head>
<body data-page="<?=htmlspecialchars($page, ENT_QUOTES, 'UTF-8')?>">
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<header class="navbar">
    <div class="container nav-inner">
        <a class="brand" href="?page=home" aria-label="MyThesis, kembali ke beranda"><?php if(is_file(__DIR__ . '/logo.png')): ?><span class="brand-mark brand-mark-image" aria-hidden="true"><img src="logo.png?v=<?=filemtime(__DIR__ . '/logo.png')?>" alt="" width="40" height="40"></span><?php else: ?><span class="brand-mark" aria-hidden="true">M</span><?php endif; ?><span class="brand-copy"><strong>MyThesis</strong><small>Ruang kerja skripsi</small></span></a>
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
