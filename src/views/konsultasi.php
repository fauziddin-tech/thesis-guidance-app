<?php
require_login();require_once __DIR__.'/../controllers/KonsultasiController.php';
$u=$_SESSION['user'];$uid=(int)$u['id'];$role=$u['role'];$ctrl=new KonsultasiController($conn);
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=$_POST['action']??'';
 if($action==='create_konsultasi'&&$role==='mahasiswa'){
  $bid=(int)($_POST['bimbingan_id']??0);$topik=trim($_POST['topik']??'');$desc=trim($_POST['deskripsi']??'');$ok=$ctrl->create($uid,$bid,$topik,$desc);
  if($ok){$s=$conn->prepare('SELECT dosen_id FROM bimbingan WHERE id=?');$s->bind_param('i',$bid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if($r)notify_user($conn,(int)$r['dosen_id'],'konsultasi_baru','Ada konsultasi baru dari '.e($u['nama_lengkap']).'.','?page=konsultasi');flash('success','Konsultasi berhasil dikirim.');}else flash('danger','Konsultasi gagal dibuat.');
  redirect('?page=konsultasi');
 }
 if($action==='answer_konsultasi'&&$role==='dosen'){
  $id=(int)($_POST['konsultasi_id']??0);$jawaban=trim($_POST['jawaban']??'');$status=$_POST['status']??'';$ok=$ctrl->answer($uid,$id,$jawaban,$status);$owners=$ok?$ctrl->getOwnerIds($id):null;
  if($ok&&$owners)notify_user($conn,(int)$owners['mahasiswa_id'],'konsultasi_dijawab','Konsultasi Anda telah dijawab oleh dosen.','?page=konsultasi');flash($ok?'success':'danger',$ok?'Jawaban berhasil disimpan.':'Jawaban gagal disimpan.');redirect('?page=konsultasi');
 }
}
$items=$ctrl->getForUser($uid,$role);$bimbingan=[];
if($role==='mahasiswa'){$s=$conn->prepare("SELECT id,judul_skripsi FROM bimbingan WHERE mahasiswa_id=? AND status='aktif' ORDER BY created_at DESC");$s->bind_param('i',$uid);$s->execute();$bimbingan=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();}
?>
<div class="page-head"><div><h1>Konsultasi</h1><p class="muted">Ajukan pertanyaan dan simpan riwayat diskusi bimbingan.</p></div></div>
<?php if($role==='mahasiswa'): ?><section class="card"><h2>Ajukan Konsultasi</h2><form method="post"><input type="hidden" name="action" value="create_konsultasi"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="form-group"><label>Bimbingan</label><select name="bimbingan_id" required><option value="">-- Pilih bimbingan --</option><?php foreach($bimbingan as $b): ?><option value="<?=$b['id']?>"><?=e($b['judul_skripsi'])?></option><?php endforeach; ?></select></div><div class="form-group"><label>Topik</label><input name="topik" maxlength="255" required></div><div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" required></textarea></div><button class="btn btn-primary">Kirim Konsultasi</button></form></section><?php endif; ?>
<section class="card"><h2>Riwayat Konsultasi</h2><?php if(!$items): ?><p class="muted">Belum ada konsultasi.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Topik</th><th><?=($role==='mahasiswa'?'Dosen':'Mahasiswa')?></th><th>Status</th><th>Jawaban</th></tr></thead><tbody><?php foreach($items as $k): ?><tr><td><?=e(formatDateTime($k['created_at']))?></td><td><?=e($k['topik'])?><br><small><?=e($k['deskripsi']??'')?></small></td><td><?=e($role==='mahasiswa'?$k['dosen_nama']:$k['mahasiswa_nama'])?></td><td><?=getStatusBadge($k['status'])?></td><td><?=e($k['jawaban']??'Belum dijawab.')?><?php if($role==='dosen'&&$k['status']==='pending'): ?><form method="post" style="margin-top:8px"><input type="hidden" name="action" value="answer_konsultasi"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="konsultasi_id" value="<?=$k['id']?>"><textarea name="jawaban" required placeholder="Tulis jawaban..."></textarea><select name="status"><option value="diterima">Terima / Jawab</option><option value="ditolak">Tolak</option></select><button class="btn btn-success" type="submit">Simpan</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
