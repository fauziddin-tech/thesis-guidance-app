<?php
$user=$_SESSION['user']; $uid=(int)$user['id']; $role=$user['role'];
$unreadCount=0;
$ns=$conn->prepare("SELECT COUNT(*) total FROM notifikasi WHERE user_id=? AND dibaca=0");
if($ns){$ns->bind_param('i',$uid);$ns->execute();$unreadCount=(int)($ns->get_result()->fetch_assoc()['total']??0);$ns->close();}
?>
<div class="page-head">
  <div><h1>Dashboard</h1><p class="muted">Ringkasan bimbingan dan perkembangan skripsi Anda.</p>
  <div class="dashboard-refresh">
    <button class="btn btn-secondary" type="button" onclick="window.location.reload()">Muat Ulang</button>
  </div></div>
  <span class="role-badge"><?=e(ucfirst($role))?></span>
</div>

<section class="card research-flow-card">
  <div class="section-heading">
    <h2>Alur Penelitian</h2>
    <p class="muted">Klik setiap tahap untuk melihat halaman dan status proses penelitian.</p>
  </div>
  <?php
  $flowBimbinganId=0;$flowStatus='belum_ada';
  $flowCounts=array_fill(0,6,0);

  if($role==='mahasiswa'){
    $fs=$conn->prepare("SELECT id,status FROM bimbingan WHERE mahasiswa_id=? ORDER BY created_at DESC LIMIT 1");
    if($fs){$fs->bind_param('i',$uid);$fs->execute();$fr=$fs->get_result()->fetch_assoc();$fs->close();}
    if(!empty($fr)){
      $flowBimbinganId=(int)$fr['id'];$flowStatus=$fr['status'];

      if($flowStatus==='revisi_judul'){$flowCounts[0]=1;}

      for($i=1;$i<=5;$i++){
        $bn='Bab '.$i;
        $fb=$conn->prepare("SELECT status FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=? ORDER BY versi DESC,id DESC LIMIT 1");
        if($fb){
          $fb->bind_param('is',$flowBimbinganId,$bn);$fb->execute();$fbr=$fb->get_result()->fetch_assoc();$fb->close();
          if(!empty($fbr) && $fbr['status']==='direvisi'){
            $flowCounts[$i]=1;
          }elseif(empty($fbr) && $flowStatus==='aktif' && $i===1){
            $flowCounts[$i]=1;
          }elseif(!empty($fbr) && $fbr['status']==='disetujui' && $i<5){
            $flowCounts[$i+1]=1;
          }
        }
      }
    }
  }elseif($role==='dosen'){
    $ds=$conn->prepare("SELECT COUNT(*) total FROM bimbingan WHERE dosen_id=? AND status='pengajuan_judul'");
    if($ds){$ds->bind_param('i',$uid);$ds->execute();$flowCounts[0]=(int)($ds->get_result()->fetch_assoc()['total']??0);$ds->close();}
    for($i=1;$i<=5;$i++){
      $bn='Bab '.$i;
      $ds=$conn->prepare("SELECT COUNT(*) total
                          FROM bimbingan b
                          JOIN bab_skripsi bs ON bs.id=(
                            SELECT x.id FROM bab_skripsi x
                            WHERE x.bimbingan_id=b.id AND x.nama_bab=?
                            ORDER BY x.versi DESC,x.id DESC LIMIT 1
                          )
                          WHERE b.dosen_id=? AND b.status='aktif' AND bs.status='menunggu_review'");
      if($ds){$ds->bind_param('si',$bn,$uid);$ds->execute();$flowCounts[$i]=(int)($ds->get_result()->fetch_assoc()['total']??0);$ds->close();}
    }
  }
  ?>
  <div class="research-flow">
    <a class="research-flow-item <?=($flowCounts[0]>0||($role==='mahasiswa'&&($flowStatus==='pengajuan_judul'||$flowStatus==='belum_ada'))) ? 'is-current':''?>" href="<?= $flowBimbinganId ? '?page=bimbingan-detail&id='.$flowBimbinganId : '?page=dashboard' ?>">
      <span class="flow-number">01</span>
      <span class="flow-content">
        <strong>Pengajuan Judul</strong>
        <small><?= $role==='dosen' ? 'Pengajuan yang perlu diperiksa' : ($flowStatus==='pengajuan_judul'?'Menunggu persetujuan dosen':($flowStatus==='revisi_judul'?'Perlu revisi judul':($flowStatus==='belum_ada'?'Belum diajukan':'Judul disetujui'))) ?></small>
      </span>
      <?php if($flowCounts[0]>0): ?><span class="flow-count"><?=e($flowCounts[0])?></span><?php endif; ?>
    </a>
    <?php for($i=1;$i<=5;$i++): ?>
      <?php
      $flowBabStatus='Belum dimulai';
      if($flowBimbinganId){
        $fb=$conn->prepare("SELECT status FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=? ORDER BY versi DESC,id DESC LIMIT 1");
        if($fb){$bn='Bab '.$i;$fb->bind_param('is',$flowBimbinganId,$bn);$fb->execute();$fbr=$fb->get_result()->fetch_assoc();$fb->close();
          if(!empty($fbr)){$flowBabStatus=getStatusBadge($fbr['status']);}
          elseif($flowStatus==='pengajuan_judul'||$flowStatus==='belum_ada'){$flowBabStatus='Menunggu judul disetujui';}
          elseif($flowStatus==='aktif'&&$i===1){$flowBabStatus='Siap diunggah';}
        }
      }elseif($role==='dosen' && $flowCounts[$i]>0){
        $flowBabStatus='Ada pengajuan yang perlu diperiksa';
      }
      ?>
      <a class="research-flow-item <?= $flowCounts[$i]>0 ? 'is-current':'' ?>" href="<?= $flowBimbinganId ? '?page=bimbingan-detail&id='.$flowBimbinganId : '?page=dashboard' ?>">
        <span class="flow-number">0<?=($i+1)?></span>
        <span class="flow-content"><strong>Bab <?=$i?></strong><small><?=$flowBabStatus?></small></span>
        <?php if($flowCounts[$i]>0): ?><span class="flow-count"><?=e($flowCounts[$i])?></span><?php endif; ?>
      </a>
    <?php endfor; ?>
  </div>
  <div class="hint">Angka pada setiap tahap menunjukkan jumlah tindakan yang perlu diperiksa atau dilakukan. Angka akan berubah setelah proses ditindaklanjuti.</div>
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
  <h2>Unggah Bab</h2><p class="muted">Format PDF, DOC, atau DOCX. Maksimal <?=e(format_bytes(effective_upload_limit()))?>. Bab berikutnya hanya dapat diunggah setelah bab sebelumnya mendapat ACC.</p>
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
  <div class="section-heading"><div><h2>Riwayat Bimbingan</h2><p class="muted">Daftar seluruh pengajuan bimbingan Anda.</p></div><div class="actions" style="margin-top:12px"><a class="btn btn-secondary" href="export-riwayat.php?format=word" target="_blank" rel="noopener">Unduh Word</a><a class="btn btn-secondary" href="export-riwayat.php?format=pdf" target="_blank" rel="noopener">Cetak / Simpan PDF</a></div></div>
  <?php $s=$conn->prepare("SELECT b.*,u.nama_lengkap dosen_nama FROM bimbingan b JOIN users u ON u.id=b.dosen_id WHERE b.mahasiswa_id=? ORDER BY b.created_at DESC");$s->bind_param('i',$uid);$s->execute();$r=$s->get_result();if(!$r->num_rows): ?><div class="empty-state">Belum ada bimbingan.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Judul Skripsi</th><th>Dosen</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php while($b=$r->fetch_assoc()): ?><tr><td><?=e($b['judul_skripsi'])?></td><td><?=e($b['dosen_nama'])?></td><td><?=getStatusBadge($b['status'])?></td><td><a class="btn btn-secondary" href="?page=bimbingan-detail&id=<?=$b['id']?>">Lihat Detail</a></td></tr><?php endwhile; ?></tbody></table></div><?php endif;$s->close(); ?>
</section>

<?php elseif($role==='dosen'): ?>

<section class="card" id="mahasiswa-bimbingan">
  <div class="section-heading">
    <h2>Mahasiswa Bimbingan</h2>
    <p class="muted">Daftar mahasiswa yang menjadi bimbingan Anda. Pilih <strong>Detail Bimbingan</strong> untuk melihat alur, dokumen, riwayat review, dan proses ACC.</p>
  </div>
  <?php
  $studentSearch=trim($_GET['student_q']??''); $studentLimit=(int)($_GET['student_limit']??10); if(!in_array($studentLimit,[10,20,50],true)) $studentLimit=10;
  $studentPage=max(1,(int)($_GET['student_page']??1)); $studentOffset=($studentPage-1)*$studentLimit; $studentTotal=0;$studentRows=[];
  if($studentSearch!==''){
    $like='%'.$studentSearch.'%';
    $s=$conn->prepare("SELECT COUNT(*) total FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND (u.nama_lengkap LIKE ? OR u.email LIKE ? OR b.judul_skripsi LIKE ?)");
    $s->bind_param('isss',$uid,$like,$like,$like);$s->execute();$studentTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT b.id,b.mahasiswa_id,b.judul_skripsi,b.status,b.updated_at,u.nama_lengkap mahasiswa_nama,u.email FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND (u.nama_lengkap LIKE ? OR u.email LIKE ? OR b.judul_skripsi LIKE ?) ORDER BY u.nama_lengkap ASC,b.updated_at DESC,b.id DESC LIMIT ? OFFSET ?");
    $s->bind_param('isssii',$uid,$like,$like,$like,$studentLimit,$studentOffset);$s->execute();$studentRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
  }else{
    $s=$conn->prepare("SELECT COUNT(*) total FROM bimbingan WHERE dosen_id=?");$s->bind_param('i',$uid);$s->execute();$studentTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT b.id,b.mahasiswa_id,b.judul_skripsi,b.status,b.updated_at,u.nama_lengkap mahasiswa_nama,u.email FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? ORDER BY u.nama_lengkap ASC,b.updated_at DESC,b.id DESC LIMIT ? OFFSET ?");
    $s->bind_param('iii',$uid,$studentLimit,$studentOffset);$s->execute();$studentRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
  }
  $studentTotalPages=max(1,(int)ceil($studentTotal/$studentLimit)); if($studentPage>$studentTotalPages){$studentPage=$studentTotalPages;$studentOffset=($studentPage-1)*$studentLimit;}
  if(!$studentRows):
  ?>
    <div class="empty-state">Belum ada mahasiswa bimbingan.</div>
  <?php else: ?>
    <form method="get" class="account-toolbar">
      <input type="hidden" name="page" value="dashboard"><input type="hidden" name="student_page" value="1">
      <div class="account-search"><div><label for="student_q">Cari mahasiswa</label><div class="account-search-row"><input id="student_q" name="student_q" type="search" value="<?=e($studentSearch)?>" placeholder="Nama, email, atau judul skripsi"><button class="btn btn-secondary" type="submit">Cari</button><?php if($studentSearch!==''): ?><a class="btn btn-secondary" href="?page=dashboard#mahasiswa-bimbingan">Reset</a><?php endif; ?></div></div></div>
      <div class="account-limit"><label for="student_limit_top">Tampilkan</label><select id="student_limit_top" name="student_limit" onchange="this.form.submit()"><option value="10" <?=$studentLimit===10?'selected':''?>>10</option><option value="20" <?=$studentLimit===20?'selected':''?>>20</option><option value="50" <?=$studentLimit===50?'selected':''?>>50</option></select></div>
    </form>
    <div class="student-list">
      <?php foreach($studentRows as $b): ?>
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
            <?=impersonate_form((int)$b['mahasiswa_id'],(string)$b['mahasiswa_nama'])?>
          </div>
        </article>
      <?php endwhile; ?>
    </div>
    <?=render_pagination($studentPage,$studentTotalPages,$studentTotal,$studentOffset,$studentLimit,['page'=>'dashboard','student_q'=>$studentSearch,'student_limit'=>$studentLimit],'student_page','#mahasiswa-bimbingan','mahasiswa')?>
  <?php endif; ?>
</section>

<section class="card" id="pengajuan-judul">
  <div class="section-heading">
    <h2>Pengajuan Judul</h2>
    <p class="muted">Pengajuan judul yang menunggu keputusan dosen.</p>
  </div>
  <?php
  $judulSearch=trim($_GET['judul_q']??''); $judulLimit=(int)($_GET['judul_limit']??10); if(!in_array($judulLimit,[10,20,50],true)) $judulLimit=10;
  $judulPage=max(1,(int)($_GET['judul_page']??1)); $judulOffset=($judulPage-1)*$judulLimit; $judulTotal=0;$judulRows=[];
  if($judulSearch!==''){
    $like='%'.$judulSearch.'%';
    $s=$conn->prepare("SELECT COUNT(*) total FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND b.status='pengajuan_judul' AND (u.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ? OR b.deskripsi LIKE ?)");
    $s->bind_param('isss',$uid,$like,$like,$like);$s->execute();$judulTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.deskripsi,u.nama_lengkap mahasiswa_nama FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND b.status='pengajuan_judul' AND (u.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ? OR b.deskripsi LIKE ?) ORDER BY b.created_at ASC,b.id ASC LIMIT ? OFFSET ?");
    $s->bind_param('isssii',$uid,$like,$like,$like,$judulLimit,$judulOffset);$s->execute();$judulRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
  }else{
    $s=$conn->prepare("SELECT COUNT(*) total FROM bimbingan WHERE dosen_id=? AND status='pengajuan_judul'");$s->bind_param('i',$uid);$s->execute();$judulTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.deskripsi,u.nama_lengkap mahasiswa_nama FROM bimbingan b JOIN users u ON u.id=b.mahasiswa_id WHERE b.dosen_id=? AND b.status='pengajuan_judul' ORDER BY b.created_at ASC,b.id ASC LIMIT ? OFFSET ?");
    $s->bind_param('iii',$uid,$judulLimit,$judulOffset);$s->execute();$judulRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
  }
  $judulTotalPages=max(1,(int)ceil($judulTotal/$judulLimit)); if($judulPage>$judulTotalPages){$judulPage=$judulTotalPages;$judulOffset=($judulPage-1)*$judulLimit;}
  if(!$judulRows):
  ?>
    <div class="empty-state">Belum ada pengajuan judul yang menunggu keputusan.</div>
  <?php else: ?>
    <form method="get" class="account-toolbar">
      <input type="hidden" name="page" value="dashboard"><input type="hidden" name="judul_page" value="1">
      <div class="account-search"><div><label for="judul_q">Cari pengajuan</label><div class="account-search-row"><input id="judul_q" name="judul_q" type="search" value="<?=e($judulSearch)?>" placeholder="Nama mahasiswa, judul, atau deskripsi"><button class="btn btn-secondary" type="submit">Cari</button><?php if($judulSearch!==''): ?><a class="btn btn-secondary" href="?page=dashboard#pengajuan-judul">Reset</a><?php endif; ?></div></div></div>
      <div class="account-limit"><label for="judul_limit_top">Tampilkan</label><select id="judul_limit_top" name="judul_limit" onchange="this.form.submit()"><option value="10" <?=$judulLimit===10?'selected':''?>>10</option><option value="20" <?=$judulLimit===20?'selected':''?>>20</option><option value="50" <?=$judulLimit===50?'selected':''?>>50</option></select></div>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Judul</th><th>Deskripsi</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach($judulRows as $j): ?>
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
    <?=render_pagination($judulPage,$judulTotalPages,$judulTotal,$judulOffset,$judulLimit,['page'=>'dashboard','judul_q'=>$judulSearch,'judul_limit'=>$judulLimit],'judul_page','#pengajuan-judul','pengajuan judul')?>
  <?php endif; ?>
</section>
<section class="card" id="riwayat-revisi-judul">
  <div class="section-heading">
    <h2>Riwayat Revisi Judul</h2>
    <p class="muted">Riwayat permintaan revisi judul mahasiswa bimbingan, termasuk judul sebelum, catatan, pengajuan ulang, dan persetujuan.</p>
  </div>
  <?php
  $historySearch=trim($_GET['history_q']??''); $historyLimit=(int)($_GET['history_limit']??10); if(!in_array($historyLimit,[10,20,50],true)) $historyLimit=10;
  $historyPage=max(1,(int)($_GET['history_page']??1)); $historyOffset=($historyPage-1)*$historyLimit; $historyTotal=0;$titleHistoryRows=[];
  $like=$historySearch!==''?'%'.$historySearch.'%':null;
  if($like!==null){
    $s=$conn->prepare("SELECT COUNT(*) total FROM judul_revisi jr JOIN users u ON u.id=jr.mahasiswa_id WHERE jr.dosen_id=? AND (u.nama_lengkap LIKE ? OR jr.judul_sebelum LIKE ? OR jr.catatan_dosen LIKE ?)");
    $s->bind_param('isss',$uid,$like,$like,$like);$s->execute();$historyTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT jr.*,u.nama_lengkap mahasiswa_nama,b.status AS bimbingan_status,(SELECT MAX(x.id) FROM judul_revisi x WHERE x.bimbingan_id=jr.bimbingan_id) AS latest_revisi_id FROM judul_revisi jr JOIN users u ON u.id=jr.mahasiswa_id JOIN bimbingan b ON b.id=jr.bimbingan_id WHERE jr.dosen_id=? AND (u.nama_lengkap LIKE ? OR jr.judul_sebelum LIKE ? OR jr.catatan_dosen LIKE ?) ORDER BY jr.id DESC LIMIT ? OFFSET ?");
    $s->bind_param('isssii',$uid,$like,$like,$like,$historyLimit,$historyOffset);$s->execute();$titleHistoryRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
  }else{
    $s=$conn->prepare("SELECT COUNT(*) total FROM judul_revisi jr WHERE jr.dosen_id=?");$s->bind_param('i',$uid);$s->execute();$historyTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT jr.*,u.nama_lengkap mahasiswa_nama,b.status AS bimbingan_status,(SELECT MAX(x.id) FROM judul_revisi x WHERE x.bimbingan_id=jr.bimbingan_id) AS latest_revisi_id FROM judul_revisi jr JOIN users u ON u.id=jr.mahasiswa_id JOIN bimbingan b ON b.id=jr.bimbingan_id WHERE jr.dosen_id=? ORDER BY jr.id DESC LIMIT ? OFFSET ?");
    $s->bind_param('iii',$uid,$historyLimit,$historyOffset);$s->execute();$titleHistoryRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
  }
  $historyTotalPages=max(1,(int)ceil($historyTotal/$historyLimit)); if($historyPage>$historyTotalPages){$historyPage=$historyTotalPages;$historyOffset=($historyPage-1)*$historyLimit;}
  ?>
  <?php if($titleHistoryRows===false): ?>
    <div class="empty-state">Riwayat belum dapat ditampilkan. Pastikan migration <strong>migration_add_judul_revisi_history.sql</strong> sudah dijalankan di database.</div>
  <?php elseif(!$titleHistoryRows->num_rows): ?>
    <div class="empty-state">Belum ada permintaan revisi judul.</div>
  <?php else: ?>
    <form method="get" class="account-toolbar">
      <input type="hidden" name="page" value="dashboard"><input type="hidden" name="history_page" value="1">
      <div class="account-search"><div><label for="history_q">Cari riwayat revisi</label><div class="account-search-row"><input id="history_q" name="history_q" type="search" value="<?=e($historySearch)?>" placeholder="Nama mahasiswa, judul, atau catatan"><button class="btn btn-secondary" type="submit">Cari</button><?php if($historySearch!==''): ?><a class="btn btn-secondary" href="?page=dashboard#riwayat-revisi-judul">Reset</a><?php endif; ?></div></div></div>
      <div class="account-limit"><label for="history_limit_top">Tampilkan</label><select id="history_limit_top" name="history_limit" onchange="this.form.submit()"><option value="10" <?=$historyLimit===10?'selected':''?>>10</option><option value="20" <?=$historyLimit===20?'selected':''?>>20</option><option value="50" <?=$historyLimit===50?'selected':''?>>50</option></select></div>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Judul Sebelum</th><th>Catatan Dosen</th><th>Status</th><th>Waktu</th><th>Detail</th></tr></thead><tbody>
    <?php foreach($titleHistoryRows as $h): ?>
      <tr>
        <td><strong><?=e($h['mahasiswa_nama'])?></strong></td>
        <td><?=e($h['judul_sebelum'])?></td>
        <td><?=e($h['catatan_dosen'])?></td>
        <td><?php
          $rowBadge=$h['status'];
          if($rowBadge==='diajukan_ulang'){$rowBadge=((int)$h['id']===(int)$h['latest_revisi_id']&&$h['bimbingan_status']==='pengajuan_judul')?'menunggu_review':'sudah_ditinjau';}
          echo getStatusBadge($rowBadge);
        ?></td>
        <td><?=e(formatDateTime($h['requested_at']))?></td>
        <td><a class="btn btn-secondary" href="?page=bimbingan-detail&id=<?=e($h['bimbingan_id'])?>">Buka Detail</a></td>
      </tr>
    <?php endwhile; ?>
    </tbody></table></div>
    <?=render_pagination($historyPage,$historyTotalPages,$historyTotal,$historyOffset,$historyLimit,['page'=>'dashboard','history_q'=>$historySearch,'history_limit'=>$historyLimit],'history_page','#riwayat-revisi-judul','riwayat revisi judul')?>
  <?php endif; ?>
</section>
<?php endif; ?><script>
document.addEventListener('click',function(e){
  const b=e.target.closest('[data-title-revision]');
  if(b){const f=document.getElementById('title-revision-'+b.dataset.titleRevision);if(f)f.style.display=f.style.display==='none'?'block':'none';}
});
</script>
