<?php
// Pemeriksaan sistem untuk admin. Halaman ini hanya membaca dan tidak mengubah data apa pun.
$user = $_SESSION['user'];
$root = realpath(__DIR__ . '/../..');
$flash = pull_flash();

$toBytes = function ($value): int {
    $value = trim((string)$value);
    if ($value === '' || $value === '-1') return PHP_INT_MAX;
    $number = (float)$value;
    $unit = strtolower(substr($value, -1));
    if ($unit === 'g') $number *= 1024 ** 3;
    elseif ($unit === 'm') $number *= 1024 ** 2;
    elseif ($unit === 'k') $number *= 1024;
    return (int)$number;
};
$countQuery = function (string $sql) use ($conn): ?int {
    $result = $conn->query($sql);
    if (!$result) return null;
    $row = $result->fetch_row();
    $result->free();
    return (int)($row[0] ?? 0);
};

// 1. Pembaruan basis data (file di folder migrations/).
$migrations = [
    ['file' => '20260921_add_revision_file.sql', 'title' => 'Lampiran file revisi dosen', 'done' => column_exists($conn, 'revisi', 'file_path')],
    ['file' => '20260923_add_academic_periods.sql', 'title' => 'Periode akademik, program studi, serta NIM/prodi/angkatan', 'done' => academic_ready($conn)],
    ['file' => '20260924_add_guidance_card.sql', 'title' => 'Kartu bimbingan: tanggal persetujuan dan kode verifikasi', 'done' => column_exists($conn, 'bab_skripsi', 'disetujui_at') && column_exists($conn, 'bimbingan', 'kode_verifikasi')],
    ['file' => '20260925_add_lecturer_homepage.sql', 'title' => 'Pilihan menampilkan dosen di beranda', 'done' => column_exists($conn, 'users', 'tampil_beranda')],
    ['file' => '20260926_restore_features.sql', 'title' => 'Pembimbing 2, alur judul, lupa password, pembatasan login, log pratinjau', 'done' => p2_ready($conn) && column_exists($conn, 'password_resets', 'token_hash') && column_exists($conn, 'rate_limits', 'rkey') && column_exists($conn, 'impersonation_logs', 'target_id') && initial_guidance_status($conn) === 'pengajuan_judul'],
    ['file' => '20260927_add_meeting_log.sql', 'title' => 'Log pertemuan bimbingan dan target minimal pertemuan', 'done' => meeting_ready($conn)],
    ['file' => '20260928_pembimbing2_format_review.sql', 'title' => 'Pembimbing 2 memeriksa format naskah proposal (menyelaraskan status lama)', 'done' => !p2_ready($conn) || (int)$conn->query("SELECT (SELECT COUNT(*) FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id JOIN mythesis_review_dosen r ON r.jenis='bab' AND r.objek_id=bs.id AND r.dosen_id=b.dosen_id WHERE bs.status IN ('menunggu_review','direvisi') AND bs.status<>r.keputusan AND bs.nama_bab NOT LIKE 'Naskah Proposal%') + (SELECT COUNT(*) FROM bimbingan b JOIN mythesis_review_dosen r ON r.jenis='judul' AND r.objek_id=b.id AND r.dosen_id=b.dosen_id AND r.keputusan='disetujui' WHERE b.status='pengajuan_judul') AS total")->fetch_assoc()['total'] === 0],
    ['file' => '20260929_student_approval.sql', 'title' => 'Penerimaan pendaftaran mahasiswa oleh dosen pembimbing', 'done' => approval_ready($conn)],
    ['file' => '20260930_proposal_seminar.sql', 'title' => 'Tahap proposal penelitian dan seminar proposal sebelum Bab 4', 'done' => proposal_ready($conn)],
    ['file' => '20261001_proposal_checklist.sql', 'title' => 'Pemeriksaan kelengkapan dan tata tulis proposal', 'done' => proposal_checklist_ready($conn)],
];
$pendingMigrations = count(array_filter($migrations, function ($item) {return !$item['done'];}));

// 2. Pemeriksaan per kelompok: [label, status ok|warn|fail, keterangan].
$groups = [];

$server = [];
$phpVersion = PHP_VERSION;
$server[] = ['Versi PHP', version_compare($phpVersion, '8.0', '>=') ? 'ok' : (version_compare($phpVersion, '7.4', '>=') ? 'warn' : 'fail'), 'PHP ' . $phpVersion . (version_compare($phpVersion, '8.0', '<') ? ' — disarankan PHP 8.1 atau lebih baru.' : '')];
foreach ([
    'mysqli' => ['fail', 'koneksi basis data'],
    'mbstring' => ['fail', 'pengolahan teks berbahasa Indonesia'],
    'curl' => ['fail', 'pengiriman email melalui Resend'],
    'zip' => ['warn', 'pemeriksaan isi file DOCX'],
    'openssl' => ['warn', 'koneksi aman ke Resend'],
] as $extension => $info) {
    $loaded = extension_loaded($extension);
    $server[] = ['Ekstensi ' . $extension, $loaded ? 'ok' : $info[0], ($loaded ? 'Aktif' : 'Belum aktif') . ' — dipakai untuk ' . $info[1] . '.'];
}
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$server[] = ['Batas unggah file', $toBytes($uploadMax) >= 10 * 1024 * 1024 ? 'ok' : 'fail', 'upload_max_filesize = ' . $uploadMax . ' (aplikasi menerima dokumen hingga 10 MB).'];
$server[] = ['Batas ukuran formulir', $toBytes($postMax) >= 11 * 1024 * 1024 ? 'ok' : 'warn', 'post_max_size = ' . $postMax . ' (disarankan minimal 12M).'];
$dbNowResult = $conn->query('SELECT NOW()');
$dbNow = $dbNowResult ? (string)$dbNowResult->fetch_row()[0] : '';
$clockGap = $dbNow !== '' ? abs(strtotime($dbNow) - time()) : 0;
$server[] = ['Jam PHP dan basis data', $clockGap <= 300 ? 'ok' : 'warn', 'Zona waktu PHP ' . date_default_timezone_get() . ' (' . date('H:i') . '), basis data ' . ($dbNow !== '' ? date('H:i', strtotime($dbNow)) : '—') . ($clockGap > 300 ? '. Selisih ' . round($clockGap / 3600, 1) . ' jam dapat membuat urutan riwayat di kartu bimbingan keliru.' : '.')];
$displayErrors = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);
$server[] = ['Tampilan pesan error', $displayErrors ? 'warn' : 'ok', $displayErrors ? 'display_errors aktif; sebaiknya dimatikan di situs produksi agar detail teknis tidak terlihat pengunjung.' : 'display_errors nonaktif.'];
$groups['Server'] = $server;

$security = [];
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$security[] = ['HTTPS', $https ? 'ok' : 'fail', $https ? 'Situs diakses melalui HTTPS.' : 'Situs diakses tanpa HTTPS; password dan dokumen dapat disadap.'];
$security[] = ['Konfigurasi lokal', is_file($root . '/config/database.local.php') ? 'ok' : 'warn', is_file($root . '/config/database.local.php') ? 'config/database.local.php ditemukan.' : 'Konfigurasi dibaca dari environment variable.'];
$rootHtaccess = is_file($root . '/.htaccess') ? (string)file_get_contents($root . '/.htaccess') : '';
$security[] = ['Perlindungan folder sistem', strpos($rootHtaccess, 'config|src|storage') !== false ? 'ok' : 'fail', strpos($rootHtaccess, 'config|src|storage') !== false ? 'Folder config, src, dan storage tidak dapat dibuka langsung dari browser.' : 'Aturan .htaccess untuk memblokir folder config, src, dan storage tidak ditemukan.'];
// Folder uploads/ dari aplikasi lama masih dibaca (lewat index.php) tetapi tidak boleh dibuka langsung.
if (is_dir($root . '/uploads')) {
    $legacyBlocked = preg_match('/RewriteRule \^\(\?:[^)]*\buploads\b/', $rootHtaccess) === 1;
    $security[] = ['Folder uploads/ lama', $legacyBlocked ? 'ok' : 'fail', $legacyBlocked ? 'Dokumen era aplikasi lama tidak dapat dibuka langsung dari browser.' : 'Folder uploads/ belum diblokir di .htaccess; dokumen lama dapat diunduh tanpa login.'];
}
$errorLogBlocked = strpos($rootHtaccess, 'error_log') !== false;
if (is_file($root . '/error_log')) $security[] = ['File error_log', $errorLogBlocked ? 'ok' : 'fail', $errorLogBlocked ? 'error_log tidak dapat dibuka dari browser.' : 'error_log dapat dibuka dari browser dan dapat membocorkan detail teknis.'];
// Sisa aplikasi lama yang tidak dipakai lagi oleh MyThesis versi sekarang.
$legacyItems = array_values(array_filter(['app', 'database', 'download.php', 'export-riwayat.php', 'profile-photo.php', 'konsultasi.php', 'seminar.php', 'src/controllers', 'src/models'], function ($item) use ($root) {return file_exists($root . '/' . $item);}));
$security[] = ['Sisa aplikasi lama', $legacyItems ? 'warn' : 'ok', $legacyItems ? 'Masih ada: ' . implode(', ', $legacyItems) . '. Pindahkan ke luar public_html (lihat DEPLOYMENT.md).' : 'Tidak ada berkas aplikasi lama di folder situs.'];
foreach (['storage/uploads' => 'Dokumen skripsi', 'storage/avatars' => 'Foto profil'] as $folder => $label) {
    $path = $root . '/' . $folder;
    $writable = is_dir($path) && is_writable($path);
    $security[] = ['Folder ' . $folder, $writable ? 'ok' : 'fail', $label . ($writable ? ' dapat disimpan.' : ' tidak dapat disimpan; periksa keberadaan folder dan permission (750/755).')];
    $protected = is_file($path . '/.htaccess');
    $security[] = ['Proteksi ' . $folder, $protected ? 'ok' : 'warn', $protected ? 'File .htaccess pelindung tersedia.' : 'File .htaccess pelindung tidak ditemukan.'];
}
$freeSpace = @disk_free_space($root . '/storage');
if ($freeSpace !== false) $security[] = ['Ruang penyimpanan', $freeSpace >= 500 * 1024 * 1024 ? 'ok' : 'warn', 'Sisa ruang ' . number_format($freeSpace / 1024 / 1024 / 1024, 1, ',', '.') . ' GB.'];
// Backup database otomatis (src/tools/backup-database.php lewat Cron Jobs).
$backupStatus = is_file($root . '/storage/backup-status.json') ? json_decode((string)file_get_contents($root . '/storage/backup-status.json'), true) : null;
if (!is_array($backupStatus)) {
    $security[] = ['Backup database', 'fail', 'Belum pernah berjalan. Jadwalkan src/tools/backup-database.php di cPanel → Cron Jobs (setiap malam).'];
} elseif (empty($backupStatus['ok'])) {
    $security[] = ['Backup database', 'fail', 'Backup terakhir gagal (' . tanggal_id((string)$backupStatus['waktu'], true) . '): ' . (string)($backupStatus['pesan'] ?? 'lihat error_log') . '.'];
} else {
    $backupAge = time() - (int)strtotime((string)$backupStatus['waktu']);
    // Batas usia backup: 36 jam untuk jadwal harian; atur BACKUP_MAX_AGE_HOURS (mis. 192 untuk mingguan) di database.local.php.
    $backupMaxHours = defined('BACKUP_MAX_AGE_HOURS') ? max(1, (int)BACKUP_MAX_AGE_HOURS) : 36;
    $security[] = ['Backup database', $backupAge <= $backupMaxHours * 3600 ? 'ok' : 'warn', 'Terakhir ' . tanggal_id((string)$backupStatus['waktu'], true) . ' — ' . number_format((int)$backupStatus['ukuran'] / 1024, 1, ',', '.') . ' KB, ' . (int)$backupStatus['tabel'] . ' tabel. Tersimpan ' . (int)$backupStatus['jumlah_backup'] . ' backup di ' . (string)$backupStatus['folder'] . '.' . ($backupAge > $backupMaxHours * 3600 ? ' Sudah lebih dari ' . ($backupMaxHours % 24 === 0 ? ($backupMaxHours / 24) . ' hari' : $backupMaxHours . ' jam') . '; periksa Cron Jobs.' : '')];
}
$groups['Keamanan dan Penyimpanan'] = $security;

$mail = [];
$apiKey = defined('RESEND_API_KEY') ? (string)RESEND_API_KEY : (string)(getenv('RESEND_API_KEY') ?: '');
$mailFrom = defined('MAIL_FROM') ? (string)MAIL_FROM : (string)(getenv('MAIL_FROM') ?: '');
$appUrl = defined('APP_URL') ? (string)APP_URL : (string)(getenv('APP_URL') ?: '');
$mail[] = ['API key Resend', $apiKey === '' ? 'fail' : (strpos($apiKey, 're_') === 0 ? 'ok' : 'warn'), $apiKey === '' ? 'RESEND_API_KEY belum diisi; email tidak akan terkirim.' : 'Terisi (' . substr($apiKey, 0, 6) . '…' . ')' . (strpos($apiKey, 're_') === 0 ? '.' : '; format tidak diawali re_.')];
$mail[] = ['Alamat pengirim', filter_var($mailFrom, FILTER_VALIDATE_EMAIL) ? 'ok' : 'fail', filter_var($mailFrom, FILTER_VALIDATE_EMAIL) ? $mailFrom . ' — domain ini harus berstatus Verified di Resend.' : 'MAIL_FROM belum diisi atau tidak valid.'];
$currentHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
$appHost = (string)parse_url($appUrl, PHP_URL_HOST);
$appUrlStatus = $appUrl === '' ? 'warn' : ((strpos($appUrl, 'https://') === 0 && ($appHost === $currentHost || $currentHost === '')) ? 'ok' : 'warn');
$mail[] = ['Alamat aplikasi (APP_URL)', $appUrlStatus, $appUrl === '' ? 'APP_URL belum diisi; tautan email dan QR memakai alamat yang sedang dibuka.' : $appUrl . ($appUrlStatus === 'ok' ? '' : ' — sebaiknya memakai https dan domain yang sama dengan ' . $currentHost . '.')];
$groups['Email'] = $mail;

$data = [];
$lecturerCount = $countQuery("SELECT COUNT(*) FROM users WHERE role='dosen'");
$studentCount = $countQuery("SELECT COUNT(*) FROM users WHERE role='mahasiswa'");
// Kemungkinan akun mahasiswa ganda (nama sama). Hapus lewat Daftar Bimbingan > Hapus bimbingan.
$duplicateNames = [];
$duplicateResult = $conn->query("SELECT GROUP_CONCAT(nama_lengkap ORDER BY id SEPARATOR ' / ') AS nama FROM users WHERE role='mahasiswa' GROUP BY REGEXP_REPLACE(LOWER(TRIM(nama_lengkap)),'[[:space:]]+',' ') HAVING COUNT(*)>1 LIMIT 10");
while ($duplicateResult && ($row = $duplicateResult->fetch_assoc())) $duplicateNames[] = $row['nama'];
$data[] = ['Akun mahasiswa ganda', $duplicateNames ? 'warn' : 'ok', $duplicateNames ? 'Nama yang terdaftar lebih dari sekali: ' . implode('; ', $duplicateNames) . '. Periksa di Daftar Bimbingan, lalu hapus akun yang tidak dipakai.' : 'Tidak ada nama mahasiswa yang terdaftar ganda.'];
$data[] = ['Akun dosen', $lecturerCount ? 'ok' : 'warn', $lecturerCount ? $lecturerCount . ' dosen terdaftar.' : 'Belum ada dosen; mahasiswa tidak dapat memilih pembimbing saat mendaftar.'];
if (academic_ready($conn)) {
    $activePeriodCount = $countQuery('SELECT COUNT(*) FROM periode_akademik WHERE is_aktif=1');
    $prodiCount = $countQuery('SELECT COUNT(*) FROM program_studi');
    $incomplete = $countQuery("SELECT COUNT(*) FROM users WHERE role='mahasiswa' AND (nim IS NULL OR nim='' OR prodi_id IS NULL OR angkatan IS NULL)");
    $withoutPeriod = $countQuery('SELECT COUNT(*) FROM bimbingan WHERE periode_id IS NULL');
    $data[] = ['Periode aktif', $activePeriodCount ? 'ok' : 'warn', $activePeriodCount ? 'Periode aktif: ' . period_label(($r = $conn->query('SELECT tahun_ajaran,semester FROM periode_akademik WHERE is_aktif=1 LIMIT 1')) ? $r->fetch_assoc() : null) . '.' : 'Belum ada periode aktif.'];
    $data[] = ['Program studi', $prodiCount ? 'ok' : 'fail', $prodiCount ? $prodiCount . ' program studi.' : 'Belum ada program studi; pendaftaran mahasiswa tertutup.'];
    $data[] = ['Data akademik mahasiswa', $incomplete ? 'warn' : 'ok', $incomplete ? $incomplete . ' dari ' . $studentCount . ' mahasiswa belum melengkapi NIM, prodi, atau angkatan.' : 'Semua mahasiswa sudah melengkapi data akademik.'];
    $data[] = ['Bimbingan tanpa periode', $withoutPeriod ? 'warn' : 'ok', $withoutPeriod ? $withoutPeriod . ' bimbingan belum memiliki periode; atur di Daftar Bimbingan.' : 'Semua bimbingan memiliki periode.'];
}
$missingDocuments = 0;
$documentResult = $conn->query('SELECT file_path FROM bab_skripsi');
while ($documentResult && ($row = $documentResult->fetch_row())) if (!is_file($root . '/' . ltrim((string)$row[0], '/'))) $missingDocuments++;
$documentTotal = $documentResult ? $documentResult->num_rows : 0;
$data[] = ['File dokumen mahasiswa', $missingDocuments ? 'fail' : 'ok', $missingDocuments ? $missingDocuments . ' dari ' . $documentTotal . ' file dokumen tidak ditemukan di storage/uploads.' : $documentTotal . ' file dokumen tersedia.'];
if (column_exists($conn, 'revisi', 'file_path')) {
    $missingRevisions = 0;
    $revisionResult = $conn->query("SELECT file_path FROM revisi WHERE file_path IS NOT NULL AND file_path<>''");
    while ($revisionResult && ($row = $revisionResult->fetch_row())) if (!is_file($root . '/' . ltrim((string)$row[0], '/'))) $missingRevisions++;
    $revisionTotal = $revisionResult ? $revisionResult->num_rows : 0;
    $data[] = ['File revisi dosen', $missingRevisions ? 'fail' : 'ok', $missingRevisions ? $missingRevisions . ' dari ' . $revisionTotal . ' file revisi tidak ditemukan.' : $revisionTotal . ' file revisi tersedia.'];
}
$data[] = ['Logo aplikasi', is_file($root . '/logo.png') ? 'ok' : 'warn', is_file($root . '/logo.png') ? 'logo.png tersedia untuk favicon, navigasi, dan kartu.' : 'logo.png tidak ditemukan di folder utama.'];
$data[] = ['Library QR kartu bimbingan', is_file($root . '/public/js/vendor/qrcode.js') ? 'ok' : 'fail', is_file($root . '/public/js/vendor/qrcode.js') ? 'public/js/vendor/qrcode.js tersedia.' : 'public/js/vendor/qrcode.js hilang; QR pada kartu tidak akan tampil.'];
$groups['Data Aplikasi'] = $data;

$allChecks = array_merge(...array_values($groups));
$failCount = count(array_filter($allChecks, function ($item) {return $item[1] === 'fail';}));
$warnCount = count(array_filter($allChecks, function ($item) {return $item[1] === 'warn';}));
$statusBadge = function (string $status): string {
    $map = ['ok' => ['success', 'Baik'], 'warn' => ['warning', 'Perhatikan'], 'fail' => ['danger', 'Perbaiki']];
    return '<span class="badge badge-' . $map[$status][0] . '">' . $map[$status][1] . '</span>';
};
?>
<div class="section-heading page-intro"><span class="eyebrow">ADMINISTRATOR</span><h1>Pemeriksaan Sistem</h1><p>Versi aplikasi <strong><?=h(APP_VERSION)?></strong>. Halaman ini hanya membaca, tidak mengubah apa pun. Diperiksa pada <?=h(tanggal_id(date('Y-m-d H:i:s'), true))?>.</p></div>
<?php if($flash): ?><div class="alert alert-<?=h($flash['type'])?>" role="status" tabindex="-1" data-flash><?=h($flash['message'])?></div><?php endif; ?>

<section class="check-summary" aria-label="Ringkasan pemeriksaan">
    <article class="<?=$pendingMigrations?'is-fail':''?>"><strong><?=$pendingMigrations?></strong><span>Pembaruan basis data</span><small>belum dijalankan</small></article>
    <article class="<?=$failCount?'is-fail':''?>"><strong><?=$failCount?></strong><span>Perlu dibereskan</span><small>hal penting</small></article>
    <article class="<?=$warnCount?'is-warn':''?>"><strong><?=$warnCount?></strong><span>Perlu diperhatikan</span><small>hal ringan</small></article>
    <article><strong><?=count($allChecks)?></strong><span>Pemeriksaan</span><small>butir diperiksa</small></article>
</section>

<section class="card"><div class="section-heading"><h2>Pembaruan Basis Data</h2><?php if($pendingMigrations): ?><p>Jalankan file yang belum aktif melalui phpMyAdmin, berurutan dari atas. Cadangkan basis data terlebih dahulu.</p><?php else: ?><p class="check-ok">✓ Semua pembaruan basis data sudah dijalankan. Tidak ada yang perlu dikerjakan.</p><?php endif; ?></div>
<div class="table-wrap"><table class="check-table"><thead><tr><th>File migrasi</th><th>Pembaruan</th><th>Status</th></tr></thead><tbody>
<?php foreach($migrations as $migration): ?><tr><td><code><?=h($migration['file'])?></code></td><td><?=h($migration['title'])?></td><td><?=$migration['done']?'<span class="badge badge-success">Aktif</span>':'<span class="badge badge-danger">Belum dijalankan</span>'?></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php foreach($groups as $groupName => $checks): ?>
<section class="card"<?=$groupName==='Email'?' id="email"':''?>><div class="section-heading"><h2><?=h($groupName)?></h2></div>
<div class="table-wrap"><table class="check-table"><thead><tr><th>Pemeriksaan</th><th>Keterangan</th><th>Status</th></tr></thead><tbody>
<?php foreach($checks as $check): ?><tr><td><strong><?=h($check[0])?></strong></td><td><?=h($check[2])?></td><td><?=$statusBadge($check[1])?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if($groupName==='Email'): ?><form method="post" action="?page=dashboard" class="test-email-form" id="tes-email" style="margin-top:18px"><?=csrf_field()?><input type="hidden" name="action" value="send_test_email"><input type="hidden" name="kembali" value="pemeriksaan"><div class="form-group"><label for="email_tujuan">Kirim email tes ke</label><input id="email_tujuan" name="email_tujuan" type="email" value="<?=h($user['email'] ?? '')?>" required maxlength="150"></div><button class="btn btn-primary" type="submit">Kirim Email Tes</button></form><?php endif; ?>
</section>
<?php endforeach; ?>
