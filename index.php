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
require_once __DIR__ . '/src/helpers/Email.php';
require_once __DIR__ . '/src/helpers/Pembimbing2.php';
require_once __DIR__ . '/src/helpers/TitleRevision.php';
require_once __DIR__ . '/src/helpers/RateLimit.php';

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
    $labels = ['menunggu_review' => 'Menunggu review', 'direvisi' => 'Perlu revisi', 'disetujui' => 'Disetujui', 'draft' => 'Draf', 'pengajuan_judul' => 'Pengajuan judul', 'revisi_judul' => 'Revisi judul', 'aktif' => 'Aktif', 'selesai' => 'Selesai', 'ditangguhkan' => 'Ditangguhkan'];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function app_base_url(): string
{
    if (defined('APP_URL') && APP_URL !== '') return rtrim(APP_URL, '/') . '/';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host)) $host = 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $path . '/';
}

function lecturer_options(mysqli $conn): array
{
    $result = $conn->query("SELECT id,nama_lengkap FROM users WHERE role='dosen' ORDER BY nama_lengkap");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function active_period_id(mysqli $conn): ?int
{
    if (!academic_ready($conn)) return null;
    $result = $conn->query('SELECT id FROM periode_akademik WHERE is_aktif=1 ORDER BY id DESC LIMIT 1');
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? (int)$row['id'] : null;
}

// Membuat relasi bimbingan yang dipilih mahasiswa sendiri, lalu memberi notifikasi kepada dosen.
function create_student_guidance(mysqli $conn, int $studentId, string $studentName, int $lecturerId, string $title, bool $sendEmail = true): bool
{
    if (academic_ready($conn)) {
        $periodId = active_period_id($conn);
        $initialStatus = initial_guidance_status($conn);
        $stmt = $conn->prepare("INSERT INTO bimbingan(mahasiswa_id,dosen_id,periode_id,judul_skripsi,status) VALUES(?,?,?,?,?)");
        $stmt->bind_param('iiiss', $studentId, $lecturerId, $periodId, $title, $initialStatus);
    } else {
        $initialStatus = initial_guidance_status($conn);
        $stmt = $conn->prepare("INSERT INTO bimbingan(mahasiswa_id,dosen_id,judul_skripsi,status) VALUES(?,?,?,?)");
        $stmt->bind_param('iiss', $studentId, $lecturerId, $title, $initialStatus);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) return false;
    $message = $studentName . ' memilih Anda sebagai dosen pembimbing dan mengajukan judul: ' . $title . '.';
    $link = '?page=dashboard#mahasiswa-bimbingan';
    $stmt = $conn->prepare("INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,'bimbingan_baru',?,?)");
    $stmt->bind_param('iss', $lecturerId, $message, $link);
    $stmt->execute();
    $stmt->close();
    if ($sendEmail) send_guidance_selected_email($conn, $lecturerId, $studentName, $title);
    return true;
}

// File dokumen boleh berada di storage/uploads (versi sekarang) atau uploads/ (unggahan era aplikasi lama).
function document_file_path(string $relativePath): ?string
{
    $filePath = realpath(__DIR__ . '/' . ltrim($relativePath, '/'));
    if (!$filePath || !is_file($filePath)) return null;
    foreach (['/storage/uploads', '/uploads'] as $folder) {
        $root = realpath(__DIR__ . $folder);
        if ($root && strpos($filePath, $root . DIRECTORY_SEPARATOR) === 0) return $filePath;
    }
    return null;
}

// Dosen termasuk tim pembimbing (Pembimbing 1 atau 2) dari bimbingan tertentu.
function lecturer_in_guidance(mysqli $conn, int $guidanceId, int $lecturerId): bool
{
    return $guidanceId > 0 && $lecturerId > 0 && p2_is_member($conn, $guidanceId, $lecturerId);
}

function send_guidance_selected_email(mysqli $conn, int $lecturerId, string $studentName, string $title): bool
{
    return send_user_email($conn, $lecturerId, 'Mahasiswa bimbingan baru: ' . $studentName, 'Mahasiswa bimbingan baru', $studentName . ' memilih Anda sebagai dosen pembimbing di MyThesis dan mengajukan judul skripsi untuk Anda tinjau.' . "\n\n" . 'Judul skripsi: ' . $title, '?page=dashboard#mahasiswa-bimbingan', 'Lihat Mahasiswa Bimbingan');
}

function valid_thesis_title(string $title): bool
{
    $length = mb_strlen($title);
    return $length >= 10 && $length <= 255;
}

// Alat paginasi seragam (cari + 10/20/50 per halaman). Dipakai bersama atribut data-paginate di public/js/script.js.
function paginate_tools(string $id, string $placeholder, string $noun): string
{
    return '<div class="student-list-tools"><div class="student-search"><label class="sr-only" for="' . h($id) . '">Cari ' . h($noun) . '</label><input id="' . h($id) . '" type="search" placeholder="' . h($placeholder) . '" data-paginate-search></div>'
        . '<label class="student-page-size">Tampilkan <select data-paginate-size aria-label="Jumlah ' . h($noun) . ' per halaman"><option value="10" selected>10</option><option value="20">20</option><option value="50">50</option></select> per halaman</label></div>';
}

function paginate_footer(string $noun): string
{
    return '<div class="student-list-empty" data-paginate-empty hidden>Tidak ada ' . h($noun) . ' yang sesuai dengan pencarian.</div>'
        . '<div class="student-pagination"><span data-paginate-info></span><nav aria-label="Navigasi halaman ' . h($noun) . '" data-paginate-nav></nav></div>';
}

// Nomor bab (1–5) dari nama bab: "Bab 1", "BAB II - Kajian Teori", "bab 3" → 1, 2, 3. Selain itu 0.
function chapter_number(string $name): int
{
    if (!preg_match('/^\s*bab\s*([1-5]|iv|v|i{1,3})\b/i', $name, $match)) return 0;
    $roman = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5];
    $value = strtolower($match[1]);
    return ctype_digit($value) ? (int)$value : $roman[$value];
}

/**
 * Aturan unggah bab berurutan untuk satu bimbingan:
 * - satu bab dalam satu waktu; Bab n baru terbuka setelah Bab n-1 disetujui (seluruh pembimbing);
 * - versi baru sebuah bab hanya boleh setelah dosen meminta revisi (status "direvisi");
 * - selama versi terakhir masih menunggu review, unggahan dikunci.
 * Mengembalikan ['bab' => nomor yang boleh diunggah atau 0, 'nama' => nama bab, 'versi' => versi berikutnya, 'alasan' => penjelasan].
 */
function chapter_upload_state(mysqli $conn, int $guidanceId): array
{
    $stmt = $conn->prepare('SELECT id,nama_bab,versi,status FROM bab_skripsi WHERE bimbingan_id=? ORDER BY versi,id');
    $stmt->bind_param('i', $guidanceId);$stmt->execute();$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    $latest = [];$maxVersion = [];
    foreach ($rows as $row) {
        $number = chapter_number((string)$row['nama_bab']);
        if ($number === 0) continue;
        $maxVersion[$number] = max($maxVersion[$number] ?? 0, (int)$row['versi']);
        $latest[$number] = $row;
    }
    for ($number = 1; $number <= 5; $number++) {
        $row = $latest[$number] ?? null;
        if (!$row) return ['bab' => $number, 'nama' => 'Bab ' . $number, 'versi' => 1, 'alasan' => 'Bab ' . $number . ' siap diunggah.'];
        if ($row['status'] === 'disetujui') continue;
        if ($row['status'] === 'direvisi') return ['bab' => $number, 'nama' => (string)$row['nama_bab'], 'versi' => $maxVersion[$number] + 1, 'alasan' => 'Unggah perbaikan ' . $row['nama_bab'] . ' sesuai catatan revisi dosen.'];
        return ['bab' => 0, 'nama' => '', 'versi' => 0, 'alasan' => $row['nama_bab'] . ' versi ' . (int)$row['versi'] . ' masih menunggu review dosen. Unggahan berikutnya dibuka setelah dosen menyetujui atau meminta revisi.'];
    }
    return ['bab' => 0, 'nama' => '', 'versi' => 0, 'alasan' => 'Bab 1 sampai Bab 5 sudah disetujui seluruhnya.'];
}

// Versi terakhir tiap bab (1–5) untuk banyak bimbingan sekaligus: [bimbingan_id => [nomor => baris|null]].
function chapter_progress_map(mysqli $conn, array $guidanceIds): array
{
    $guidanceIds = array_values(array_unique(array_filter(array_map('intval', $guidanceIds))));
    $map = [];
    foreach ($guidanceIds as $id) $map[$id] = array_fill(1, 5, null);
    if (!$guidanceIds) return $map;
    $result = $conn->query('SELECT id,bimbingan_id,nama_bab,versi,status FROM bab_skripsi WHERE bimbingan_id IN (' . implode(',', $guidanceIds) . ') ORDER BY versi,id');
    while ($result && ($row = $result->fetch_assoc())) {
        $number = chapter_number((string)$row['nama_bab']);
        if ($number) $map[(int)$row['bimbingan_id']][$number] = $row;
    }
    return $map;
}

/**
 * Langkah progres skripsi: Judul lalu Bab 1–5. Status tiap langkah:
 * selesai | review | revisi | terbuka (giliran berikutnya) | terkunci.
 */
function thesis_progress_steps(array $guidance, array $chapters): array
{
    $titleStatus = (string)$guidance['status'];
    $steps = [['label' => 'Judul', 'status' => $titleStatus === 'pengajuan_judul' ? 'review' : ($titleStatus === 'revisi_judul' ? 'revisi' : 'selesai'), 'versi' => 0]];
    $open = in_array($titleStatus, ['aktif', 'selesai'], true);
    for ($number = 1; $number <= 5; $number++) {
        $row = $chapters[$number] ?? null;
        $state = !$row ? ($open ? 'terbuka' : 'terkunci') : ['disetujui' => 'selesai', 'menunggu_review' => 'review', 'direvisi' => 'revisi'][$row['status']] ?? 'review';
        if (!$row || $state !== 'selesai') $open = false;
        $steps[] = ['label' => 'Bab ' . $number, 'status' => $state, 'versi' => $row ? (int)$row['versi'] : 0];
    }
    return $steps;
}

function progress_status_label(string $status): string
{
    return ['selesai' => 'Disetujui', 'review' => 'Menunggu review', 'revisi' => 'Perlu revisi', 'terbuka' => 'Siap diunggah', 'terkunci' => 'Belum terbuka'][$status] ?? $status;
}

// Tampilan ringkas (titik) untuk tabel dosen/admin.
function progress_dots(array $steps): string
{
    $approved = count(array_filter(array_slice($steps, 1), function ($step) {return $step['status'] === 'selesai';}));
    $html = '<span class="progress-dots" aria-label="Progres: ' . $approved . ' dari 5 bab disetujui">';
    foreach ($steps as $step) $html .= '<span class="dot dot-' . h($step['status']) . '" title="' . h($step['label'] . ': ' . progress_status_label($step['status']) . ($step['versi'] > 1 ? ' (versi ' . $step['versi'] . ')' : '')) . '"></span>';
    return $html . '<small>' . $approved . '/5 bab disetujui</small></span>';
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
$allowedPages = ['home', 'login', 'register', 'dashboard', 'pemeriksaan', 'forgot-password', 'reset-password', 'notifikasi'];
const APP_VERSION = '1.7.0';

// Pratinjau akun mahasiswa oleh admin/dosen: harus berjalan sebelum semua aksi dan halaman lain.
require __DIR__ . '/src/helpers/StudentPreview.php';

// Notifikasi: buka (tandai dibaca lalu arahkan ke tautannya), tandai dibaca, dan tandai semua dibaca.
if (isset($_GET['action']) && $_GET['action'] === 'buka_notifikasi' && isset($_SESSION['user']) && empty($_SESSION['student_preview'])) {
    $notificationId = (int)($_GET['id'] ?? 0);
    $currentUserId = (int)$_SESSION['user']['id'];
    $stmt = $conn->prepare('SELECT link FROM notifikasi WHERE id=? AND user_id=? LIMIT 1');
    $stmt->bind_param('ii', $notificationId, $currentUserId);$stmt->execute();$notification = $stmt->get_result()->fetch_assoc();$stmt->close();
    if ($notification) {
        $stmt = $conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $notificationId, $currentUserId);$stmt->execute();$stmt->close();
    }
    $target = (string)($notification['link'] ?? '');
    // Hanya tautan internal aplikasi agar tidak dapat dipakai mengalihkan ke situs lain.
    header('Location: ' . (preg_match('/^\?page=[a-z-]+[^\s]*$/', $target) ? $target : '?page=notifikasi'));
    exit;
}
if ($page === 'notifikasi' && isset($_SESSION['user']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentUserId = (int)$_SESSION['user']['id'];
    if (!verify_csrf()) set_flash('danger', 'Sesi formulir telah berakhir. Muat ulang halaman dan coba kembali.');
    elseif (($_POST['action'] ?? '') === 'tandai_semua') {
        $stmt = $conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE user_id=? AND dibaca=0');$stmt->bind_param('i', $currentUserId);$stmt->execute();$stmt->close();
        set_flash('success', 'Semua notifikasi ditandai sudah dibaca.');
    } elseif (($_POST['action'] ?? '') === 'tandai_dibaca') {
        $notificationId = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE id=? AND user_id=?');$stmt->bind_param('ii', $notificationId, $currentUserId);$stmt->execute();$stmt->close();
    }
    header('Location: ?page=notifikasi');
    exit;
}

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
    $requestedUserId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: (int)($_SESSION['user']['id'] ?? 0);
    // Foto dosen yang ditampilkan di beranda boleh dilihat tanpa masuk.
    $isPublicLecturer = false;
    if ($requestedUserId && column_exists($conn, 'users', 'tampil_beranda')) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen' AND tampil_beranda=1 LIMIT 1");
        $stmt->bind_param('i', $requestedUserId);
        $stmt->execute();
        $isPublicLecturer = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$isPublicLecturer && !isset($_SESSION['user'])) {
        http_response_code(401);
        exit('Silakan masuk untuk melihat foto profil.');
    }

    $currentUser = $_SESSION['user'] ?? ['id' => 0, 'role' => ''];
    $currentUserId = (int)$currentUser['id'];
    $isAllowed = $isPublicLecturer || $requestedUserId === $currentUserId || $currentUser['role'] === 'admin';

    if (!$isAllowed && $currentUser['role'] === 'dosen') {
        $stmt = $conn->prepare('SELECT b.id FROM bimbingan b WHERE ' . p2_match($conn, 'b') . ' AND b.mahasiswa_id=? LIMIT 1');
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
    header('Cache-Control: ' . ($isPublicLecturer ? 'public' : 'private') . ', max-age=3600');
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

    $stmt = $conn->prepare('SELECT bs.file_path, bs.nama_bab, bs.versi, b.id AS bimbingan_id, b.mahasiswa_id, b.dosen_id FROM bab_skripsi bs JOIN bimbingan b ON bs.bimbingan_id=b.id WHERE bs.id=? LIMIT 1');
    $stmt->bind_param('i', $documentId);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentUser = $_SESSION['user'];
    $isAllowed = $document && (
        $currentUser['role'] === 'admin' ||
        ($currentUser['role'] === 'mahasiswa' && (int)$document['mahasiswa_id'] === (int)$currentUser['id']) ||
        ($currentUser['role'] === 'dosen' && lecturer_in_guidance($conn, (int)$document['bimbingan_id'], (int)$currentUser['id']))
    );
    $filePath = $document ? document_file_path((string)$document['file_path']) : null;

    if (!$isAllowed || !$filePath) {
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
    $stmt = $conn->prepare('SELECT r.file_path,bs.nama_bab,bs.versi,b.id AS bimbingan_id,b.mahasiswa_id,b.dosen_id FROM revisi r JOIN bab_skripsi bs ON r.bab_id=bs.id JOIN bimbingan b ON bs.bimbingan_id=b.id WHERE r.id=? LIMIT 1');
    $stmt->bind_param('i', $revisionId);$stmt->execute();$revision = $stmt->get_result()->fetch_assoc();$stmt->close();
    $currentUser = $_SESSION['user'];
    $isAllowed = $revision && ($currentUser['role'] === 'admin' || ($currentUser['role'] === 'mahasiswa' && (int)$revision['mahasiswa_id'] === (int)$currentUser['id']) || ($currentUser['role'] === 'dosen' && lecturer_in_guidance($conn, (int)$revision['bimbingan_id'], (int)$currentUser['id'])));
    $filePath = $revision && $revision['file_path'] ? document_file_path((string)$revision['file_path']) : null;
    if (!$isAllowed || !$filePath) {http_response_code(404);exit('Lampiran revisi tidak ditemukan atau tidak dapat diakses.');}
    $safeName = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $revision['nama_bab']), '-');
    $revisionExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $revisionMimes = ['doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pdf' => 'application/pdf'];
    if (!isset($revisionMimes[$revisionExtension])) $revisionExtension = 'docx';
    header('Content-Type: ' . $revisionMimes[$revisionExtension]);
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
if (in_array($page, ['dashboard', 'pemeriksaan', 'notifikasi'], true) && !isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}
if ($page === 'pemeriksaan' && ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ?page=dashboard');
    exit;
}

if (in_array($page, ['login', 'register', 'forgot-password', 'reset-password'], true) && isset($_SESSION['user'])) {
    header('Location: ?page=dashboard');
    exit;
}

$pageTitles = ['home'=>'Beranda','login'=>'Masuk','register'=>'Pendaftaran','dashboard'=>'Dashboard','pemeriksaan'=>'Pemeriksaan Sistem','forgot-password'=>'Lupa Password','reset-password'=>'Atur Ulang Password','notifikasi'=>'Notifikasi'];
$unreadNotifications = 0;
if (isset($_SESSION['user']) && empty($_SESSION['student_preview'])) {
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifikasi WHERE user_id=? AND dibaca=0');
    if ($stmt) {$currentUserId = (int)$_SESSION['user']['id'];$stmt->bind_param('i', $currentUserId);$stmt->execute();$unreadNotifications = (int)$stmt->get_result()->fetch_assoc()['total'];$stmt->close();}
}
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
                <li><a class="<?=$page==='dashboard'?'is-active':''?>" <?=$page==='dashboard'?'aria-current="page"':''?> href="?page=dashboard">Dashboard</a></li><?php if (empty($_SESSION['student_preview'])): ?><li><a class="nav-notif <?=$page==='notifikasi'?'is-active':''?>" <?=$page==='notifikasi'?'aria-current="page"':''?> href="?page=notifikasi">Notifikasi<?php if($unreadNotifications): ?><span class="nav-badge" aria-label="<?=$unreadNotifications?> belum dibaca"><?=$unreadNotifications>99?'99+':$unreadNotifications?></span><?php endif; ?></a></li><?php endif; ?><?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?><li><a class="<?=$page==='pemeriksaan'?'is-active':''?>" <?=$page==='pemeriksaan'?'aria-current="page"':''?> href="?page=pemeriksaan">Pemeriksaan</a></li><?php endif; ?><li><a href="?page=logout">Keluar</a></li>
            <?php else: ?>
                <li><a class="<?=$page==='login'?'is-active':''?>" <?=$page==='login'?'aria-current="page"':''?> href="?page=login">Masuk</a></li><li><a class="nav-cta <?=$page==='register'?'is-active':''?>" <?=$page==='register'?'aria-current="page"':''?> href="?page=register">Daftar</a></li>
            <?php endif; ?>
            <li><button class="theme-toggle" type="button" data-theme-toggle aria-label="Ganti tema" title="Ganti tema"><span class="icon-sun" aria-hidden="true">☀</span><span class="icon-moon" aria-hidden="true">☾</span></button></li>
        </ul></nav>
    </div>
</header>
<?php if (!empty($_SESSION['student_preview'])): ?><div class="preview-banner" role="status"><div class="container preview-banner-inner"><span><strong>Mode pratinjau</strong> — Anda melihat MyThesis sebagai <strong><?=h($_SESSION['user']['nama_lengkap'] ?? '')?></strong>. Hanya lihat; berakhir otomatis pukul <?=date('H:i', (int)$_SESSION['student_preview']['expires_at'])?>.</span><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="stop_student_preview"><button class="btn btn-primary btn-small" type="submit">Kembali ke akun saya</button></form></div></div><?php endif; ?>
<main id="main-content" class="container main-content" tabindex="-1"><?php include __DIR__ . '/src/views/' . $page . '.php'; ?></main>
<footer class="footer"><div class="container footer-inner"><div><strong>MyThesis</strong><small>Platform bimbingan skripsi yang terstruktur.</small></div><small>&copy; <?=date('Y')?> MyThesis &middot; Bantuan melalui administrator.</small></div></footer>
<script src="public/js/script.js?v=<?=$assetVersion?>" defer></script>
</body>
</html>
