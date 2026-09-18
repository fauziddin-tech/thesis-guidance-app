<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/src/helpers/Functions.php';
require_login();

$uid=(int)$_SESSION['user']['id'];
$format=$_GET['format']??'pdf';
if(!in_array($format,['word','pdf'],true))$format='pdf';

$s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.deskripsi,b.status,b.created_at,b.updated_at,u.nama_lengkap dosen_nama
                   FROM bimbingan b JOIN users u ON u.id=b.dosen_id
                   WHERE b.mahasiswa_id=? ORDER BY b.created_at DESC");
$s->bind_param('i',$uid);$s->execute();$bimbingan=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

$bab=[];
$rev=[];
foreach($bimbingan as $b){
  $bs=$conn->prepare("SELECT id,nama_bab,versi,status,uploaded_at FROM bab_skripsi WHERE bimbingan_id=? ORDER BY nama_bab,versi");
  $bs->bind_param('i',$b['id']);$bs->execute();$bab[(int)$b['id']]=$bs->get_result()->fetch_all(MYSQLI_ASSOC);$bs->close();
  $rs=$conn->prepare("SELECT r.komentar,r.tipe_revisi,r.created_at,bs.nama_bab,bs.versi,d.nama_lengkap dosen_nama
                      FROM revisi r JOIN bab_skripsi bs ON bs.id=r.bab_id JOIN users d ON d.id=r.dosen_id
                      WHERE bs.bimbingan_id=? ORDER BY r.created_at");
  $rs->bind_param('i',$b['id']);$rs->execute();$rev[(int)$b['id']]=$rs->get_result()->fetch_all(MYSQLI_ASSOC);$rs->close();
}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function status_text($s){
  $map=['pengajuan_judul'=>'Pengajuan Judul','revisi_judul'=>'Revisi Judul','aktif'=>'Aktif','selesai'=>'Selesai','ditangguhkan'=>'Ditangguhkan','draft'=>'Draft','menunggu_review'=>'Menunggu Review','direvisi'=>'Direvisi','disetujui'=>'Disetujui'];
  return $map[$s]??$s;
}

if($format==='word'){
  $name='riwayat-bimbingan-'.date('Y-m-d').'.doc';
  header('Content-Type: application/msword; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$name.'"');
} else {
  $name='riwayat-bimbingan-'.date('Y-m-d').'.html';
  header('Content-Type: text/html; charset=UTF-8');
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Riwayat Bimbingan</title>
<style>
body{font-family:Arial,sans-serif;color:#222;line-height:1.5;margin:32px;font-size:12pt}
h1{font-size:20pt;margin-bottom:4px}h2{font-size:15pt;margin-top:28px;border-bottom:1px solid #999;padding-bottom:5px}
h3{font-size:13pt;margin-bottom:5px}.muted{color:#666}.meta{margin:8px 0 18px}
table{width:100%;border-collapse:collapse;margin:10px 0 18px}th,td{border:1px solid #aaa;padding:7px;text-align:left;vertical-align:top}th{background:#eee}
.print-actions{margin-bottom:24px}@media print{.print-actions{display:none}body{margin:18mm}}
</style></head><body>
<div class="print-actions"><button onclick="window.print()">Cetak / Simpan sebagai PDF</button></div>
<h1>Riwayat Bimbingan Skripsi</h1>
<p class="muted">Dokumen riwayat proses bimbingan melalui MyThesis.</p>
<?php foreach($bimbingan as $b): ?>
<h2><?=h($b['judul_skripsi'])?></h2>
<div class="meta">
<strong>Mahasiswa:</strong> <?=h($_SESSION['user']['nama_lengkap'])?><br>
<strong>Dosen Pembimbing:</strong> <?=h($b['dosen_nama'])?><br>
<strong>Status:</strong> <?=h(status_text($b['status']))?><br>
<strong>Dibuat:</strong> <?=h(formatDateTime($b['created_at']))?> &nbsp; <strong>Diperbarui:</strong> <?=h(formatDateTime($b['updated_at']))?>
</div>
<?php if(!empty($b['deskripsi'])): ?><p><strong>Deskripsi:</strong><br><?=nl2br(h($b['deskripsi']))?></p><?php endif; ?>
<h3>Riwayat Dokumen Bab</h3>
<?php if(empty($bab[(int)$b['id']])): ?><p class="muted">Belum ada dokumen bab.</p><?php else: ?>
<table><thead><tr><th>Bab</th><th>Versi</th><th>Status</th><th>Waktu Upload</th></tr></thead><tbody>
<?php foreach($bab[(int)$b['id']] as $x): ?><tr><td><?=h($x['nama_bab'])?></td><td>v<?=h($x['versi'])?></td><td><?=h(status_text($x['status']))?></td><td><?=h(formatDateTime($x['uploaded_at']))?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
<h3>Catatan Revisi Dosen</h3>
<?php if(empty($rev[(int)$b['id']])): ?><p class="muted">Belum ada catatan revisi.</p><?php else: ?>
<table><thead><tr><th>Bab</th><th>Versi</th><th>Tipe</th><th>Catatan</th><th>Dosen</th><th>Waktu</th></tr></thead><tbody>
<?php foreach($rev[(int)$b['id']] as $x): ?><tr><td><?=h($x['nama_bab'])?></td><td>v<?=h($x['versi'])?></td><td><?=h(ucfirst($x['tipe_revisi']))?></td><td><?=nl2br(h($x['komentar']))?></td><td><?=h($x['dosen_nama'])?></td><td><?=h(formatDateTime($x['created_at']))?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
<?php endforeach; ?>
<p class="muted" style="margin-top:30px">Dicetak dari MyThesis pada <?=h(date('d-m-Y H:i'))?>.</p>
</body></html>