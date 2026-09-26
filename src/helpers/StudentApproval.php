<?php
/**
 * Penerimaan pendaftaran mahasiswa oleh dosen dan penghapusan bimbingan yang salah/ganda
 * (kolom bimbingan.menunggu_persetujuan, migrasi 20260929_student_approval.sql).
 * - Pendaftaran mandiri (halaman Daftar atau form di dashboard) menunggu diterima Pembimbing 1.
 *   Selama itu judul belum dapat direview dan pertemuan belum dapat dicatat.
 * - Pembimbing 1 dapat menerima, atau menolak sekaligus menghapus pendaftaran tersebut.
 * - Pembimbing 1 dan admin dapat menghapus bimbingan yang belum memiliki dokumen bab maupun
 *   pertemuan terkonfirmasi (mis. akun ganda). Akun mahasiswa ikut dihapus bila tidak memiliki
 *   bimbingan lain, sehingga tidak ada akun yatim.
 * Semua fungsi aman dipanggil sebelum migrasi dijalankan.
 */

function approval_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready === null) $ready = column_exists($db, 'bimbingan', 'menunggu_persetujuan');
    return $ready;
}

function approval_table_exists(mysqli $db, string $table): bool
{
    static $cache = [];
    if (!array_key_exists($table, $cache)) {
        $result = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        $cache[$table] = $result && $result->num_rows > 0;
    }
    return $cache[$table];
}

function approval_is_pending(array $guidance): bool
{
    return !empty($guidance['menunggu_persetujuan']);
}

function approval_guidance_pending(mysqli $db, int $guidanceId): bool
{
    if (!approval_ready($db)) return false;
    $stmt = $db->prepare('SELECT menunggu_persetujuan FROM bimbingan WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $guidanceId);$stmt->execute();$row = $stmt->get_result()->fetch_assoc();$stmt->close();
    return $row && (int)$row['menunggu_persetujuan'] === 1;
}

/** Pendaftaran yang menunggu diterima dosen ini, beserta kemungkinan akun ganda. */
function approval_pending_for_lecturer(mysqli $db, int $lecturerId): array
{
    if (!approval_ready($db)) return [];
    $academic = column_exists($db, 'users', 'nim');
    $select = $academic ? ',u.nim,u.angkatan,ps.nama AS prodi_nama,ps.jenjang AS prodi_jenjang' : ',NULL AS nim,NULL AS angkatan,NULL AS prodi_nama,NULL AS prodi_jenjang';
    $join = $academic ? ' LEFT JOIN program_studi ps ON ps.id=u.prodi_id' : '';
    $stmt = $db->prepare('SELECT b.id,b.judul_skripsi,b.created_at,b.mahasiswa_id,u.nama_lengkap,u.email,u.no_telp' . $select . ' FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id' . $join . ' WHERE b.dosen_id=? AND b.menunggu_persetujuan=1 ORDER BY b.created_at');
    $stmt->bind_param('i', $lecturerId);$stmt->execute();$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    foreach ($rows as &$row) $row['kemungkinan_ganda'] = approval_duplicates($db, (int)$row['mahasiswa_id'], (string)$row['nama_lengkap'], (string)($row['no_telp'] ?? ''));
    unset($row);
    return $rows;
}

/** Akun mahasiswa lain dengan nama sama (tanpa membedakan huruf besar/spasi) atau nomor telepon sama. */
function approval_duplicates(mysqli $db, int $studentId, string $name, string $phone): array
{
    $normalizedName = mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    $digits = preg_replace('/\D+/', '', $phone);
    $academic = column_exists($db, 'users', 'nim');
    $stmt = $db->prepare("SELECT u.id,u.nama_lengkap,u.email," . ($academic ? 'u.nim' : 'NULL AS nim') . ",u.no_telp,(SELECT d.nama_lengkap FROM bimbingan b JOIN users d ON d.id=b.dosen_id WHERE b.mahasiswa_id=u.id ORDER BY b.id DESC LIMIT 1) AS dosen_nama FROM users u WHERE u.role='mahasiswa' AND u.id<>? AND (REGEXP_REPLACE(LOWER(TRIM(u.nama_lengkap)),'[[:space:]]+',' ')=?" . (strlen($digits) >= 8 ? " OR REPLACE(REPLACE(REPLACE(u.no_telp,' ',''),'-',''),'+','') LIKE ?" : '') . ') LIMIT 5');
    if (strlen($digits) >= 8) {
        $phoneTail = '%' . substr($digits, -9);
        $stmt->bind_param('iss', $studentId, $normalizedName, $phoneTail);
    } else {
        $stmt->bind_param('is', $studentId, $normalizedName);
    }
    $stmt->execute();$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    return $rows;
}

/** Pembimbing 1 menerima pendaftaran mahasiswa. */
function approval_accept(mysqli $db, int $lecturerId, int $guidanceId): void
{
    if (!approval_ready($db)) throw new RuntimeException('Fitur penerimaan mahasiswa belum diaktifkan.');
    $stmt = $db->prepare('SELECT b.mahasiswa_id,b.judul_skripsi,u.nama_lengkap AS dosen_nama FROM bimbingan b JOIN users u ON u.id=b.dosen_id WHERE b.id=? AND b.dosen_id=? AND b.menunggu_persetujuan=1 LIMIT 1');
    $stmt->bind_param('ii', $guidanceId, $lecturerId);$stmt->execute();$guidance = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$guidance) throw new RuntimeException('Pendaftaran tidak ditemukan atau sudah ditanggapi.');
    $stmt = $db->prepare('UPDATE bimbingan SET menunggu_persetujuan=0 WHERE id=? AND menunggu_persetujuan=1');
    $stmt->bind_param('i', $guidanceId);$stmt->execute();$changed = $stmt->affected_rows > 0;$stmt->close();
    if (!$changed) throw new RuntimeException('Pendaftaran ini sudah ditanggapi.');
    notify_user($db, (int)$guidance['mahasiswa_id'], 'bimbingan_baru', $guidance['dosen_nama'] . ' menerima Anda sebagai mahasiswa bimbingan. Pengajuan judul Anda akan segera direview.',
        '?page=dashboard#bimbingan-saya', 'Pendaftaran bimbingan diterima', 'Pendaftaran bimbingan Anda diterima', 'Lihat Bimbingan');
}

/** Alasan bimbingan tidak boleh dihapus, atau '' bila boleh. */
function guidance_delete_blocker(mysqli $db, int $guidanceId): string
{
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM bab_skripsi WHERE bimbingan_id=?');
    $stmt->bind_param('i', $guidanceId);$stmt->execute();$documents = (int)$stmt->get_result()->fetch_assoc()['total'];$stmt->close();
    if ($documents > 0) return 'Bimbingan ini sudah memiliki ' . $documents . ' dokumen bab sehingga tidak dapat dihapus. Ubah statusnya menjadi Ditangguhkan bila tidak dilanjutkan.';
    if (approval_table_exists($db, 'log_pertemuan')) {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM log_pertemuan WHERE bimbingan_id=? AND status='dikonfirmasi'");
        $stmt->bind_param('i', $guidanceId);$stmt->execute();$meetings = (int)$stmt->get_result()->fetch_assoc()['total'];$stmt->close();
        if ($meetings > 0) return 'Bimbingan ini sudah memiliki pertemuan terkonfirmasi sehingga tidak dapat dihapus.';
    }
    return '';
}

/** Daftar id bimbingan (dari $guidanceIds) yang boleh dihapus: tanpa dokumen bab dan tanpa pertemuan terkonfirmasi. */
function guidance_deletable_ids(mysqli $db, array $guidanceIds): array
{
    $guidanceIds = array_values(array_unique(array_filter(array_map('intval', $guidanceIds))));
    if (!$guidanceIds) return [];
    $list = implode(',', $guidanceIds);
    $blocked = [];
    $result = $db->query("SELECT DISTINCT bimbingan_id FROM bab_skripsi WHERE bimbingan_id IN ($list)");
    while ($result && ($row = $result->fetch_row())) $blocked[(int)$row[0]] = true;
    if (approval_table_exists($db, 'log_pertemuan')) {
        $result = $db->query("SELECT DISTINCT bimbingan_id FROM log_pertemuan WHERE status='dikonfirmasi' AND bimbingan_id IN ($list)");
        while ($result && ($row = $result->fetch_row())) $blocked[(int)$row[0]] = true;
    }
    return array_fill_keys(array_values(array_filter($guidanceIds, function ($id) use ($blocked) {return !isset($blocked[$id]);})), true);
}

/**
 * Menghapus bimbingan beserta data turunannya. $actor: ['id','role','nama_lengkap'].
 * Dosen hanya dapat menghapus bimbingan tempat ia menjadi Pembimbing 1. Akun mahasiswa ikut dihapus
 * bila tidak memiliki bimbingan lain. Mengembalikan ['akun_dihapus' => bool, 'mahasiswa' => nama].
 */
function guidance_delete(mysqli $db, int $guidanceId, array $actor, string $reason = '', bool $isRejection = false): array
{
    $stmt = $db->prepare('SELECT b.*,u.nama_lengkap AS mahasiswa_nama,u.email AS mahasiswa_email FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.id=? LIMIT 1');
    $stmt->bind_param('i', $guidanceId);$stmt->execute();$guidance = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$guidance) throw new RuntimeException('Bimbingan tidak ditemukan.');
    if ($actor['role'] === 'dosen' && (int)$guidance['dosen_id'] !== (int)$actor['id']) throw new RuntimeException('Hanya Pembimbing 1 yang dapat menghapus bimbingan ini.');
    if (!in_array($actor['role'], ['dosen', 'admin'], true)) throw new RuntimeException('Tindakan tidak diizinkan.');
    if ($isRejection && !approval_is_pending($guidance)) throw new RuntimeException('Pendaftaran ini sudah diterima. Gunakan tombol Hapus bila bimbingan memang salah atau ganda.');
    $blocker = guidance_delete_blocker($db, $guidanceId);
    if ($blocker !== '') throw new RuntimeException($blocker);
    $studentId = (int)$guidance['mahasiswa_id'];

    // Beritahu mahasiswa lebih dulu (email tetap terkirim walaupun akunnya ikut dihapus).
    $body = $isRejection
        ? 'Pendaftaran bimbingan skripsi Anda dengan judul "' . $guidance['judul_skripsi'] . '" tidak diterima oleh dosen pembimbing.'
        : 'Bimbingan skripsi dengan judul "' . $guidance['judul_skripsi'] . '" dihapus oleh ' . ($actor['role'] === 'admin' ? 'administrator' : 'dosen pembimbing') . '.';
    if ($reason !== '') $body .= "\n\nAlasan: " . $reason;
    $body .= "\n\nJika ini keliru, hubungi dosen pembimbing atau administrator program studi.";
    notify_user($db, $studentId, 'bimbingan_baru', $body, '?page=dashboard', $isRejection ? 'Pendaftaran bimbingan tidak diterima' : 'Bimbingan skripsi dihapus', $isRejection ? 'Pendaftaran bimbingan tidak diterima' : 'Bimbingan skripsi dihapus', 'Buka MyThesis');

    $db->begin_transaction();
    try {
        $run = function (string $sql) use ($db): void {if ($db->query($sql) === false) throw new RuntimeException('Data belum dapat dihapus.');};
        if (approval_table_exists($db, 'mythesis_review_dosen')) $run("DELETE FROM mythesis_review_dosen WHERE jenis='judul' AND objek_id=$guidanceId");
        foreach (['mythesis_pembimbing2', 'konsultasi', 'log_pertemuan', 'jadwal_seminar'] as $table) {
            if (approval_table_exists($db, $table)) $run("DELETE FROM `$table` WHERE bimbingan_id=$guidanceId");
        }
        $run("DELETE FROM bimbingan WHERE id=$guidanceId");
        $remaining = (int)$db->query("SELECT COUNT(*) AS total FROM bimbingan WHERE mahasiswa_id=$studentId")->fetch_assoc()['total'];
        $accountDeleted = false;
        if ($remaining === 0) {
            foreach (['notifikasi' => 'user_id', 'password_resets' => 'user_id'] as $table => $column) {
                if (approval_table_exists($db, $table)) $run("DELETE FROM `$table` WHERE `$column`=$studentId");
            }
            $run("DELETE FROM users WHERE id=$studentId AND role='mahasiswa'");
            $accountDeleted = true;
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error instanceof RuntimeException ? $error : new RuntimeException('Data belum dapat dihapus.');
    }
    if ($accountDeleted) foreach (glob(dirname(__DIR__, 2) . '/storage/avatars/user-' . $studentId . '.*') ?: [] as $avatar) @unlink($avatar);
    error_log('MyThesis: bimbingan #' . $guidanceId . ' (' . $guidance['mahasiswa_nama'] . ') dihapus oleh ' . $actor['role'] . ' #' . (int)$actor['id'] . ($accountDeleted ? ', akun mahasiswa ikut dihapus' : '') . ($reason !== '' ? '. Alasan: ' . $reason : ''));
    return ['akun_dihapus' => $accountDeleted, 'mahasiswa' => (string)$guidance['mahasiswa_nama']];
}
