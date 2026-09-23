<?php
/**
 * Pratinjau akun mahasiswa (hanya lihat, 15 menit) oleh admin atau dosen pembimbingnya.
 * Setiap sesi dicatat di tabel impersonation_logs. Selama pratinjau, semua permintaan yang
 * mengubah data ditolak dan hanya dashboard/beranda yang dapat dibuka.
 * Dijalankan dari index.php sebelum semua aksi dan halaman lain.
 */
const STUDENT_PREVIEW_SECONDS = 900;

function preview_account(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT id,username,email,role,nama_lengkap,no_telp FROM users WHERE id=?');
    $stmt->bind_param('i', $id);$stmt->execute();$row = $stmt->get_result()->fetch_assoc();$stmt->close();
    return $row ?: null;
}

function preview_allowed(mysqli $conn, array $actor, int $student): bool {
    if ($actor['role'] === 'admin') return true;
    if ($actor['role'] !== 'dosen') return false;
    $stmt = $conn->prepare('SELECT b.id FROM bimbingan b WHERE ' . p2_match($conn, 'b') . ' AND b.mahasiswa_id=? LIMIT 1');
    $stmt->bind_param('ii', $actor['id'], $student);$stmt->execute();$ok = (bool)$stmt->get_result()->fetch_assoc();$stmt->close();
    return $ok;
}

function preview_log_start(mysqli $conn, array $actor, array $student): int {
    if (!column_exists($conn, 'impersonation_logs', 'impersonator_id')) return 0;
    $ip = function_exists('client_ip') ? client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $stmt = $conn->prepare("INSERT INTO impersonation_logs(impersonator_id,impersonator_nama,impersonator_role,target_id,target_nama,mode,ip_address) VALUES(?,?,?,?,?,'view',?)");
    $stmt->bind_param('ississ', $actor['id'], $actor['nama_lengkap'], $actor['role'], $student['id'], $student['nama_lengkap'], $ip);
    $ok = $stmt->execute();$id = (int)$conn->insert_id;$stmt->close();
    return $ok ? $id : 0;
}

function preview_return(mysqli $conn, string $reason): void {
    $state = $_SESSION['student_preview'];
    if (!empty($state['log_id'])) {
        $stmt = $conn->prepare('UPDATE impersonation_logs SET ended_at=NOW() WHERE id=? AND ended_at IS NULL');
        $stmt->bind_param('i', $state['log_id']);$stmt->execute();$stmt->close();
    }
    $actor = preview_account($conn, (int)$state['actor_id']);
    unset($_SESSION['student_preview'], $_SESSION['user'], $_SESSION['csrf_token'], $_SESSION['flash']);
    session_regenerate_id(true);
    if ($actor && in_array($actor['role'], ['admin', 'dosen'], true)) {
        $_SESSION['user'] = $actor;
        set_flash($reason === 'returned' ? 'success' : 'warning', $reason === 'returned' ? 'Pratinjau selesai. Anda kembali ke akun sendiri.' : 'Pratinjau berakhir karena waktu habis atau akses berubah.');
    }
    header('Location: ?page=' . (isset($_SESSION['user']) ? 'dashboard' : 'login'));
    exit;
}

$previewAction = (string)($_POST['action'] ?? '');
if (!empty($_SESSION['student_preview'])) {
    $state = $_SESSION['student_preview'];
    $actor = preview_account($conn, (int)$state['actor_id']);
    $student = preview_account($conn, (int)$state['student_id']);
    if (time() >= $state['expires_at'] || !$actor || !$student || $student['role'] !== 'mahasiswa' || !preview_allowed($conn, $actor, (int)$state['student_id'])) preview_return($conn, 'expired_or_revoked');
    $_SESSION['user'] = $student;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $previewAction === 'stop_student_preview' && verify_csrf()) preview_return($conn, 'returned');
    if (($_GET['page'] ?? '') === 'logout') preview_return($conn, 'returned');
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        http_response_code(403);
        exit('Mode pratinjau hanya untuk melihat; perubahan data tidak diizinkan. Kembali ke dashboard dan klik "Kembali ke akun saya".');
    }
    $previewAllowedActions = ['', 'download', 'download_revision', 'kartu', 'avatar'];
    if (!in_array((string)($_GET['action'] ?? ''), $previewAllowedActions, true) || !in_array($_GET['page'] ?? 'dashboard', ['dashboard', 'home'], true)) {
        header('Location: ?page=dashboard');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $previewAction === 'start_student_preview') {
    if (!verify_csrf() || empty($_SESSION['user']['id'])) {http_response_code(403);exit('Permintaan tidak diizinkan.');}
    $actor = preview_account($conn, (int)$_SESSION['user']['id']);
    $student = preview_account($conn, (int)($_POST['student_id'] ?? 0));
    if (!$actor || !$student || $student['role'] !== 'mahasiswa' || !preview_allowed($conn, $actor, (int)$student['id'])) {http_response_code(403);exit('Mahasiswa ini bukan bagian dari kewenangan Anda.');}
    $logId = preview_log_start($conn, $actor, $student);
    session_regenerate_id(true);
    unset($_SESSION['csrf_token'], $_SESSION['flash']);
    $_SESSION['student_preview'] = ['actor_id' => (int)$actor['id'], 'actor_name' => $actor['nama_lengkap'], 'student_id' => (int)$student['id'], 'expires_at' => time() + STUDENT_PREVIEW_SECONDS, 'log_id' => $logId];
    $_SESSION['user'] = $student;
    header('Location: ?page=dashboard');
    exit;
}
