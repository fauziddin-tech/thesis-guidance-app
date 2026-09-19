<?php
$user=$_SESSION['user']; $uid=(int)$user['id']; $role=$user['role'];
$unreadCount=0;
$ns=$conn->prepare("SELECT COUNT(*) total FROM notifikasi WHERE user_id=? AND dibaca=0");
if($ns){$ns->bind_param('i',$uid);$ns->execute();$unreadCount=(int)($ns->get_result()->fetch_assoc()['total']??0);$ns->close();}
?>
<div class="page-head">
  <div><h1>Dashboard</h1><p class="muted">Ringkasan bimbingan dan perkembangan skripsi.</p></div>
  <span class="role-badge"><?=e(ucfirst($role))?></span>
</div>

<?php if($role==='admin'): ?>
<section class="card">
  <div class="section-heading"><div><h2>Ringkasan Sistem</h2><p class="muted">Informasi umum aktivitas MyThesis.</p></div></div>
  <?php
  $stats=[
    'users'=>0,'mahasiswa'=>0,'dosen'=>0,'bimbingan'=>0,
    'pengajuan'=>0,'aktif'=>0,'selesai'=>0
  ];
  $q=$conn->query("SELECT COUNT(*) total FROM users"); if($q)$stats['users']=(int)$q->fetch_assoc()['total'];
  $q=$conn->query("SELECT COUNT(*) total FROM users WHERE role='mahasiswa'"); if($q)$stats['mahasiswa']=(int)$q->fetch_assoc()['total'];
  $q=$conn->query("SELECT COUNT(*) total FROM users WHERE role='dosen'"); if($q)$stats['dosen']=(int)$q->fetch_assoc()['total'];
  $q=$conn->query("SELECT COUNT(*) total FROM bimbingan"); if($q)$stats['bimbingan']=(int)$q->fetch_assoc()['total'];
  $q=$conn->query("SELECT COUNT(*) total FROM bimbingan WHERE status='pengajuan_judul'"); if($q)$stats['pengajuan']=(int)$q->fetch_assoc()['total'];
  $q=$conn->query("SELECT COUNT(*) total FROM bimbingan WHERE status='aktif'"); if($q)$stats['aktif']=(int)$q->fetch_assoc()['total'];
  $q=$conn->query("SELECT COUNT(*) total FROM bimbingan WHERE status='selesai'"); if($q)$stats['selesai']=(int)$q->fetch_assoc()['total'];
  ?>
  <div class="grid-2">
    <div class="meta-item"><div class="meta-label">Total Pengguna</div><div class="meta-value"><?=e($stats['users'])?></div></div>
    <div class="meta-item"><div class="meta-label">Mahasiswa</div><div class="meta-value"><?=e($stats['mahasiswa'])?></div></div>
    <div class="meta-item"><div class="meta-label">Dosen</div><div class="meta-value"><?=e($stats['dosen'])?></div></div>
    <div class="meta-item"><div class="meta-label">Total Bimbingan</div><div class="meta-value"><?=e($stats['bimbingan'])?></div></div>
    <div class="meta-item"><div class="meta-label">Pengajuan Judul</div><div class="meta-value"><?=e($stats['pengajuan'])?></div></div>
    <div class="meta-item"><div class="meta-label">Bimbingan Aktif</div><div class="meta-value"><?=e($stats['aktif'])?></div></div>
    <div class="meta-item"><div class="meta-label">Bimbingan Selesai</div><div class="meta-value"><?=e($stats['selesai'])?></div></div>
  </div>
</section>

<section class="card">
  <div class="section-heading"><h2>Bimbingan Terbaru</h2><p class="muted">Daftar pengajuan dan aktivitas bimbingan terbaru.</p></div>
  <?php
  $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.status,b.created_at,m.nama_lengkap mahasiswa_nama,d.nama_lengkap dosen_nama FROM bimbingan b JOIN users m ON m.id=b.mahasiswa_id JOIN users d ON d.id=b.dosen_id ORDER BY b.created_at DESC LIMIT 10");
  if($s){$s->execute();$rows=$s->get_result();}
  ?>
  <?php if(empty($rows)||!$rows->num_rows): ?>
    <div class="empty-state">Belum ada data bimbingan.</div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Judul</th><th>Dosen</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php while($b=$rows->fetch_assoc()): ?>
      <tr><td><strong><?=e($b['mahasiswa_nama'])?></strong></td><td><?=e($b['judul_skripsi'])?></td><td><?=e($b['dosen_nama'])?></td><td><?=getStatusBadge($b['status'])?></td><td><a class="btn btn-secondary" href="?page=bimbingan-detail&id=<?=e($b['id'])?>">Detail</a></td></tr>
    <?php endwhile; ?>
    </tbody></table></div>
  <?php endif; if(isset($s)&&$s)$s->close(); ?>
</section>

<?php elseif($role==='mahasiswa'): ?>
<?php
$flowBimbinganId=0;$flowStatus='belum_ada';$flowCounts=array_fill(0,6,0);
$fs=$conn->prepare("SELECT id,status FROM bimbingan WHERE mahasiswa_id=? ORDER BY created_at DESC LIMIT 1");
if($fs){$fs->bind_param('i',$uid);$fs->execute();$fr=$fs->get_result()->fetch_assoc();$fs->close();}
if(!empty($fr)){$flowBimbinganId=(int)$fr['id'];$flowStatus=$fr['status'];if($flowStatus==='revisi_judul')$flowCounts[0]=1;for($i=1;$i<=5;$i++){ $bn='Bab '.$i;$fb=$conn->prepare("SELECT status FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=? ORDER BY versi DESC,id DESC LIMIT 1");if($fb){$fb->bind_param('is',$flowBimbinganId,$bn);$fb->execute();$fbr=$fb->get_result()->fetch_assoc();$fb->close();if(!empty($fbr)&&$fbr['status']==='direvisi')$flowCounts[$i]=1;elseif(empty($fbr)&&$flowStatus==='aktif'&&$i===1)$flowCounts[$i]=1;elseif(!empty($fbr)&&$fbr['status']==='disetujui'&&$i<5)$flowCounts[$i+1]=1;}}}
?>
<section class="card research-flow-card"><div class="section-heading"><h2>Alur Penelitian</h2><p class="muted">Klik setiap tahap untuk melihat status proses penelitian.</p></div><div class="research-flow">
<a class="research-flow-item <?=($flowCounts[0]>0||($flowStatus==='pengajuan_judul'||$flowStatus==='belum_ada'))?'is-current':''?>" href="<?=$flowBimbinganId?'?page=bimbingan-detail&id='.$flowBimbinganId:'?page=dashboard'?>"><span class="flow-number">01</span><span class="flow-content"><strong>Pengajuan Judul</strong><small><?=$flowStatus==='pengajuan_judul'?'Menunggu persetujuan dosen':($flowStatus==='revisi_judul'?'Perlu revisi judul':($flowStatus==='belum_ada'?'Belum diajukan':'Judul disetujui'))?></small></span><?php if($flowCounts[0]>0): ?><span class="flow-count"><?=e($flowCounts[0])?></span><?php endif; ?></a>
<?php for($i=1;$i<=5;$i++): ?><a class="research-flow-item <?= $flowCounts[$i]>0?'is-current':'' ?>" href="<?=$flowBimbinganId?'?page=bimbingan-detail&id='.$flowBimbinganId:'?page=dashboard'?>"><span class="flow-number">0<?=($i+1)?></span><span class="flow-content"><strong>Bab <?=$i?></strong><small>Kelola Bab <?=$i?> pada detail bimbingan.</small></span><?php if($flowCounts[$i]>0): ?><span class="flow-count"><?=e($flowCounts[$i])?></span><?php endif; ?></a><?php endfor; ?></div></section>
<section class="card"><h2>Pengajuan Judul Skripsi</h2><p class="muted">Gunakan halaman ini untuk mengajukan judul dan membuka detail bimbingan.</p><a class="btn btn-primary" href="?page=bimbingan">Bimbingan</a></section>

<?php elseif($role==='dosen'): ?>
<section class="card"><div class="section-heading"><h2>Mahasiswa Bimbingan</h2><p class="muted">Daftar mahasiswa yang menjadi bimbingan Anda.</p></div>
<?php $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.status,b.updated_at,u.nama_lengkap mahasiswa_nama,u.email FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? ORDER BY u.nama_lengkap ASC,b.updated_at DESC");if($s){$s->bind_param('i',$uid);$s->execute();$r=$s->get_result();} ?>
<?php if(empty($r)||!$r->num_rows): ?><div class="empty-state">Belum ada mahasiswa bimbingan.</div><?php else: ?><div class="student-list"><?php while($b=$r->fetch_assoc()): ?><article class="student-card"><div class="student-card-main"><div class="student-index">MAHASISWA</div><h3><?=e($b['mahasiswa_nama'])?></h3><p class="student-email"><?=e($b['email'])?></p><div class="student-title"><span class="meta-label">Judul Skripsi</span><strong><?=e($b['judul_skripsi'])?></strong></div></div><div class="student-card-side"><div><?=getStatusBadge($b['status'])?></div><small class="muted">Diperbarui <?=e(formatDateTime($b['updated_at']))?></small><a class="btn btn-primary" href="?page=bimbingan-detail&id=<?=e($b['id'])?>">Detail Bimbingan</a></div></article><?php endwhile; ?></div><?php endif; if(isset($s)&&$s)$s->close(); ?></section>
<?php endif; ?>
<script>document.addEventListener('click',function(e){const b=e.target.closest('[data-title-revision]');if(b){const f=document.getElementById('title-revision-'+b.dataset.titleRevision);if(f)f.style.display=f.style.display==='none'?'block':'none';}});</script>