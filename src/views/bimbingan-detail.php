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
        <td><a class="btn btn-secondary" target="_blank" rel="noopener" href="download.php?id=<?=e($row['id'])?>">Buka Dokumen</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
  <?php endfor; ?>
</div>
