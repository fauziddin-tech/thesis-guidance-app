<?php
/**
 * Pengelolaan akun oleh administrator (kolom users.is_aktif, migrasi 20261003_user_active.sql).
 * - Dosen: Nonaktifkan (disarankan) atau Hapus.
 *   Menonaktifkan dosen melepas seluruh mahasiswa bimbingannya: bimbingan yang belum selesai menjadi
 *   Ditangguhkan, peran Pembimbing 2 dilepas, dan mahasiswa diberi notifikasi. Dokumen serta riwayat
 *   review tetap tersimpan. Admin kemudian memindahkan bimbingan ke dosen aktif lain.
 *   Hapus hanya untuk akun dosen yang belum pernah terlibat bimbingan (mis. akun salah ketik).
 * - Mahasiswa: Hapus permanen hanya untuk akun tanpa bimbingan; yang sudah selesai tetap tersimpan di Arsip.
 * Fungsi pembaca aman dipanggil sebelum migrasi dijalankan.
 */

function useradmin_ready(mysqli $db): bool
{
    return column_exists($db, 'users', 'is_aktif');
}

/** Fragmen SQL "akun aktif" untuk alias tabel users; kosong bila migrasi belum dijalankan. */
function user_active_sql(mysqli $db, string $alias = ''): string
{
    return useradmin_ready($db) ? ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'is_aktif=1' : '';
}

function user_is_active(mysqli $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $userId);$stmt->execute();$row = $stmt->get_result()->fetch_assoc();$stmt->close();
    return $row && (!array_key_exists('is_aktif', $row) || (int)$row['is_aktif'] === 1);
}

function useradmin_lecturer(mysqli $db, int $lecturerId): array
{
    $stmt = $db->prepare("SELECT id,nama_lengkap FROM users WHERE id=? AND role='dosen' LIMIT 1");
    $stmt->bind_param('i', $lecturerId);$stmt->execute();$row = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$row) throw new RuntimeException('Dosen tidak ditemukan.');
    return $row;
}

/** Nonaktifkan dosen dan lepaskan mahasiswa bimbingannya. Mengembalikan jumlah bimbingan yang dilepas. */
function lecturer_deactivate(mysqli $db, int $lecturerId): array
{
    if (!useradmin_ready($db)) throw new RuntimeException('Fitur nonaktifkan dosen belum diaktifkan. Jalankan migrasi 20261003_user_active.sql.');
    $lecturer = useradmin_lecturer($db, $lecturerId);
    $released = [];
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT b.id,b.mahasiswa_id,b.judul_skripsi FROM bimbingan b WHERE b.dosen_id=? AND b.status<>'selesai'");
        $stmt->bind_param('i', $lecturerId);$stmt->execute();$released = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
        $stmt = $db->prepare("UPDATE bimbingan SET status='ditangguhkan' WHERE dosen_id=? AND status<>'selesai'");
        $stmt->bind_param('i', $lecturerId);$stmt->execute();$stmt->close();
        $secondRoles = [];
        if (approval_table_exists($db, 'mythesis_pembimbing2')) {
            $stmt = $db->prepare('SELECT p.bimbingan_id,b.mahasiswa_id FROM mythesis_pembimbing2 p JOIN bimbingan b ON b.id=p.bimbingan_id WHERE p.dosen_id=?');
            $stmt->bind_param('i', $lecturerId);$stmt->execute();$secondRoles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
            $stmt = $db->prepare('DELETE FROM mythesis_pembimbing2 WHERE dosen_id=?');
            $stmt->bind_param('i', $lecturerId);$stmt->execute();$stmt->close();
        }
        $stmt = $db->prepare('UPDATE users SET is_aktif=0,tampil_beranda=0 WHERE id=?');
        if (!column_exists($db, 'users', 'tampil_beranda')) $stmt = $db->prepare('UPDATE users SET is_aktif=0 WHERE id=?');
        $stmt->bind_param('i', $lecturerId);$stmt->execute();$stmt->close();
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw new RuntimeException('Dosen belum dapat dinonaktifkan.');
    }
    foreach ($released as $guidance) {
        notify_user($db, (int)$guidance['mahasiswa_id'], 'bimbingan_baru', $lecturer['nama_lengkap'] . ' tidak lagi menjadi dosen pembimbing Anda. Bimbingan "' . $guidance['judul_skripsi'] . '" ditangguhkan sementara; dokumen dan riwayat Anda tetap aman. Administrator akan menetapkan pembimbing pengganti.', '?page=dashboard#bimbingan-saya', 'Dosen pembimbing diganti', 'Dosen pembimbing Anda tidak lagi aktif', 'Lihat Bimbingan');
    }
    foreach ($secondRoles as $guidance) {
        notify_user($db, (int)$guidance['mahasiswa_id'], 'bimbingan_baru', $lecturer['nama_lengkap'] . ' tidak lagi menjadi Pembimbing 2 Anda. Anda dapat memilih Pembimbing 2 kembali.', '?page=dashboard#pembimbing-dua', 'Pembimbing 2 dilepas', 'Pembimbing 2 dilepas dari bimbingan Anda', 'Lihat Bimbingan');
    }
    error_log('MyThesis: dosen #' . $lecturerId . ' (' . $lecturer['nama_lengkap'] . ') dinonaktifkan, ' . count($released) . ' bimbingan dilepas');
    return ['nama' => $lecturer['nama_lengkap'], 'dilepas' => count($released)];
}

function lecturer_activate(mysqli $db, int $lecturerId): string
{
    if (!useradmin_ready($db)) throw new RuntimeException('Fitur nonaktifkan dosen belum diaktifkan.');
    $lecturer = useradmin_lecturer($db, $lecturerId);
    $stmt = $db->prepare('UPDATE users SET is_aktif=1 WHERE id=?');
    $stmt->bind_param('i', $lecturerId);$stmt->execute();$stmt->close();
    return $lecturer['nama_lengkap'];
}

/** Pindahkan bimbingan ke dosen aktif lain (Pembimbing 1). Bimbingan Ditangguhkan kembali Aktif. */
function guidance_reassign(mysqli $db, int $guidanceId, int $lecturerId): array
{
    $stmt = $db->prepare('SELECT b.id,b.mahasiswa_id,b.dosen_id,b.status,b.judul_skripsi FROM bimbingan b WHERE b.id=? LIMIT 1');
    $stmt->bind_param('i', $guidanceId);$stmt->execute();$guidance = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$guidance) throw new RuntimeException('Bimbingan tidak ditemukan.');
    $stmt = $db->prepare("SELECT id,nama_lengkap FROM users WHERE id=? AND role='dosen'" . user_active_sql($db) . ' LIMIT 1');
    $stmt->bind_param('i', $lecturerId);$stmt->execute();$lecturer = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$lecturer) throw new RuntimeException('Pilih dosen aktif sebagai pembimbing pengganti.');
    if ((int)$guidance['dosen_id'] === $lecturerId) throw new RuntimeException('Dosen tersebut sudah menjadi Pembimbing 1.');
    if (approval_table_exists($db, 'mythesis_pembimbing2')) {
        $stmt = $db->prepare('DELETE FROM mythesis_pembimbing2 WHERE bimbingan_id=? AND dosen_id=?');
        $stmt->bind_param('ii', $guidanceId, $lecturerId);$stmt->execute();$stmt->close();
    }
    $status = $guidance['status'] === 'ditangguhkan' ? 'aktif' : $guidance['status'];
    $stmt = $db->prepare('UPDATE bimbingan SET dosen_id=?,status=? WHERE id=?');
    $stmt->bind_param('isi', $lecturerId, $status, $guidanceId);$stmt->execute();$stmt->close();
    notify_user($db, (int)$guidance['mahasiswa_id'], 'bimbingan_baru', $lecturer['nama_lengkap'] . ' kini menjadi dosen pembimbing Anda untuk judul: ' . $guidance['judul_skripsi'] . '.', '?page=dashboard#bimbingan-saya', 'Dosen pembimbing baru', 'Dosen pembimbing baru Anda', 'Lihat Bimbingan');
    notify_user($db, $lecturerId, 'bimbingan_baru', 'Anda ditetapkan sebagai dosen pembimbing untuk judul: ' . $guidance['judul_skripsi'] . '.', '?page=dashboard#mahasiswa-bimbingan', 'Mahasiswa bimbingan baru', 'Mahasiswa bimbingan baru', 'Lihat Mahasiswa Bimbingan');
    return ['dosen' => $lecturer['nama_lengkap']];
}

/** Alasan dosen tidak boleh dihapus, atau '' bila boleh. */
function lecturer_delete_blocker(mysqli $db, int $lecturerId): string
{
    $count = function (string $sql) use ($db, $lecturerId): int {
        $stmt = $db->prepare($sql);$stmt->bind_param('i', $lecturerId);$stmt->execute();$total = (int)$stmt->get_result()->fetch_row()[0];$stmt->close();
        return $total;
    };
    $guidances = $count('SELECT COUNT(*) FROM bimbingan WHERE dosen_id=?');
    if (approval_table_exists($db, 'mythesis_pembimbing2')) $guidances += $count('SELECT COUNT(*) FROM mythesis_pembimbing2 WHERE dosen_id=?');
    if ($guidances > 0) return 'Dosen ini terkait ' . $guidances . ' bimbingan sehingga tidak dapat dihapus. Gunakan Nonaktifkan agar riwayat mahasiswa tetap utuh.';
    if ($count('SELECT COUNT(*) FROM revisi WHERE dosen_id=?') > 0) return 'Dosen ini pernah memberi revisi sehingga tidak dapat dihapus. Gunakan Nonaktifkan.';
    return '';
}

function lecturer_delete(mysqli $db, int $lecturerId): string
{
    $lecturer = useradmin_lecturer($db, $lecturerId);
    $blocker = lecturer_delete_blocker($db, $lecturerId);
    if ($blocker !== '') throw new RuntimeException($blocker);
    $db->begin_transaction();
    try {
        $run = function (string $sql) use ($db): void {if ($db->query($sql) === false) throw new RuntimeException('Dosen belum dapat dihapus.');};
        foreach (['notifikasi' => 'user_id', 'password_resets' => 'user_id', 'mythesis_review_dosen' => 'dosen_id'] as $table => $column) {
            if (approval_table_exists($db, $table)) $run("DELETE FROM `$table` WHERE `$column`=$lecturerId");
        }
        $run("DELETE FROM users WHERE id=$lecturerId AND role='dosen'");
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error instanceof RuntimeException ? $error : new RuntimeException('Dosen belum dapat dihapus.');
    }
    foreach (glob(dirname(__DIR__, 2) . '/storage/avatars/user-' . $lecturerId . '.*') ?: [] as $avatar) @unlink($avatar);
    error_log('MyThesis: akun dosen #' . $lecturerId . ' (' . $lecturer['nama_lengkap'] . ') dihapus');
    return $lecturer['nama_lengkap'];
}

/** Hapus permanen akun mahasiswa beserta seluruh bimbingan, dokumen, revisi, dan filenya. */
function student_delete(mysqli $db, int $studentId): string
{
    $stmt = $db->prepare("SELECT id,nama_lengkap FROM users WHERE id=? AND role='mahasiswa' LIMIT 1");
    $stmt->bind_param('i', $studentId);$stmt->execute();$student = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$student) throw new RuntimeException('Mahasiswa tidak ditemukan.');
    $files = [];
    $db->begin_transaction();
    try {
        $run = function (string $sql) use ($db): void {if ($db->query($sql) === false) throw new RuntimeException('Mahasiswa belum dapat dihapus.');};
        $ids = array_map('intval', array_column($db->query("SELECT id FROM bimbingan WHERE mahasiswa_id=$studentId")->fetch_all(MYSQLI_ASSOC), 'id'));
        if ($ids) {
            $list = implode(',', $ids);
            foreach ($db->query("SELECT file_path FROM bab_skripsi WHERE bimbingan_id IN ($list)")->fetch_all(MYSQLI_NUM) as $row) $files[] = (string)$row[0];
            if (column_exists($db, 'revisi', 'file_path')) {
                foreach ($db->query("SELECT r.file_path FROM revisi r JOIN bab_skripsi bs ON bs.id=r.bab_id WHERE bs.bimbingan_id IN ($list) AND r.file_path IS NOT NULL")->fetch_all(MYSQLI_NUM) as $row) $files[] = (string)$row[0];
            }
            $chapterIds = "SELECT id FROM bab_skripsi WHERE bimbingan_id IN ($list)";
            if (approval_table_exists($db, 'mythesis_review_dosen')) {
                $run("DELETE FROM mythesis_review_dosen WHERE jenis='judul' AND objek_id IN ($list)");
                $run("DELETE FROM mythesis_review_dosen WHERE jenis='bab' AND objek_id IN ($chapterIds)");
            }
            foreach (['mythesis_pembimbing2', 'konsultasi', 'log_pertemuan', 'jadwal_seminar'] as $table) {
                if (approval_table_exists($db, $table)) $run("DELETE FROM `$table` WHERE bimbingan_id IN ($list)");
            }
            $run("DELETE FROM revisi WHERE bab_id IN ($chapterIds)");
            $run("DELETE FROM bab_skripsi WHERE bimbingan_id IN ($list)");
            $run("DELETE FROM bimbingan WHERE id IN ($list)");
        }
        foreach (['notifikasi' => 'user_id', 'password_resets' => 'user_id'] as $table => $column) {
            if (approval_table_exists($db, $table)) $run("DELETE FROM `$table` WHERE `$column`=$studentId");
        }
        $run("DELETE FROM users WHERE id=$studentId AND role='mahasiswa'");
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error instanceof RuntimeException ? $error : new RuntimeException('Mahasiswa belum dapat dihapus.');
    }
    foreach (array_unique($files) as $relative) {
        $path = function_exists('document_file_path') ? document_file_path($relative) : null;
        if ($path) @unlink($path);
    }
    foreach (glob(dirname(__DIR__, 2) . '/storage/avatars/user-' . $studentId . '.*') ?: [] as $avatar) @unlink($avatar);
    error_log('MyThesis: akun mahasiswa #' . $studentId . ' (' . $student['nama_lengkap'] . ') dihapus beserta ' . count($ids ?? []) . ' bimbingan');
    return $student['nama_lengkap'];
}
