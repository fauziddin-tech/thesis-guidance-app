<?php
/**
 * Backup database MyThesis (PHP murni, tanpa mysqldump). Dipakai bersama oleh:
 * - src/tools/backup-database.php (Cron Jobs cPanel), dan
 * - dashboard admin (tombol "Buat Backup Sekarang" dan unduh file backup).
 * File disimpan di luar public_html: ~/backup-mythesis (ubah lewat konstanta BACKUP_DIR).
 */

const BACKUP_FILE_PATTERN = '/^mythesis-\d{8}-\d{6}\.sql\.gz$/';

function backup_app_root(): string
{
    return dirname(__DIR__, 2);
}

function backup_dir(): string
{
    return defined('BACKUP_DIR') ? rtrim((string)BACKUP_DIR, '/') : dirname(backup_app_root(), 2) . '/backup-mythesis';
}

function backup_status_file(): string
{
    return backup_app_root() . '/storage/backup-status.json';
}

function backup_status(): ?array
{
    $file = backup_status_file();
    $status = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return is_array($status) ? $status : null;
}

/** Daftar file backup, terbaru lebih dulu: [['nama','ukuran','waktu'], ...]. */
function backup_list(): array
{
    $files = [];
    foreach (glob(backup_dir() . '/mythesis-*.sql.gz') ?: [] as $path) {
        $name = basename($path);
        if (!preg_match(BACKUP_FILE_PATTERN, $name) || !is_file($path)) continue;
        $files[] = ['nama' => $name, 'ukuran' => (int)filesize($path), 'waktu' => (int)filemtime($path)];
    }
    usort($files, function ($first, $second) {return $second['waktu'] <=> $first['waktu'] ?: strcmp($second['nama'], $first['nama']);});
    return $files;
}

/** Path absolut file backup yang sah, atau null. Nama file divalidasi agar tidak dapat keluar dari folder backup. */
function backup_file_path(string $name): ?string
{
    if (!preg_match(BACKUP_FILE_PATTERN, $name)) return null;
    $dir = realpath(backup_dir());
    $path = $dir ? realpath($dir . '/' . $name) : false;
    return ($path && is_file($path) && dirname($path) === $dir) ? $path : null;
}

/**
 * Membuat backup lengkap (struktur + data semua tabel), menghapus backup yang lebih lama dari
 * BACKUP_KEEP_DAYS hari (bawaan 14), dan menulis status untuk halaman Pemeriksaan.
 * Mengembalikan status; melempar RuntimeException bila gagal.
 */
function backup_run(mysqli $conn, string $dbName, string $trigger = 'cron'): array
{
    $backupDir = backup_dir();
    $keepDays = defined('BACKUP_KEEP_DAYS') ? max(1, (int)BACKUP_KEEP_DAYS) : 14;
    $gz = null;$partial = '';
    try {
        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('Folder backup tidak dapat dibuat: ' . $backupDir);
        $fileName = 'mythesis-' . date('Ymd-His') . '.sql.gz';
        $target = $backupDir . '/' . $fileName;
        if (is_file($target)) throw new RuntimeException('Backup dengan waktu yang sama sudah ada. Coba lagi sebentar.');
        $partial = $target . '.part';
        $gz = gzopen($partial, 'wb6');
        if (!$gz) throw new RuntimeException('File backup tidak dapat ditulis: ' . $partial);
        $write = function (string $text) use ($gz): void {
            if (gzwrite($gz, $text) === false) throw new RuntimeException('Gagal menulis file backup.');
        };
        $write("-- Backup MyThesis " . date('c') . "\n-- Database: " . $dbName . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $tables = [];$rowsTotal = 0;
        $result = $conn->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        if (!$result) throw new RuntimeException('Daftar tabel tidak dapat dibaca.');
        while ($row = $result->fetch_row()) $tables[] = $row[0];
        $integerTypes = [MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24, MYSQLI_TYPE_YEAR];
        foreach ($tables as $table) {
            $quoted = '`' . str_replace('`', '``', $table) . '`';
            $create = $conn->query('SHOW CREATE TABLE ' . $quoted)->fetch_row()[1];
            $write("DROP TABLE IF EXISTS $quoted;\n$create;\n\n");
            $data = $conn->query('SELECT * FROM ' . $quoted, MYSQLI_USE_RESULT);
            if (!$data) throw new RuntimeException('Tabel ' . $table . ' tidak dapat dibaca.');
            $batch = [];
            $fields = $data->fetch_fields();
            while ($row = $data->fetch_row()) {
                $values = [];
                foreach ($row as $index => $value) {
                    if ($value === null) $values[] = 'NULL';
                    elseif (in_array($fields[$index]->type, $integerTypes, true)) $values[] = (string)$value;
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
        gzclose($gz);$gz = null;
        if (!rename($partial, $target)) throw new RuntimeException('File backup tidak dapat diselesaikan.');
        @chmod($target, 0600);

        $removed = 0;
        foreach (glob($backupDir . '/mythesis-*.sql.gz') ?: [] as $old) {
            if ($old !== $target && filemtime($old) < time() - $keepDays * 86400 && @unlink($old)) $removed++;
        }
        $count = count(glob($backupDir . '/mythesis-*.sql.gz') ?: []);
        $status = ['ok' => true, 'waktu' => date('c'), 'file' => $fileName, 'folder' => $backupDir, 'ukuran' => filesize($target), 'tabel' => count($tables), 'baris' => $rowsTotal, 'jumlah_backup' => $count, 'dihapus' => $removed, 'pemicu' => $trigger];
        @file_put_contents(backup_status_file(), json_encode($status, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return $status;
    } catch (Throwable $error) {
        if ($gz) gzclose($gz);
        if ($partial !== '' && is_file($partial)) @unlink($partial);
        @file_put_contents(backup_status_file(), json_encode(['ok' => false, 'waktu' => date('c'), 'pesan' => $error->getMessage(), 'folder' => $backupDir, 'pemicu' => $trigger], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        error_log('MyThesis backup gagal: ' . $error->getMessage());
        throw $error instanceof RuntimeException ? $error : new RuntimeException($error->getMessage());
    }
}
