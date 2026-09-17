<?php
require_login();
$id=(int)($_GET['id']??0); if(!$id) redirect('?page=dashboard');
require_once __DIR__.'/../controllers/BimbinganController.php';
$ctrl=new BimbinganController($conn); $b=$ctrl->getDetail($id);
if(!$b) { http_response_code(404); echo '<div class="card"><h2>Bimbingan tidak ditemukan</h2></div>'; return; }
$u=$_SESSION['user'];
if($u['role']==='mahasiswa' && (int)$b['mahasiswa_id']!==(int)$u['id']) { http_response_code(403); exit('Akses ditolak.'); }
if($u['role']==='dosen' && (int)$b['dosen_id']!==(int)$u['id']) { http_response_code(403); exit('Akses ditolak.'); }
$rows=$ctrl->getBabSkripsi($id); $progress=$ctrl->getProgress($id);
$babByName=[];
foreach($rows as $row){ $babByName[$row['nama_bab']][]=$row; }
?>
<div class="card"><a class="btn btn-secondary" style="float:right" href="?page=dashboard">Kembali</a><h2>Detail Bimbingan</h2><p><strong><?=e($b['judul_skripsi'])?></strong></p><p>Status: <?=e(ucfirst($b['status']))?></p><p class="muted">Alur bimbingan: Bab 1 → Bab 2 → Bab 3 → Bab 4 → Bab 5. Bimbingan selesai setelah Bab 5 mendapat ACC.</p></div>
<div class="card"><h3>Progress</h3><div style="background:#eee;border-radius:8px;height:24px;overflow:hidden"><div style="height:100%;width:<?=$progress?>%"></div></div><p style="text-align:center"><strong><?=round($progress)?>%</strong></p><p>Mahasiswa: <?=e($b['mahasiswa_nama'])?> · Dosen: <?=e($b['dosen_nama'])?></p></div>
<div class="card"><h3>Bab Skripsi</h3>
<?php for($n=1;$n<=5;$n++): $name='Bab '.$n; $history=$babByName[$name]??[]; ?>
<section style="margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #eee"><h4><?=$name?></h4>
<?php if(!$history): ?><p class="muted">Belum diunggah.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Versi</th><th>Status</th><th>Revisi</th><th>Diunggah</th><th>File</th></tr></thead><tbody>
<?php foreach($history as $row): ?><tr><td>v<?=e($row['versi'])?></td><td><?=e(ucfirst(str_replace('_',' ',$row['status'])))?></td><td><?=e($row['total_revisi'])?></td><td><?=e(formatDateTime($row['uploaded_at']))?></td><td><a class="btn btn-primary" target="_blank" rel="noopener" href="download.php?id=<?=$row['id']?>">Buka</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php endfor; ?>
</div>
