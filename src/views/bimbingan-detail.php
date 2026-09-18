<?php
require_login();
$id=(int)($_GET['id']??0); if(!$id) redirect('?page=dashboard');
require_once __DIR__.'/../controllers/BimbinganController.php';
$ctrl=new BimbinganController($conn); $b=$ctrl->getDetail($id);
if(!$b){http_response_code(404);echo '<div class="card"><h2>Bimbingan tidak ditemukan</h2><p class="muted">Data yang diminta tidak tersedia.</p></div>';return;}
$u=$_SESSION['user'];
if($u['role']==='mahasiswa'&&(int)$b['mahasiswa_id']!==(int)$u['id']){http_response_code(403);exit('Akses ditolak.');}
if($u['role']==='dosen'&&(int)$b['dosen_id']!==(int)$u['id']){http_response_code(403);exit('Akses ditolak.');}
$rows=$ctrl->getBabSkripsi($id);$progress=$ctrl->getProgress($id);$babByName=[];
$revisionMap=[];
$rs=$conn->prepare("SELECT r.id,r.bab_id,r.komentar,r.tipe_revisi,r.file_path,r.created_at,d.nama_lengkap AS dosen_nama FROM revisi r JOIN bab_skripsi bs ON bs.id=r.bab_id JOIN users d ON d.id=r.dosen_id WHERE bs.bimbingan_id=? ORDER BY r.created_at DESC");
$rs->bind_param('i',$id);$rs->execute();$revRows=$rs->get_result()->fetch_all(MYSQLI_ASSOC);$rs->close();
foreach($revRows as $rv){$revisionMap[(int)$rv['bab_id']][]=$rv;}
foreach($rows as $row){$babByName[$row['nama_bab']][]=$row;}
?>
<div class="page-head">
  <div><h1>Detail Bimbingan</h1><p class="muted">Riwayat dokumen, status review, dan perkembangan skripsi.</p></div>
  <a class="btn btn-secondary" href="?page=dashboard">Kembali ke Dashboard</a>
</div>

<div class="detail-grid">
  <section class="card">
    <h2>Informasi Skripsi</h2>
    <div class="detail-meta">
      <div class="meta-item"><div class="meta-label">Judul Skripsi</div><div class="meta-value"><?=e($b['judul_skripsi'])?></div></div>
      <div class="meta-item"><div class="meta-label">Status Bimbingan</div><div class="meta-value"><?=getStatusBadge($b['status'])?></div></div>
      <div class="meta-item"><div class="meta-label">Mahasiswa</div><div class="meta-value"><?=e($b['mahasiswa_nama'])?></div></div>
      <div class="meta-item"><div class="meta-label">Dosen Pembimbing</div><div class="meta-value"><?=e($b['dosen_nama'])?></div></div>
    </div>
  </section>
  <section class="card">
    <h2>Progress</h2>
    <div class="progress-track"><div class="progress-fill" style="width:<?=max(0,min(100,(float)$progress))?>%"></div></div>
    <p style="font-size:26px;font-weight:750;margin:12px 0 0"><?=round($progress)?>%</p>
    <p class="muted" style="margin:2px 0 0">Berdasarkan jumlah Bab 1–5 yang sudah disetujui.</p>
  </section>
</div>

<div class="card">
  <div class="section-heading"><h2>Perkembangan Bimbingan</h2><p class="muted">Pengajuan judul harus disetujui terlebih dahulu sebelum proses Bab 1 sampai Bab 5.</p></div>
  <div class="workflow">
    <div class="workflow-step <?= $b['status']==='aktif'?'is-approved':'' ?>">Pengajuan Judul<br><small><?=e(ucfirst(str_replace('_',' ',$b['status'])))?></small></div>
    <?php for($n=1;$n<=5;$n++): $h=$babByName['Bab '.$n]??[];$latest=$h[0]??null;$cls=$latest&&$latest['status']==='disetujui'?'is-approved':($latest?'is-current':''); ?>
      <div class="workflow-step <?=$cls?>">Bab <?=$n?><br><small><?= $latest ? e(ucfirst(str_replace('_',' ',$latest['status']))) : 'Belum diunggah' ?></small></div>
    <?php endfor; ?>
  </div>

  <?php for($n=1;$n<=5;$n++): $name='Bab '.$n;$history=$babByName[$name]??[]; ?>
  <section class="chapter-block">
    <div class="chapter-title"><h4><?=$name?></h4><?php if($history): ?><?=getStatusBadge($history[0]['status'])?><?php endif; ?></div>
    <?php if(!$history): ?>
      <div class="empty-state">Bab ini belum diunggah.</div>
    <?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>Versi</th><th>Status</th><th>Revisi</th><th>Waktu Upload</th><th>Dokumen</th></tr></thead><tbody>
      <?php foreach($history as $row): ?>
      <tr>
        <td><strong>v<?=e($row['versi'])?></strong></td>
        <td><?=getStatusBadge($row['status'])?></td>
        <td><?=e($row['total_revisi'])?></td>
        <td><?=e(formatDateTime($row['uploaded_at']))?></td>
        <td>
          <?php $ext=strtolower(pathinfo((string)$row['file_path'],PATHINFO_EXTENSION)); ?>
          <a class="btn btn-secondary" target="_blank" rel="noopener" href="download.php?id=<?=e($row['id'])?>"><?= $ext==='pdf' ? 'Preview Dokumen' : 'Buka Dokumen' ?></a>
        </td>
      </tr>
      <?php if(!empty($revisionMap[(int)$row['id']])): ?>
        <?php foreach($revisionMap[(int)$row['id']] as $rv): ?>
        <tr>
          <td colspan="5">
            <div class="meta-item">
              <div class="meta-label">Catatan Dosen — <?=e($rv['dosen_nama'])?> (<?=e(formatDateTime($rv['created_at']))?>)</div>
              <div class="meta-value"><?=nl2br(e($rv['komentar']))?></div>
              <?php if(!empty($rv['file_path'])): ?><div class="actions"><a class="btn btn-secondary" target="_blank" rel="noopener" href="download.php?revisi=<?=e($rv['id'])?>">Buka File Revisi Dosen</a></div><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php endforeach; ?>
      </tbody></table></div>

      <?php if($u['role']==='mahasiswa' && $history[0]['status']==='direvisi'): ?>
      <div class="review-panel" style="margin-top:18px;">
        <div class="section-heading">
          <h4>Unggah Revisi <?=$name?></h4>
          <p class="muted">Silakan perbaiki dokumen berdasarkan catatan dosen, lalu unggah versi berikutnya untuk direview kembali.</p>
        </div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_bab">
          <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
          <input type="hidden" name="bimbingan_id" value="<?=e($id)?>">
          <input type="hidden" name="nama_bab" value="<?=e($name)?>">
          <div class="form-group">
            <label>File Revisi <?=$name?></label>
            <input type="file" name="file" accept=".pdf,.doc,.docx" required>
            <small class="muted">PDF, DOC, atau DOCX. Maksimal 10 MB. File ini akan disimpan sebagai versi berikutnya.</small>
          </div>
          <button class="btn btn-primary" type="submit">Unggah Revisi <?=$name?></button>
        </form>
      </div>
      <?php endif; ?>

      <?php if($u['role']==='dosen' && $history[0]['status']==='menunggu_review'): ?>
      <div class="review-panel" style="margin-top:18px;">
        <div class="section-heading">
          <h4>Tindakan Review Dosen</h4>
          <p class="muted">Versi terbaru dapat dikembalikan untuk revisi atau langsung di-ACC.</p>
        </div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_revision">
          <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
          <input type="hidden" name="bab_id" value="<?=e($history[0]['id'])?>">
          <div class="form-group">
            <label>Komentar / Catatan Revisi</label>
            <textarea name="komentar" placeholder="Tuliskan bagian yang perlu diperbaiki." required></textarea>
          </div>
          <div class="form-group">
            <label>Tipe Revisi</label>
            <select name="tipe_revisi">
              <option value="minor">Minor</option>
              <option value="major">Major</option>
              <option value="kritis">Kritis</option>
            </select>
          </div>
          <div class="form-group">
            <label>File Revisi dari Dosen</label>
            <input type="file" name="file_revisi" accept=".pdf,.doc,.docx">
            <small class="muted">Opsional, maksimal 10 MB.</small>
          </div>
          <button class="btn btn-warning" type="submit">Kirim Revisi</button>
        </form>
        <form method="post" style="margin-top:10px;">
          <input type="hidden" name="action" value="approve_bab">
          <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
          <input type="hidden" name="bab_id" value="<?=e($history[0]['id'])?>">
          <button class="btn btn-success" type="submit">Setujui / ACC Bab</button>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endfor; ?>
</div>
