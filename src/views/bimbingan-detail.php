<?php
require_login();
$id=(int)($_GET['id']??0); if(!$id) redirect('?page=dashboard');
require_once __DIR__.'/../controllers/BimbinganController.php';
$ctrl=new BimbinganController($conn); $b=$ctrl->getDetail($id);
if(!$b) { http_response_code(404); echo '<div class="card"><h2>Bimbingan tidak ditemukan</h2></div>'; return; }
$u=$_SESSION['user'];
if($u['role']==='mahasiswa' && (int)$b['mahasiswa_id']!==(int)$u['id']) { http_response_code(403); exit('Akses ditolak.'); }
if($u['role']==='dosen' && (int)$b['dosen_id']!==(int)$u['id']) { http_response_code(403); exit('Akses ditolak.'); }
$bab=$ctrl->getBabSkripsi($id); $progress=$ctrl->getProgress($id);
?>
<div class="card"><a class="btn btn-secondary" style="float:right" href="?page=dashboard">Kembali</a><h2>Detail Bimbingan</h2><p><strong><?=e($b['judul_skripsi'])?></strong></p><p>Status: <?=e(ucfirst($b['status']))?></p></div>
<div class="card"><h3>Progress</h3><div style="background:#eee;border-radius:8px;height:24px;overflow:hidden"><div style="height:100%;width:<?=$progress?>%;background:#27ae60"></div></div><p style="text-align:center"><strong><?=round($progress)?>%</strong></p><p>Mahasiswa: <?=e($b['mahasiswa_nama'])?> · Dosen: <?=e($b['dosen_nama'])?></p></div>
<div class="card"><h3>Bab Skripsi</h3><?php if(!$bab): ?><p>Belum ada bab yang diunggah.</p><?php else: ?><table><tr><th>Bab</th><th>Versi</th><th>Status</th><th>Revisi</th><th>File</th></tr><?php foreach($bab as $row): ?><tr><td><?=e($row['nama_bab'])?></td><td><?=e($row['versi'])?></td><td><?=e(ucfirst(str_replace('_',' ',$row['status'])))?></td><td><?=e($row['total_revisi'])?></td><td><a class="btn btn-primary" target="_blank" href="<?=e($row['file_path'])?>">Buka</a></td></tr><?php endforeach; ?></table><?php endif; ?></div>
