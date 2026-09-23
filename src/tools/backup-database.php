<?php
/**
 * Backup database MyThesis tanpa mysqldump (PHP murni), untuk dijalankan lewat Cron Jobs cPanel:
 *   php /home/USER/public_html/mythesis/src/tools/backup-database.php
 * - Hasil: <folder backup>/mythesis-YYYYmmdd-HHMMSS.sql.gz (struktur + data semua tabel).
 * - Folder backup bawaan: di luar public_html, yaitu ~/backup-mythesis (ubah lewat konstanta BACKUP_DIR).
 * - Menyimpan BACKUP_KEEP_DAYS hari terakhir (bawaan 14) dan menghapus yang lebih lama.
 * - Status terakhir ditulis ke storage/backup-status.json untuk halaman Pemeriksaan Sistem.
 * Hanya boleh dijalankan dari command line; akses lewat browser ditolak.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya dapat dijalankan dari command line.');
}
$appRoot = dirname(__DIR__, 2);
require $appRoot . '/config/database.php';
if (!isset($conn) || !($conn instanceof mysqli)) {fwrite(STDERR, "Koneksi database tidak tersedia.\n");exit(1);}
$conn->set_charset('utf8mb4');

$backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : dirname($appRoot, 2) . '/backup-mythesis';
$keepDays = defined('BACKUP_KEEP_DAYS') ? (int)BACKUP_KEEP_DAYS : 14;
$statusFile = $appRoot . '/storage/backup-status.json';
$writeStatus = function (array $status) use ($statusFile): void {
    @file_put_contents($statusFile, json_encode($status, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
};

try {
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('Folder backup tidak dapat dibuat: ' . $backupDir);
    $fileName = 'mythesis-' . date('Ymd-His') . '.sql.gz';
    $target = $backupDir . '/' . $fileName;
    $partial = $target . '.part';
    $gz = gzopen($partial, 'wb6');
    if (!$gz) throw new RuntimeException('File backup tidak dapat ditulis: ' . $partial);
    $write = function (string $text) use ($gz): void {
        if (gzwrite($gz, $text) === false) throw new RuntimeException('Gagal menulis file backup.');
    };
    $write("-- Backup MyThesis " . date('c') . "\n-- Database: " . $dbName . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tables = [];$rowsTotal = 0;
    $result = $conn->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    while ($row = $result->fetch_row()) $tables[] = $row[0];
    foreach ($tables as $table) {
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $create = $conn->query('SHOW CREATE TABLE ' . $quoted)->fetch_row()[1];
        $write("DROP TABLE IF EXISTS $quoted;\n$create;\n\n");
        $data = $conn->query('SELECT * FROM ' . $quoted, MYSQLI_USE_RESULT);
        $batch = [];
        $fields = $data->fetch_fields();
        while ($row = $data->fetch_row()) {
            $values = [];
            foreach ($row as $index => $value) {
                if ($value === null) $values[] = 'NULL';
                elseif (in_array($fields[$index]->type, [MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24, MYSQLI_TYPE_YEAR], true)) $values[] = (string)$value;
                else $values[] = "'" . $conn->real_escape_string((string)$value) . "'";
            }
            $batch[] = '(' . implode(',', $values) . ')';
            $rowsTotal++;
            if (count($batch) >= 200) {$write("INSERT INTO $quoted VALUES\n" . implode(",\n", $batch) . ";\n");$batch = [];}
        }
        $data->free();
        if ($batch) $write("INSERT INTO $quoted VALUES\n" . implode(",\n", $batch) . ";\n");
        $write("\n");
    }
    $write("SET FOREIGN_KEY_CHECKS=1;\n-- Selesai\n");
    gzclose($gz);
    if (!rename($partial, $target)) throw new RuntimeException('File backup tidak dapat diselesaikan.');
    chmod($target, 0600);

    // Hapus backup yang lebih lama dari batas simpan.
    $removed = 0;
    foreach (glob($backupDir . '/mythesis-*.sql.gz') ?: [] as $old) {
        if (filemtime($old) < time() - $keepDays * 86400 && @unlink($old)) $removed++;
    }
    $count = count(glob($backupDir . '/mythesis-*.sql.gz') ?: []);
    $status = ['ok' => true, 'waktu' => date('c'), 'file' => $fileName, 'folder' => $backupDir, 'ukuran' => filesize($target), 'tabel' => count($tables), 'baris' => $rowsTotal, 'jumlah_backup' => $count, 'dihapus' => $removed];
    $writeStatus($status);
    echo "Backup berhasil: $target (" . round(filesize($target) / 1024, 1) . " KB, " . count($tables) . " tabel, $rowsTotal baris). Tersimpan $count backup.\n";
    exit(0);
} catch (Throwable $error) {
    if (isset($gz) && is_resource($gz)) gzclose($gz);
    if (isset($partial) && is_file($partial)) @unlink($partial);
    $writeStatus(['ok' => false, 'waktu' => date('c'), 'pesan' => $error->getMessage(), 'folder' => $backupDir]);
    fwrite(STDERR, 'Backup gagal: ' . $error->getMessage() . "\n");
    error_log('MyThesis backup gagal: ' . $error->getMessage());
    exit(1);
}
