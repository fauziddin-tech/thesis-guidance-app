<?php
// Bagian dashboard admin: status backup, buat backup sekarang, dan unduh file backup.
$backupFiles = backup_list();
$backupState = backup_status();
$backupSize = function (int $bytes): string {
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : number_format($bytes / 1024, 1, ',', '.') . ' KB';
};
$backupKeepDays = defined('BACKUP_KEEP_DAYS') ? max(1, (int)BACKUP_KEEP_DAYS) : 14;
?>
<section class="card" id="backup-database"><div class="section-heading"><span class="eyebrow">CADANGAN</span><h2>Backup Database</h2><p>Backup otomatis berjalan lewat Cron Jobs; Anda juga dapat membuatnya sekarang. File disimpan di luar folder situs dan dihapus otomatis setelah <?=$backupKeepDays?> hari.</p></div>
<?php if(!$backupState): ?><div class="alert alert-warning" role="status">Backup belum pernah dijalankan.</div>
<?php elseif(empty($backupState['ok'])): ?><div class="alert alert-danger" role="status">Backup terakhir gagal (<?=h(tanggal_id((string)$backupState['waktu'], true))?>): <?=h($backupState['pesan'] ?? 'lihat error_log')?></div>
<?php else: ?><p class="backup-last">Backup terakhir <strong><?=h(tanggal_id((string)$backupState['waktu'], true))?></strong> — <?=h($backupSize((int)$backupState['ukuran']))?>, <?=(int)$backupState['tabel']?> tabel, <?=(int)($backupState['baris'] ?? 0)?> baris<?=str_starts_with((string)($backupState['pemicu'] ?? 'cron'), 'admin')?' (dibuat dari dashboard)':''?>.</p><?php endif; ?>
<form method="post" class="backup-actions" data-confirm-title="Buat backup sekarang?" data-confirm="Seluruh isi database akan disalin ke file backup baru. Proses ini biasanya selesai dalam beberapa detik." data-confirm-ok="Buat backup"><?=csrf_field()?><input type="hidden" name="action" value="run_backup"><button class="btn btn-primary" type="submit">Buat Backup Sekarang</button></form>
<?php if($backupFiles): ?><div data-paginate="backup"><?=paginate_tools('backup-search','Cari tanggal backup, mis. 20260924…','backup')?><div class="table-wrap"><table><thead><tr><th>File</th><th>Dibuat</th><th>Ukuran</th><th>Unduh</th></tr></thead><tbody>
<?php foreach($backupFiles as $index=>$file): ?><tr data-paginate-item><td><code><?=h($file['nama'])?></code><?php if($index===0): ?> <span class="badge badge-success">Terbaru</span><?php endif; ?></td><td><?=h(tanggal_id(date('Y-m-d H:i:s', $file['waktu']), true))?></td><td><?=h($backupSize($file['ukuran']))?></td><td><a class="btn btn-secondary btn-small" href="?action=download_backup&amp;file=<?=rawurlencode($file['nama'])?>" download>Unduh</a></td></tr><?php endforeach; ?>
</tbody></table></div><?=paginate_footer('backup')?></div>
<p class="field-help">File berformat <code>.sql.gz</code> berisi seluruh data, termasuk email dan password terenkripsi pengguna. Simpan di tempat aman dan jangan dibagikan. Untuk memulihkan, impor file ini melalui phpMyAdmin (tab Impor) pada database kosong.</p>
<?php else: ?><div class="empty-state"><strong>Belum ada file backup.</strong><span>Klik Buat Backup Sekarang atau periksa jadwal Cron Jobs di cPanel.</span></div><?php endif; ?>
</section>
