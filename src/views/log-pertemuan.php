<?php
// Bagian dashboard mahasiswa: catat pertemuan bimbingan dan pantau konfirmasi dosen.
// Membutuhkan $guidances, $meetingLogs, $meetingCounts, dan $meetingDraft dari dashboard.php.
$meetingTarget = meeting_target();
$meetingStatusBadge = function (string $status): string {
    $map = ['menunggu' => ['warning', 'Menunggu konfirmasi'], 'dikonfirmasi' => ['success', 'Dikonfirmasi'], 'ditolak' => ['danger', 'Ditolak']];
    [$class, $label] = $map[$status] ?? ['secondary', $status];
    return '<span class="badge badge-' . $class . '">' . h($label) . '</span>';
};
?>
<section class="card" id="log-pertemuan"><div class="section-heading"><span class="eyebrow">PERTEMUAN</span><h2>Log Pertemuan Bimbingan</h2><p>Catat setiap pertemuan dengan dosen pembimbing. Pertemuan dihitung setelah dikonfirmasi dosen yang Anda temui, lalu tercetak di kartu bimbingan. Target minimal <?=$meetingTarget?> pertemuan.</p></div>
<?php foreach($guidances as $item): $guidanceId=(int)$item['id'];$confirmed=$meetingCounts[$guidanceId]??0;$logs=$meetingLogs[$guidanceId]??[];$team=p2_team($conn,$guidanceId);$percent=min(100,(int)round($confirmed/$meetingTarget*100));$draft=($meetingDraft['bimbingan_id']??0)===$guidanceId?$meetingDraft:[]; ?>
<div class="meeting-block">
<?php if(count($guidances)>1): ?><p class="progress-title"><?=h($item['judul_skripsi'])?></p><?php endif; ?>
<div class="meeting-meter<?=$confirmed>=$meetingTarget?' is-complete':''?>"><div class="meeting-meter-copy"><strong><?=$confirmed?> / <?=$meetingTarget?></strong><span><?=$confirmed>=$meetingTarget?'Target pertemuan minimal sudah terpenuhi.':'Pertemuan dikonfirmasi. Kurang '.($meetingTarget-$confirmed).' pertemuan lagi.'?></span></div><div class="meeting-meter-bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?=$meetingTarget?>" aria-valuenow="<?=min($confirmed,$meetingTarget)?>" aria-label="Pertemuan terkonfirmasi"><span style="width:<?=$percent?>%"></span></div></div>
<?php if(meeting_open_status((string)$item['status'])): ?>
<details class="meeting-form"<?=$draft?' open':''?>><summary class="btn btn-primary btn-small">Catat Pertemuan Baru</summary>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="record_meeting"><input type="hidden" name="bimbingan_id" value="<?=$guidanceId?>">
<div class="form-grid"><div class="form-group"><label for="meeting-dosen-<?=$guidanceId?>">Dosen yang ditemui</label><select id="meeting-dosen-<?=$guidanceId?>" name="dosen_id" required><?php if(count($team)>1): ?><option value="">Pilih dosen pembimbing</option><?php endif; ?><?php foreach($team as $teacher): ?><option value="<?=(int)$teacher['id']?>"<?=(int)($draft['dosen_id']??0)===(int)$teacher['id']?' selected':''?>>Pembimbing <?=(int)$teacher['urutan']?> — <?=h($teacher['nama_lengkap'])?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="meeting-date-<?=$guidanceId?>">Tanggal pertemuan</label><input id="meeting-date-<?=$guidanceId?>" name="tanggal" type="date" max="<?=date('Y-m-d')?>" min="<?=date('Y-m-d',strtotime('-365 days'))?>" value="<?=h($draft['tanggal']??date('Y-m-d'))?>" required></div></div>
<div class="form-group"><span class="form-label">Metode</span><div class="choice-row"><?php foreach(MEETING_METHODS as $methodValue=>$methodLabel): ?><label class="checkbox-line"><input type="radio" name="metode" value="<?=h($methodValue)?>"<?=($draft['metode']??'tatap_muka')===$methodValue?' checked':''?> required> <?=h($methodLabel)?></label><?php endforeach; ?></div></div>
<div class="form-group"><label for="meeting-topic-<?=$guidanceId?>">Topik yang dibahas</label><input id="meeting-topic-<?=$guidanceId?>" name="topik" minlength="5" maxlength="255" required placeholder="Contoh: Rumusan masalah Bab 1" value="<?=h($draft['topik']??'')?>"></div>
<div class="form-group"><label for="meeting-notes-<?=$guidanceId?>">Arahan dosen</label><textarea id="meeting-notes-<?=$guidanceId?>" name="catatan" minlength="10" maxlength="2000" rows="3" required placeholder="Ringkas arahan atau keputusan dari pertemuan ini."><?=h($draft['catatan']??'')?></textarea><small class="field-help">Dosen akan memeriksa catatan ini sebelum mengonfirmasi.</small></div>
<button class="btn btn-primary" type="submit">Kirim untuk Dikonfirmasi</button></form></details>
<?php elseif($logs===[]): ?><div class="upload-lock" role="status"><strong>Pencatatan pertemuan ditutup</strong><span>Pertemuan hanya dapat dicatat selama bimbingan berjalan.</span></div><?php endif; ?>
<?php if($logs): ?><div data-paginate="pertemuan"><?=paginate_tools('meeting-search-'.$guidanceId,'Cari tanggal, dosen, topik, atau status…','pertemuan')?><div class="table-wrap"><table class="meeting-table"><thead><tr><th>Tanggal</th><th>Dosen</th><th>Topik dan Arahan</th><th>Status</th></tr></thead><tbody>
<?php foreach($logs as $log): ?><tr data-paginate-item><td><strong><?=h(tanggal_id($log['tanggal']))?></strong><small class="table-subtext"><?=h(meeting_method_label((string)$log['metode']))?></small></td><td><?=h($log['dosen_nama'])?></td><td><strong><?=h($log['topik'])?></strong><small class="table-subtext meeting-notes"><?=nl2br(h($log['catatan']))?></small></td><td><?=$meetingStatusBadge((string)$log['status'])?><?php if($log['status']==='dikonfirmasi'&&$log['dikonfirmasi_at']): ?><small class="table-subtext"><?=h(tanggal_id($log['dikonfirmasi_at']))?></small><?php elseif($log['status']==='ditolak'&&$log['alasan_tolak']): ?><small class="table-subtext attention-text">Alasan: <?=h($log['alasan_tolak'])?></small><?php elseif($log['status']==='menunggu'): ?><form method="post" class="meeting-cancel" data-confirm-title="Batalkan log pertemuan?" data-confirm="Log pertemuan tanggal <?=h(tanggal_id($log['tanggal']))?> akan dihapus dan tidak lagi menunggu konfirmasi dosen." data-confirm-ok="Batalkan log"><?=csrf_field()?><input type="hidden" name="action" value="cancel_meeting"><input type="hidden" name="log_id" value="<?=(int)$log['id']?>"><button class="link-button" type="submit">Batalkan</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?=paginate_footer('pertemuan')?></div>
<?php else: ?><div class="empty-state"><strong>Belum ada pertemuan tercatat.</strong><span>Catat pertemuan pertama setelah bertemu dosen pembimbing.</span></div><?php endif; ?>
</div>
<?php endforeach; ?>
</section>
