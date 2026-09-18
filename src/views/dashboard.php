<?php
$user=$_SESSION['user']; $uid=(int)$user['id']; $role=$user['role'];
?>
<div class="page-head">
  <div><h1>Dashboard</h1><p class="muted">Ringkasan bimbingan dan perkembangan skripsi Anda.</p></div>
  <span class="role-badge"><?=e(ucfirst($role))?></span>
</div>

<div class="card">
  <div class="section-heading"><h2>Alur Bimbingan</h2><p class="muted">Pengajuan judul merupakan tahap awal sebelum Bab 1 sampai Bab 5.</p></div>
  <div class="workflow">
    <div class="workflow-step">Pengajuan Judul</div>
    <?php for($i=1;$i<=5;$i++): ?><div class="workflow-step">Bab <?=$i?></div><?php endfor; ?>
  </div>
  <div class="hint">Status <strong>Menunggu Review</strong> berarti sedang diperiksa dosen. Status <strong>Direvisi</strong> berarti mahasiswa dapat mengunggah versi berikutnya. Setelah <strong>Bab 5 disetujui</strong>, bimbingan otomatis selesai.</div>
</div>

<?php if($role==='mahasiswa'): ?>
<?php
$s=$conn->prepare("SELECT id FROM bimbingan WHERE mahasiswa_id=? AND status IN ('aktif','pengajuan_judul') LIMIT 1"); $s->bind_param('i',$uid); $s->execute(); $hasActive=$s->get_result()->num_rows>0; $s->close();
?>
<div class="grid-2">
<section class="card">
  <h2><?= $hasActive ? 'Pengajuan / Bimbingan' : 'Pengajuan Judul Skripsi' ?></h2>
  <?php if($hasActive): ?>
    <?php $s=$conn->prepare("SELECT id,judul_skripsi,status FROM bimbingan WHERE mahasiswa_id=? AND status IN ('aktif','pengajuan_judul') LIMIT 1");$s->bind_param('i',$uid);$s->execute();$active=$s->get_result()->fetch_assoc();$s->close(); ?>
    <?php if($active): ?>
      <div class="meta-item"><div class="meta-label">Judul Skripsi</div><div class="meta-value"><?=e($active['judul_skripsi'])?></div></div>
      <div class="meta-item"><div class="meta-label">Status</div><div class="meta-value"><?=getStatusBadge($active['status'])?></div></div>
      <?php if($active['status']==='pengajuan_judul'): ?><p class="muted">Judul sedang menunggu persetujuan dosen. Setelah disetujui, Bab 1 dapat diunggah.</p><?php endif; ?>
      <div class="actions"><a class="btn btn-primary" href="?page=bimbingan-detail&id=<?=$active['id']?>">Buka Detail</a></div>
    <?php endif; ?>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="action" value="create_bimbingan"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <div class="form-group"><label>Dosen Pembimbing</label><select name="dosen_id" required><option value="">Pilih dosen</option><?php $pref=$conn->prepare("SELECT dosen_pembimbing_id FROM users WHERE id=? LIMIT 1");$pref->bind_param('i',$uid);$pref->execute();$preferred=(int)($pref->get_result()->fetch_assoc()['dosen_pembimbing_id']??0);$pref->close();$r=$conn->query("SELECT id,nama_lengkap FROM users WHERE role='dosen' ORDER BY nama_lengkap");while($d=$r->fetch_assoc()): ?><option value="<?=$d['id']?>" <?=$preferred===(int)$d['id']?'selected':''?>><?=e($d['nama_lengkap'])?></option><?php endwhile; ?></select><small class="muted">Dosen yang dipilih saat pendaftaran akan terpilih otomatis.</small></div>
      <div class="form-group"><label>Judul Skripsi</label><input name="judul_skripsi" maxlength="255" required></div>
      <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" placeholder="Deskripsi singkat mengenai topik skripsi"></textarea></div>
      <button class="btn btn-primary">Simpan Bimbingan</button>
    </form>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Unggah Bab</h2><p class="muted">Format PDF, DOC, atau DOCX. Maksimal 10 MB. Bab berikutnya hanya dapat diunggah setelah bab sebelumnya mendapat ACC.</p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload_bab"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <div class="form-group"><label>Bimbingan</label><select name="bimbingan_id" required><option value="">Pilih bimbingan</option><?php $s=$conn->prepare("SELECT id,judul_skripsi FROM bimbingan WHERE mahasiswa_id=? AND status='aktif' ORDER BY created_at DESC");$s->bind_param('i',$uid);$s->execute();$r=$s->get_result();while($b=$r->fetch_assoc()): ?><option value="<?=$b['id']?>"><?=e($b['judul_skripsi'])?></option><?php endwhile;$s->close(); ?></select></div>
    <div class="form-group"><label>Bab</label><select name="nama_bab" required><option value="">Pilih bab</option><?php for($i=1;$i<=5;$i++): ?><option value="Bab <?=$i?>">Bab <?=$i?></option><?php endfor; ?></select></div>
    <div class="form-group"><label>File Skripsi</label><input type="file" name="file" accept=".pdf,.doc,.docx" required></div>
    <button class="btn btn-primary">Unggah Bab</button>
  </form>
</section>
</div>

<section class="card">
  <div class="section-heading"><h2>Riwayat Bimbingan</h2><p class="muted">Daftar seluruh pengajuan bimbingan Anda.</p></div>
  <?php $s=$conn->prepare("SELECT b.*,u.nama_lengkap dosen_nama FROM bimbingan b JOIN users u ON u.id=b.dosen_id WHERE b.mahasiswa_id=? ORDER BY b.created_at DESC");$s->bind_param('i',$uid);$s->execute();$r=$s->get_result();if(!$r->num_rows): ?><div class="empty-state">Belum ada bimbingan.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Judul Skripsi</th><th>Dosen</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php while($b=$r->fetch_assoc()): ?><tr><td><?=e($b['judul_skripsi'])?></td><td><?=e($b['dosen_nama'])?></td><td><?=getStatusBadge($b['status'])?></td><td><a class="btn btn-secondary" href="?page=bimbingan-detail&id=<?=$b['id']?>">Lihat Detail</a></td></tr><?php endwhile; ?></tbody></table></div><?php endif;$s->close(); ?>
</section>

<?php elseif($role==='dosen'): ?>
<section class="card">
  <div class="section-heading"><h2>Pengajuan Judul</h2><p class="muted">Setujui judul mahasiswa sebelum mereka dapat mengunggah Bab 1.</p></div>
  <?php $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.deskripsi,b.created_at,u.nama_lengkap mahasiswa_nama FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND b.status='pengajuan_judul' ORDER BY b.created_at ASC");$s->bind_param('i',$uid);$s->execute();$judulRows=$s->get_result();if(!$judulRows->num_rows): ?><div class="empty-state">Belum ada pengajuan judul.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Judul</th><th>Deskripsi</th><th>Aksi</th></tr></thead><tbody><?php while($j=$judulRows->fetch_assoc()): ?><tr><td><?=e($j['mahasiswa_nama'])?></td><td><strong><?=e($j['judul_skripsi'])?></strong></td><td><?=e($j['deskripsi']?:'-')?></td><td><form method="post" class="inline-form"><input type="hidden" name="action" value="approve_judul"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="bimbingan_id" value="<?=e($j['id'])?>"><button class="btn btn-success" type="submit">Setujui Judul</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif;$s->close(); ?>
</section>

<section class="card">
  <div class="section-heading"><h2>Mahasiswa Bimbingan</h2><p class="muted">Pantau status bimbingan dan buka detail untuk melihat riwayat bab.</p></div>
  <?php $s=$conn->prepare("SELECT b.*,u.nama_lengkap mahasiswa_nama,u.email FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? ORDER BY b.updated_at DESC");$s->bind_param('i',$uid);$s->execute();$r=$s->get_result();if(!$r->num_rows): ?><div class="empty-state">Belum ada mahasiswa bimbingan.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Judul Skripsi</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php while($b=$r->fetch_assoc()): ?><tr><td><strong><?=e($b['mahasiswa_nama'])?></strong><br><small><?=e($b['email'])?></small></td><td><?=e($b['judul_skripsi'])?></td><td><?=getStatusBadge($b['status'])?></td><td><a class="btn btn-secondary" href="?page=bimbingan-detail&id=<?=$b['id']?>">Lihat Detail</a></td></tr><?php endwhile; ?></tbody></table></div><?php endif;$s->close(); ?>
</section>

<section class="card">
  <div class="section-heading"><h2>Review dan Revisi</h2><p class="muted">Pilih versi terbaru yang berstatus Menunggu Review untuk memberikan catatan revisi.</p></div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_revision"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <div class="form-group"><label>Bab</label><select name="bab_id" required><option value="">Pilih bab</option><?php $s=$conn->prepare("SELECT bs.id,bs.nama_bab,bs.versi,b.id bimbingan_id,u.nama_lengkap FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND b.status='aktif' AND bs.status='menunggu_review' AND bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=bs.bimbingan_id AND x.nama_bab=bs.nama_bab ORDER BY x.versi DESC,x.id DESC LIMIT 1) ORDER BY bs.uploaded_at DESC");$s->bind_param('i',$uid);$s->execute();$r=$s->get_result();while($b=$r->fetch_assoc()): ?><option value="<?=$b['id']?>" data-bimbingan-id="<?=$b['bimbingan_id']?>"><?=e($b['nama_bab'].' v'.$b['versi'].' — '.$b['nama_lengkap'])?></option><?php endwhile;$s->close(); ?></select></div>
    <input type="hidden" name="bimbingan_id" id="revision_bimbingan_id" value="">
    <div class="form-group"><label>Komentar / Catatan Revisi</label><textarea name="komentar" required placeholder="Tuliskan bagian yang perlu diperbaiki secara jelas"></textarea></div>
    <div class="form-group"><label>Tipe Revisi</label><select name="tipe_revisi"><option value="minor">Minor</option><option value="major">Major</option><option value="kritis">Kritis</option></select></div>
    <div class="form-group"><label>File Revisi dari Dosen</label><input type="file" name="file_revisi" accept=".pdf,.doc,.docx"><small class="muted">Opsional. Maksimal 10 MB. Dapat berupa dokumen dengan koreksi/catatan dosen.</small></div>
    <button class="btn btn-primary">Kirim Komentar dan Revisi</button>
  </form>
</section>

<section class="card">
  <div class="section-heading"><h2>Persetujuan Bab</h2><p class="muted">ACC hanya tersedia untuk versi terbaru yang sedang menunggu review.</p></div>
  <?php $s=$conn->prepare("SELECT bs.id,bs.nama_bab,bs.versi,bs.status,b.id bimbingan_id,u.nama_lengkap FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND b.status='aktif' AND bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=bs.bimbingan_id AND x.nama_bab=bs.nama_bab ORDER BY x.versi DESC,x.id DESC LIMIT 1) ORDER BY bs.uploaded_at DESC");$s->bind_param('i',$uid);$s->execute();$r=$s->get_result();if(!$r->num_rows): ?><div class="empty-state">Belum ada bab untuk diproses.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Bab</th><th>Mahasiswa</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php while($b=$r->fetch_assoc()): ?><tr><td><strong><?=e($b['nama_bab'])?></strong><br><small>Versi <?=e($b['versi'])?></small></td><td><?=e($b['nama_lengkap'])?></td><td><?=getStatusBadge($b['status'])?></td><td><?php if($b['status']==='menunggu_review'): ?><form method="post" class="inline-form"><input type="hidden" name="action" value="approve_bab"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="bab_id" value="<?=e($b['id'])?>"><button class="btn btn-success" type="submit">Setujui Bab</button></form><?php elseif($b['status']==='direvisi'): ?><span class="status-note">Menunggu revisi mahasiswa</span><?php elseif($b['status']==='disetujui'): ?><span class="status-note">Sudah disetujui</span><?php else: ?><span class="status-note">-</span><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div><?php endif;$s->close(); ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const bab = document.querySelector('select[name="bab_id"]');
  const target = document.getElementById('revision_bimbingan_id');
  if (!bab || !target) return;
  function syncBimbingan() {
    const option = bab.options[bab.selectedIndex];
    target.value = option ? (option.dataset.bimbinganId || '') : '';
  }
  bab.addEventListener('change', syncBimbingan);
  syncBimbingan();
});
</script>
<?php endif; ?>