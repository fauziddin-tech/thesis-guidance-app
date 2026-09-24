<?php
/**
 * Log pertemuan bimbingan (tabel log_pertemuan, migrasi 20260927_add_meeting_log.sql).
 * Mahasiswa mencatat pertemuan dengan salah satu pembimbingnya; hanya dosen yang ditemui
 * yang dapat mengonfirmasi atau menolak. Pertemuan terkonfirmasi dihitung ke target minimal
 * dan dicetak di kartu bimbingan. Semua fungsi aman dipanggil sebelum migrasi dijalankan.
 */

const MEETING_METHODS = ['tatap_muka' => 'Tatap muka', 'daring' => 'Daring'];

function meeting_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready === null) {
        $result = $db->query("SHOW TABLES LIKE 'log_pertemuan'");
        $ready = $result && $result->num_rows > 0;
        if ($result) $result->free();
    }
    return $ready;
}

// Target minimal pertemuan terkonfirmasi per bimbingan. Dapat diubah di config/database.local.php:
// define('MEETING_TARGET', 10);
function meeting_target(): int
{
    $target = defined('MEETING_TARGET') ? (int)MEETING_TARGET : 8;
    return max(1, min(50, $target));
}

function meeting_method_label(string $method): string
{
    return MEETING_METHODS[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

// Pertemuan hanya dapat dicatat selama bimbingan berjalan (termasuk tahap judul).
function meeting_open_status(string $status): bool
{
    return in_array($status, ['pengajuan_judul', 'revisi_judul', 'aktif'], true);
}

/** Jumlah pertemuan terkonfirmasi: [bimbingan_id => total]. */
function meeting_confirmed_counts(mysqli $db, array $guidanceIds): array
{
    $guidanceIds = array_values(array_unique(array_filter(array_map('intval', $guidanceIds))));
    $counts = array_fill_keys($guidanceIds, 0);
    if (!$guidanceIds || !meeting_ready($db)) return $counts;
    $result = $db->query("SELECT bimbingan_id,COUNT(*) AS total FROM log_pertemuan WHERE status='dikonfirmasi' AND bimbingan_id IN (" . implode(',', $guidanceIds) . ') GROUP BY bimbingan_id');
    while ($result && ($row = $result->fetch_assoc())) $counts[(int)$row['bimbingan_id']] = (int)$row['total'];
    return $counts;
}

/** Semua log untuk bimbingan tertentu, terbaru lebih dulu: [bimbingan_id => [baris...]]. */
function meeting_logs_by_guidance(mysqli $db, array $guidanceIds, bool $confirmedOnly = false): array
{
    $guidanceIds = array_values(array_unique(array_filter(array_map('intval', $guidanceIds))));
    $logs = array_fill_keys($guidanceIds, []);
    if (!$guidanceIds || !meeting_ready($db)) return $logs;
    $sql = 'SELECT l.*,u.nama_lengkap AS dosen_nama FROM log_pertemuan l JOIN users u ON u.id=l.dosen_id WHERE l.bimbingan_id IN (' . implode(',', $guidanceIds) . ')'
        . ($confirmedOnly ? " AND l.status='dikonfirmasi'" : '') . ' ORDER BY l.tanggal DESC,l.id DESC';
    $result = $db->query($sql);
    while ($result && ($row = $result->fetch_assoc())) $logs[(int)$row['bimbingan_id']][] = $row;
    return $logs;
}

/** Log yang menunggu konfirmasi dosen tertentu, terlama lebih dulu. */
function meeting_pending_for_lecturer(mysqli $db, int $lecturerId): array
{
    if (!meeting_ready($db)) return [];
    $stmt = $db->prepare("SELECT l.*,b.judul_skripsi,b.mahasiswa_id,m.nama_lengkap AS mahasiswa_nama FROM log_pertemuan l JOIN bimbingan b ON b.id=l.bimbingan_id JOIN users m ON m.id=b.mahasiswa_id WHERE l.dosen_id=? AND l.status='menunggu' ORDER BY l.tanggal,l.id");
    $stmt->bind_param('i', $lecturerId);$stmt->execute();$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    return $rows;
}

/** Tampilan ringkas jumlah pertemuan untuk tabel dosen/admin. */
function meeting_badge(int $confirmed): string
{
    $target = meeting_target();
    $class = $confirmed >= $target ? 'meeting-count is-complete' : 'meeting-count';
    return '<small class="' . $class . '" title="Pertemuan bimbingan yang sudah dikonfirmasi dosen">' . $confirmed . '/' . $target . ' pertemuan</small>';
}

/** Mahasiswa mencatat pertemuan. Melempar RuntimeException berisi pesan untuk pengguna. */
function meeting_record(mysqli $db, array $student, int $guidanceId, int $lecturerId, string $date, string $method, string $topic, string $notes): void
{
    if (!meeting_ready($db)) throw new RuntimeException('Fitur log pertemuan belum diaktifkan. Hubungi administrator.');
    $studentId = (int)$student['id'];
    $stmt = $db->prepare('SELECT id,status,judul_skripsi FROM bimbingan WHERE id=? AND mahasiswa_id=? LIMIT 1');
    $stmt->bind_param('ii', $guidanceId, $studentId);$stmt->execute();$guidance = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$guidance) throw new RuntimeException('Bimbingan tidak ditemukan.');
    if (!meeting_open_status((string)$guidance['status'])) throw new RuntimeException('Pertemuan hanya dapat dicatat selama bimbingan berjalan.');
    $teamIds = array_map('intval', array_column(p2_team($db, $guidanceId), 'id'));
    if (!in_array($lecturerId, $teamIds, true)) throw new RuntimeException('Pilih dosen pembimbing yang Anda temui.');
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new RuntimeException('Tanggal pertemuan tidak valid.');
    $today = new DateTime('today');
    if ($parsed > $today) throw new RuntimeException('Tanggal pertemuan tidak boleh melewati hari ini.');
    if ($parsed < (clone $today)->modify('-365 days')) throw new RuntimeException('Tanggal pertemuan paling lama satu tahun yang lalu.');
    if (!isset(MEETING_METHODS[$method])) throw new RuntimeException('Pilih metode pertemuan.');
    if (mb_strlen($topic) < 5 || mb_strlen($topic) > 255) throw new RuntimeException('Topik pertemuan wajib diisi, 5–255 karakter.');
    if (mb_strlen($notes) < 10 || mb_strlen($notes) > 2000) throw new RuntimeException('Arahan dosen wajib diisi, 10–2.000 karakter.');
    $stmt = $db->prepare("SELECT id FROM log_pertemuan WHERE bimbingan_id=? AND dosen_id=? AND tanggal=? AND status<>'ditolak' LIMIT 1");
    $stmt->bind_param('iis', $guidanceId, $lecturerId, $date);$stmt->execute();$duplicate = $stmt->get_result()->num_rows > 0;$stmt->close();
    if ($duplicate) throw new RuntimeException('Pertemuan dengan dosen ini pada tanggal tersebut sudah tercatat.');
    $stmt = $db->prepare('INSERT INTO log_pertemuan(bimbingan_id,dosen_id,tanggal,metode,topik,catatan) VALUES(?,?,?,?,?,?)');
    $stmt->bind_param('iissss', $guidanceId, $lecturerId, $date, $method, $topic, $notes);$ok = $stmt->execute();$stmt->close();
    if (!$ok) throw new RuntimeException('Log pertemuan belum dapat disimpan.');
    notify_user($db, $lecturerId, 'log_pertemuan', $student['nama_lengkap'] . ' mencatat pertemuan bimbingan tanggal ' . tanggal_id($date) . ' dan menunggu konfirmasi Anda.' . "\n\nTopik: " . $topic,
        '?page=dashboard#konfirmasi-pertemuan', 'Konfirmasi pertemuan bimbingan: ' . $student['nama_lengkap'], 'Pertemuan bimbingan menunggu konfirmasi', 'Konfirmasi Pertemuan');
}

/** Mahasiswa membatalkan log miliknya yang belum dikonfirmasi. */
function meeting_cancel(mysqli $db, int $studentId, int $logId): void
{
    if (!meeting_ready($db)) throw new RuntimeException('Fitur log pertemuan belum diaktifkan.');
    $stmt = $db->prepare("DELETE l FROM log_pertemuan l JOIN bimbingan b ON b.id=l.bimbingan_id WHERE l.id=? AND b.mahasiswa_id=? AND l.status='menunggu'");
    $stmt->bind_param('ii', $logId, $studentId);$stmt->execute();$deleted = $stmt->affected_rows > 0;$stmt->close();
    if (!$deleted) throw new RuntimeException('Log tidak ditemukan atau sudah ditanggapi dosen.');
}

/** Dosen mengonfirmasi atau menolak log yang ditujukan kepadanya. */
function meeting_decide(mysqli $db, int $lecturerId, int $logId, bool $confirm, string $reason = ''): void
{
    if (!meeting_ready($db)) throw new RuntimeException('Fitur log pertemuan belum diaktifkan.');
    $stmt = $db->prepare("SELECT l.*,b.mahasiswa_id FROM log_pertemuan l JOIN bimbingan b ON b.id=l.bimbingan_id WHERE l.id=? AND l.dosen_id=? LIMIT 1");
    $stmt->bind_param('ii', $logId, $lecturerId);$stmt->execute();$log = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$log || !p2_is_member($db, (int)$log['bimbingan_id'], $lecturerId)) throw new RuntimeException('Log pertemuan tidak ditemukan.');
    if ($log['status'] !== 'menunggu') throw new RuntimeException('Log pertemuan ini sudah ditanggapi.');
    if (!$confirm && (mb_strlen($reason) < 5 || mb_strlen($reason) > 500)) throw new RuntimeException('Alasan penolakan wajib diisi, 5–500 karakter.');
    if ($confirm) {
        $stmt = $db->prepare("UPDATE log_pertemuan SET status='dikonfirmasi',alasan_tolak=NULL,dikonfirmasi_at=NOW() WHERE id=? AND status='menunggu'");
        $stmt->bind_param('i', $logId);
    } else {
        $stmt = $db->prepare("UPDATE log_pertemuan SET status='ditolak',alasan_tolak=? WHERE id=? AND status='menunggu'");
        $stmt->bind_param('si', $reason, $logId);
    }
    $stmt->execute();$changed = $stmt->affected_rows > 0;$stmt->close();
    if (!$changed) throw new RuntimeException('Log pertemuan ini sudah ditanggapi.');
    $when = tanggal_id((string)$log['tanggal']);
    if ($confirm) {
        $total = meeting_confirmed_counts($db, [(int)$log['bimbingan_id']])[(int)$log['bimbingan_id']] ?? 0;
        notify_user($db, (int)$log['mahasiswa_id'], 'log_pertemuan', 'Pertemuan bimbingan tanggal ' . $when . ' telah dikonfirmasi dosen. Total pertemuan terkonfirmasi: ' . $total . ' dari target ' . meeting_target() . '.',
            '?page=dashboard#log-pertemuan', 'Pertemuan bimbingan dikonfirmasi', 'Pertemuan bimbingan dikonfirmasi', 'Lihat Log Pertemuan');
    } else {
        notify_user($db, (int)$log['mahasiswa_id'], 'log_pertemuan', 'Log pertemuan bimbingan tanggal ' . $when . ' tidak dikonfirmasi dosen.' . "\n\nAlasan: " . $reason,
            '?page=dashboard#log-pertemuan', 'Log pertemuan bimbingan ditolak', 'Log pertemuan tidak dikonfirmasi', 'Lihat Log Pertemuan');
    }
}
