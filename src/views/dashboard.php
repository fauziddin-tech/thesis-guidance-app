<?php
$user=$_SESSION['user']; $uid=(int)$user['id']; $role=$user['role'];
$unreadCount=0;
$ns=$conn->prepare("SELECT COUNT(*) total FROM notifikasi WHERE user_id=? AND dibaca=0");
if($ns){$ns->bind_param('i',$uid);$ns->execute();$unreadCount=(int)($ns->get_result()->fetch_assoc()['total']??0);$ns->close();}
?>
<div class="page-head">
  <div><h1>Dashboard</h1><p class="muted">Ringkasan bimbingan dan perkembangan skripsi Anda.</p></div>
  <span class="role-badge"><?=e(ucfirst($role))?></span>
</div>

<section class="card research-flow-card">
  <div class="section-heading">
    <h2>Alur Penelitian</h2>
    <p class="muted">Klik setiap tahap untuk melihat halaman dan status proses penelitian.</p>
  </div>
  <?php
  $flowBimbinganId=0;$flowStatus='belum_ada';
  if($role==='mahasiswa'){
    $fs=$conn->prepare("SELECT id,status FROM bimbingan WHERE mahasiswa_id=? ORDER BY created_at DESC LIMIT 1");
    $fs->bind_param('i',$uid);$fs->execute();$fr=$fs->get_result()->fetch_assoc();$fs->close();
    if($fr){$flowBimbinganId=(int)$fr['id'];$flowStatus=$fr['status'];}
  }
  ?>
  <div class="research-flow">
    <a class="research-flow-item <?=($flowStatus==='pengajuan_judul'||$flowStatus==='belum_ada')?'is-current':''?>" href="<?= $flowBimbinganId ? '?page=bimbingan-detail&id='.$flowBimbinganId : '?page=dashboard' ?>">
      <span class="flow-number">01</span><span><strong>Pengajuan Judul</strong><small><?= $flowStatus==='pengajuan_judul'?'Menunggu persetujuan dosen':($flowStatus==='belum_ada'?'Belum diajukan':'Judul disetujui') ?></small></span>
    </a>
    <?php for($i=1;$i<=5;$i++): ?>
      <?php
      $flowBabStatus='Belum dimulai';
      if($flowBimbinganId){
        $fb=$conn->prepare("SELECT status FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=? ORDER BY versi DESC,id DESC LIMIT 1");
        $bn='Bab '.$i;$fb->bind_param('is',$flowBimbinganId,$bn);$fb->execute();$fbr=$fb->get_result()->fetch_assoc();$fb->close();
        if($fbr){$flowBabStatus=getStatusBadge($fbr['status']);}
        elseif($flowStatus==='pengajuan_judul'||$flowStatus==='belum_ada'){$flowBabStatus='Menunggu judul disetujui';}
      }
      ?>
      <a class="research-flow-item" href="<?= $flowBimbinganId ? '?page=bimbingan-detail&id='.$flowBimbinganId : '?page=dashboard' ?>">
        <span class="flow-number">0<?=($i+1)?></span><span><strong>Bab <?=$i?></strong><small><?=$flowBabStatus?></small></span>
      </a>
    <?php endfor; ?>
  </div>
  <div class="hint">Setiap tahap membuka detail bimbingan yang sesuai. Bab berikutnya hanya dapat diproses setelah bab sebelumnya mendapat ACC.</div>
</section>

<?php if($role==='mahasiswa'): ?>
<?php
$s=$conn->prepare("SELECT id FROM bimbingan WHERE mahasiswa_id=? AND status IN ('aktif','pengajuan_judul','revisi_judul') LIMIT 1"); $s->bind_param('i',$uid); $s->execute(); $hasActive=$s->get_result()->num_rows>0; $s->close();
?>
<div class="grid-2">
<section class="card">
  <h2>Pengajuan Judul Skripsi</h2>
  <?php if($hasActive): ?>
    <?php $s=$conn->prepare("SELECT id,judul_skripsi,deskripsi,status,judul_revision_catatan FROM bimbingan WHERE mahasiswa_id=? AND status IN ('aktif','pengajuan_judul','revisi_judul') LIMIT 1");$s->bind_param('i',$uid);$s->execute();$active=$s->get_result()->fetch_assoc();$s->close(); ?>
    <?php if($active): ?>
      <div class="meta-item"><div class="meta-label">Judul Skripsi</div><div class="meta-value"><?=e($active['judul_skripsi'])?></div></div>
      <div class="meta-item"><div class="meta-label">Status</div><div class="meta-value"><?=getStatusBadge($active['status'])?></div></div>
      <?php if($active['status']==='pengajuan_judul'): ?><p class="muted">Judul sedang menunggu persetujuan dosen. Setelah disetujui, tahap Bab 1 akan terbuka.</p><?php elseif($active['status']==='revisi_judul'): ?>
      <div class="review-panel" style="margin-top:14px;">
        <h4>Revisi Judul dari Dosen</h4>
        <p><?=nl2br(e($active['judul_revision_catatan']??'Silakan perbaiki judul skripsi sesuai arahan dosen.'))?></p>
        <form method="post">
          <input type="hidden" name="action" value="submit_judul_revision"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
          <input type="hidden" name="bimbingan_id" value="<?=e($active['id'])?>">
          <div class="form-group"><label>Judul Skripsi Baru</label><input name="judul_skripsi" maxlength="255" value="<?=e($active['judul_skripsi'])?>" required></div>
          <div class="form-group"><label>Penjelasan Perubahan</label><textarea name="deskripsi" placeholder="Jelaskan perubahan judul atau alasan penyesuaian."><?=e($active['deskripsi']??'')?></textarea></div>
          <button class="btn btn-primary" type="submit">Kirim Ulang Judul</button>
        </form>
      </div>
      <?php endif; ?>
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
  <div class="section-heading">
    <h2>Mahasiswa Bimbingan</h2>
    <p class="muted">Daftar mahasiswa yang menjadi bimbingan Anda. Pilih <strong>Detail Bimbingan</strong> untuk melihat alur, dokumen, riwayat review, dan proses ACC.</p>
  </div>
  <?php
  $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.status,b.updated_at,u.nama_lengkap mahasiswa_nama,u.email
                     FROM bimbingan b
                     JOIN users u ON u.id=b.mahasiswa_id
                     WHERE b.dosen_id=?
                     ORDER BY u.nama_lengkap ASC, b.updated_at DESC");
  $s->bind_param('i',$uid);$s->execute();$r=$s->get_result();
  if(!$r->num_rows):
  ?>
    <div class="empty-state">Belum ada mahasiswa bimbingan.</div>
  <?php else: ?>
    <div class="student-list">
      <?php while($b=$r->fetch_assoc()): ?>
        <article class="student-card">
          <div class="student-card-main">
            <div class="student-index">MAHASISWA</div>
            <h3><?=e($b['mahasiswa_nama'])?></h3>
            <p class="student-email"><?=e($b['email'])?></p>
            <div class="student-title">
              <span class="meta-label">Judul Skripsi</span>
              <strong><?=e($b['judul_skripsi'])?></strong>
            </div>
          </div>
          <div class="student-card-side">
            <div><?=getStatusBadge($b['status'])?></div>
            <small class="muted">Diperbarui <?=e(formatDateTime($b['updated_at']))?></small>
            <a class="btn btn-primary" href="?page=bimbingan-detail&id=<?=e($b['id'])?>">Detail Bimbingan</a>
          </div>
        </article>
      <?php endwhile; ?>
    </div>
  <?php endif; $s->close(); ?>
</section>

<section class="card">
  <div class="section-heading">
    <h2>Pengajuan Judul</h2>
    <p class="muted">Pengajuan judul yang menunggu keputusan dosen.</p>
  </div>
  <?php
  $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.deskripsi,u.nama_lengkap mahasiswa_nama
                     FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id
                     WHERE b.dosen_id=? AND b.status='pengajuan_judul'
                     ORDER BY b.created_at ASC");
  $s->bind_param('i',$uid);$s->execute();$judulRows=$s->get_result();
  if(!$judulRows->num_rows):
  ?>
    <div class="empty-state">Belum ada pengajuan judul yang menunggu keputusan.</div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Judul</th><th>Deskripsi</th><th>Aksi</th></tr></thead><tbody>
    <?php while($j=$judulRows->fetch_assoc()): ?>
      <tr>
        <td><strong><?=e($j['mahasiswa_nama'])?></strong></td>
        <td><?=e($j['judul_skripsi'])?></td>
        <td><?=e($j['deskripsi']?:'-')?></td>
        <td>
          <div class="actions">
            <form method="post" class="inline-form">
              <input type="hidden" name="action" value="approve_judul"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="bimbingan_id" value="<?=e($j['id'])?>">
              <button class="btn btn-success" type="submit">Setujui Judul</button>
            </form>
            <button class="btn btn-warning" type="button" data-title-revision="<?=e($j['id'])?>">Minta Revisi Judul</button>
          </div>
          <form method="post" class="title-revision-form" id="title-revision-<?=e($j['id'])?>" style="display:none;margin-top:12px;">
            <input type="hidden" name="action" value="revise_judul"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="bimbingan_id" value="<?=e($j['id'])?>">
            <div class="form-group"><label>Catatan Revisi Judul</label><textarea name="catatan_judul" placeholder="Tuliskan bagian judul yang perlu diperbaiki." required></textarea></div>
            <button class="btn btn-warning" type="submit">Kirim Permintaan Revisi</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody></table></div>
  <?php endif; $s->close(); ?>
</section>
<section class="card">
  <div class="section-heading">
    <h2>Riwayat Revisi Judul</h2>
    <p class="muted">Riwayat permintaan revisi judul mahasiswa bimbingan, termasuk judul sebelum, catatan, pengajuan ulang, dan persetujuan.</p>
  </div>
  <?php
  $s=$conn->prepare("SELECT jr.*,u.nama_lengkap mahasiswa_nama FROM judul_revisi jr JOIN users u ON u.id=jr.mahasiswa_id WHERE jr.dosen_id=? ORDER BY jr.id DESC");
  if($s){$s->bind_param('i',$uid);$s->execute();$titleHistoryRows=$s->get_result();$s->close();}else{$titleHistoryRows=false;}
  ?>
  <?php if($titleHistoryRows===false): ?>
    <div class="empty-state">Riwayat belum dapat ditampilkan. Pastikan migration <strong>migration_add_judul_revisi_history.sql</strong> sudah dijalankan di database.</div>
  <?php elseif(!$titleHistoryRows->num_rows): ?>
    <div class="empty-state">Belum ada permintaan revisi judul.</div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Judul Sebelum</th><th>Catatan Dosen</th><th>Status</th><th>Waktu</th><th>Detail</th></tr></thead><tbody>
    <?php while($h=$titleHistoryRows->fetch_assoc()): ?>
      <tr>
        <td><strong><?=e($h['mahasiswa_nama'])?></strong></td>
        <td><?=e($h['judul_sebelum'])?></td>
        <td><?=e($h['catatan_dosen'])?></td>
        <td><?=getStatusBadge($h['status'])?></td>
        <td><?=e(formatDateTime($h['requested_at']))?></td>
        <td><a class="btn btn-secondary" href="?page=bimbingan-detail&id=<?=e($h['bimbingan_id'])?>">Buka Detail</a></td>
      </tr>
    <?php endwhile; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php endif; ?><script>
document.addEventListener('click',function(e){
  const b=e.target.closest('[data-title-revision]');
  if(b){const f=document.getElementById('title-revision-'+b.dataset.titleRevision);if(f)f.style.display=f.style.display==='none'?'block':'none';}
});
</script>