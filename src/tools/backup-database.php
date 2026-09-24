<?php
/**
 * Backup database MyThesis untuk Cron Jobs cPanel:
 *   php /home/USER/public_html/mythesis/src/tools/backup-database.php
 * - Hasil: <folder backup>/mythesis-YYYYmmdd-HHMMSS.sql.gz (struktur + data semua tabel).
 * - Folder backup bawaan: di luar public_html, yaitu ~/backup-mythesis (ubah lewat konstanta BACKUP_DIR).
 * - Menyimpan BACKUP_KEEP_DAYS hari terakhir (bawaan 14) dan menghapus yang lebih lama.
 * - Admin juga dapat membuat dan mengunduh backup dari dashboard (bagian Backup Database).
 * Logika backup ada di src/helpers/Backup.php. Hanya boleh dijalankan dari command line.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya dapat dijalankan dari command line.');
}
$appRoot = dirname(__DIR__, 2);
require $appRoot . '/config/database.php';
require $appRoot . '/src/helpers/Backup.php';
if (!isset($conn) || !($conn instanceof mysqli)) {fwrite(STDERR, "Koneksi database tidak tersedia.\n");exit(1);}
$conn->set_charset('utf8mb4');

try {
    $status = backup_run($conn, (string)$dbName, 'cron');
    echo 'Backup berhasil: ' . $status['folder'] . '/' . $status['file'] . ' (' . round($status['ukuran'] / 1024, 1) . ' KB, ' . $status['tabel'] . ' tabel, ' . $status['baris'] . ' baris). Tersimpan ' . $status['jumlah_backup'] . " backup.\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Backup gagal: ' . $error->getMessage() . "\n");
    exit(1);
}
