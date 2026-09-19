<?php
require_role(['admin']);

$user=$_SESSION['user'];
$uid=(int)$user['id'];

function adminCount(mysqli $conn,string $sql): int {
    $result=$conn->query($sql);
    if(!$result) return 0;
    $row=$result->fetch_assoc();
    return (int)($row['total']??0);
}

$totalUsers=adminCount($conn,'SELECT COUNT(*) total FROM users');
$totalMahasiswa=adminCount($conn,"SELECT COUNT(*) total FROM users WHERE role='mahasiswa'");
$totalDosen=adminCount($conn,"SELECT COUNT(*) total FROM users WHERE role='dosen'");
$totalBimbingan=adminCount($conn,'SELECT COUNT(*) total FROM bimbingan');
$totalAktif=adminCount($conn,"SELECT COUNT(*) total FROM bimbingan WHERE status='aktif'");
$totalPengajuan=adminCount($conn,"SELECT COUNT(*) total FROM bimbingan WHERE status='pengajuan_judul'");
$totalRevisiJudul=adminCount($conn,"SELECT COUNT(*) total FROM bimbingan WHERE status='revisi_judul'");
$totalDitangguhkan=adminCount($conn,"SELECT COUNT(*) total FROM bimbingan WHERE status='ditangguhkan'");
$totalSelesai=adminCount($conn,"SELECT COUNT(*) total FROM bimbingan WHERE status='selesai'");
$totalReview=adminCount($conn,"SELECT COUNT(*) total
    FROM bimbingan b
    JOIN bab_skripsi bs ON bs.id=(
        SELECT x.id FROM bab_skripsi x
        WHERE x.bimbingan_id=b.id
        ORDER BY x.versi DESC,x.id DESC LIMIT 1
    )
    WHERE b.status='aktif' AND bs.status='menunggu_review'");

$users=[];
$r=$conn->query("SELECT id,username,email,role,nama_lengkap,created_at FROM users ORDER BY created_at DESC LIMIT 8");
if($r) $users=$r->fetch_all(MYSQLI_ASSOC);

$dosen=[];
$r=$conn->query("SELECT id,nama_lengkap,email FROM users WHERE role='dosen' ORDER BY nama_lengkap ASC");
if($r) $dosen=$r->fetch_all(MYSQLI_ASSOC);

$dosenSearch=trim($_GET['dosen_q']??'');
$dosenLimit=(int)($_GET['dosen_limit']??10); if(!in_array($dosenLimit,[10,20,50],true)) $dosenLimit=10;
$dosenPage=max(1,(int)($_GET['dosen_page']??1)); $dosenOffset=($dosenPage-1)*$dosenLimit; $dosenTotal=0;$dosenRows=[];
if($dosenSearch!==''){
    $like='%'.$dosenSearch.'%';
    $s=$conn->prepare("SELECT COUNT(*) total FROM users WHERE role='dosen' AND (nama_lengkap LIKE ? OR username LIKE ? OR email LIKE ? OR no_telp LIKE ?)");
    $s->bind_param('ssss',$like,$like,$like,$like);$s->execute();$dosenTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT u.id,u.nama_lengkap,u.username,u.email,u.no_telp,(SELECT COUNT(*) FROM bimbingan b WHERE b.dosen_id=u.id) AS total_bimbingan FROM users u WHERE u.role='dosen' AND (u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.no_telp LIKE ?) ORDER BY u.nama_lengkap ASC,u.id ASC LIMIT ? OFFSET ?");
    $s->bind_param('ssssii',$like,$like,$like,$like,$dosenLimit,$dosenOffset);$s->execute();$dosenRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}else{
    $r=$conn->query("SELECT COUNT(*) total FROM users WHERE role='dosen'");if($r)$dosenTotal=(int)($r->fetch_assoc()['total']??0);
    $s=$conn->prepare("SELECT u.id,u.nama_lengkap,u.username,u.email,u.no_telp,(SELECT COUNT(*) FROM bimbingan b WHERE b.dosen_id=u.id) AS total_bimbingan FROM users u WHERE u.role='dosen' ORDER BY u.nama_lengkap ASC,u.id ASC LIMIT ? OFFSET ?");
    $s->bind_param('ii',$dosenLimit,$dosenOffset);$s->execute();$dosenRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}
$dosenTotalPages=max(1,(int)ceil($dosenTotal/$dosenLimit)); if($dosenPage>$dosenTotalPages){$dosenPage=$dosenTotalPages;$dosenOffset=($dosenPage-1)*$dosenLimit;}

$accountSearch=trim($_GET['account_q']??'');
$accountLimit=(int)($_GET['account_limit']??10);
if(!in_array($accountLimit,[10,20,50],true)) $accountLimit=10;
$accountPage=max(1,(int)($_GET['account_page']??1));
$accountOffset=($accountPage-1)*$accountLimit;
$accountRows=[];$accountTotal=0;$accountTotalPages=1;
if($accountSearch!==''){
    $like='%'.$accountSearch.'%';
    $s=$conn->prepare('SELECT COUNT(*) total FROM users WHERE nama_lengkap LIKE ?');$s->bind_param('s',$like);$s->execute();$accountTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare('SELECT id,username,email,role,nama_lengkap,created_at FROM users WHERE nama_lengkap LIKE ? ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?');$s->bind_param('sii',$like,$accountLimit,$accountOffset);$s->execute();$accountRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}else{
    $r=$conn->query('SELECT COUNT(*) total FROM users');if($r)$accountTotal=(int)($r->fetch_assoc()['total']??0);
    $s=$conn->prepare('SELECT id,username,email,role,nama_lengkap,created_at FROM users ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?');$s->bind_param('ii',$accountLimit,$accountOffset);$s->execute();$accountRows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}
$accountTotalPages=max(1,(int)ceil($accountTotal/$accountLimit));
if($accountPage>$accountTotalPages){$accountPage=$accountTotalPages;$accountOffset=($accountPage-1)*$accountLimit;}

$old=$_SESSION['old_dosen_form']??[];
unset($_SESSION['old_dosen_form']);

$bimbinganSearch=trim($_GET['bimbingan_q']??'');
$bimbinganLimit=(int)($_GET['bimbingan_limit']??10); if(!in_array($bimbinganLimit,[10,20,50],true)) $bimbinganLimit=10;
$bimbinganPage=max(1,(int)($_GET['bimbingan_page']??1)); $bimbinganOffset=($bimbinganPage-1)*$bimbinganLimit; $bimbinganTotal=0;$bimbingan=[];
$baseB=" FROM bimbingan b JOIN users m ON m.id=b.mahasiswa_id JOIN users d ON d.id=b.dosen_id";
if($bimbinganSearch!==''){
    $like='%'.$bimbinganSearch.'%';
    $s=$conn->prepare("SELECT COUNT(*) total".$baseB." WHERE m.nama_lengkap LIKE ? OR d.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ? OR b.status LIKE ?");
    $s->bind_param('ssss',$like,$like,$like,$like);$s->execute();$bimbinganTotal=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();
    $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.status,b.updated_at,b.dosen_id,m.nama_lengkap mahasiswa_nama,d.nama_lengkap dosen_nama,(SELECT COUNT(*) FROM bab_skripsi bs WHERE bs.bimbingan_id=b.id AND bs.status='disetujui' AND bs.nama_bab IN ('Bab 1','Bab 2','Bab 3','Bab 4','Bab 5')) AS bab_disetujui".$baseB." WHERE m.nama_lengkap LIKE ? OR d.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ? OR b.status LIKE ? ORDER BY b.updated_at DESC,b.id DESC LIMIT ? OFFSET ?");
    $s->bind_param('ssssii',$like,$like,$like,$like,$bimbinganLimit,$bimbinganOffset);$s->execute();$bimbingan=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}else{
    $r=$conn->query("SELECT COUNT(*) total".$baseB);if($r)$bimbinganTotal=(int)($r->fetch_assoc()['total']??0);
    $s=$conn->prepare("SELECT b.id,b.judul_skripsi,b.status,b.updated_at,b.dosen_id,m.nama_lengkap mahasiswa_nama,d.nama_lengkap dosen_nama,(SELECT COUNT(*) FROM bab_skripsi bs WHERE bs.bimbingan_id=b.id AND bs.status='disetujui' AND bs.nama_bab IN ('Bab 1','Bab 2','Bab 3','Bab 4','Bab 5')) AS bab_disetujui".$baseB." ORDER BY b.updated_at DESC,b.id DESC LIMIT ? OFFSET ?");
    $s->bind_param('ii',$bimbinganLimit,$bimbinganOffset);$s->execute();$bimbingan=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}
$bimbinganTotalPages=max(1,(int)ceil($bimbinganTotal/$bimbinganLimit)); if($bimbinganPage>$bimbinganTotalPages){$bimbinganPage=$bimbinganTotalPages;$bimbinganOffset=($bimbinganPage-1)*$bimbinganLimit;}
?>

<style>
.admin-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
.admin-stat{padding:18px 20px}
.admin-stat strong{display:block;font-size:28px;line-height:1.1;margin:6px 0}
.admin-stat span{display:block;color:#687684;font-size:13px}
.admin-stat-attention{border-color:#c9d7e3;background:#f8fbfd}
.admin-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);gap:18px;margin-bottom:18px}
.admin-status-row{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid #e6ebef}
.admin-status-row:last-child{border-bottom:0}
.admin-status-row strong{font-size:18px}
.admin-table-actions{display:flex;flex-direction:column;gap:8px;min-width:230px}
.admin-table-actions form{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.admin-table-actions select{min-width:150px}
.admin-title{max-width:310px;line-height:1.45}
.admin-progress{min-width:90px}
.admin-progress-track{height:7px;background:#e9edf0;border-radius:5px;overflow:hidden;margin-top:7px}
.admin-progress-bar{height:100%;background:#315d82}
.account-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:end;margin:16px 0}.account-search{display:flex;gap:8px;align-items:end}.account-search label,.account-limit label{font-weight:600;font-size:14px;display:block;margin-bottom:6px}.account-search-row{display:flex;gap:8px;align-items:center}.account-search-row input{min-width:300px}.account-limit{min-width:150px}.account-limit select{width:100%}.account-pagination{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:14px;padding-top:14px;border-top:1px solid #e6ebef}.account-pagination-info{color:#687684;font-size:13px}.account-pagination-controls{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.account-page-link{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;border:1px solid #d5dde4;border-radius:6px;background:#fff;color:#334e68;text-decoration:none;font-size:14px}.account-page-link:hover{background:#f5f8fa}.account-page-link.active{background:#315d82;color:#fff;border-color:#315d82;font-weight:600}.account-page-link.disabled{color:#9aa6b2;background:#f5f7f8;pointer-events:none}.account-page-ellipsis{padding:0 4px;color:#687684}@media(max-width:700px){.account-toolbar{display:block}.account-search-row{margin-top:0}.account-search-row input{min-width:0;flex:1}.account-limit{margin-top:12px;max-width:150px}.account-pagination{display:block}.account-pagination-controls{margin-top:10px}}
@media(max-width:1050px){.admin-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-layout{grid-template-columns:1fr}.admin-table-actions{min-width:190px}}
@media(max-width:640px){.admin-summary{grid-template-columns:1fr}.admin-table-actions form{align-items:stretch;flex-direction:column}.admin-table-actions select,.admin-table-actions .btn{width:100%}}
</style>

<div class="page-head">
  <div>
    <h1>Dashboard Admin</h1>
    <p class="muted">Pusat pemantauan pengguna, bimbingan, pengajuan judul, dan progres Bab 1–5.</p>
  </div>
  <span class="role-badge">Administrator</span>
  <div class="dashboard-refresh">
    <button class="btn btn-secondary" type="button" data-refresh-now>Muat Ulang</button>
  </div>
</div>

<section class="admin-summary">
  <article class="card admin-stat"><span>Total User</span><strong><?=e($totalUsers)?></strong><span>Seluruh akun MyThesis</span></article>
  <article class="card admin-stat"><span>Mahasiswa</span><strong><?=e($totalMahasiswa)?></strong><span>Pengguna mahasiswa</span></article>
  <article class="card admin-stat"><span>Dosen</span><strong><?=e($totalDosen)?></strong><span>Dosen pembimbing</span></article>
  <article class="card admin-stat"><span>Total Bimbingan</span><strong><?=e($totalBimbingan)?></strong><span>Seluruh pengajuan</span></article>
  <article class="card admin-stat"><span>Bimbingan Aktif</span><strong><?=e($totalAktif)?></strong><span>Sedang menjalani Bab 1–5</span></article>
  <article class="card admin-stat admin-stat-attention"><span>Pengajuan Judul</span><strong><?=e($totalPengajuan)?></strong><span>Menunggu persetujuan dosen</span></article>
  <article class="card admin-stat admin-stat-attention"><span>Review Bab</span><strong><?=e($totalReview)?></strong><span>Menunggu tindakan dosen</span></article>
  <article class="card admin-stat"><span>Selesai</span><strong><?=e($totalSelesai)?></strong><span>Bab 5 telah mendapat ACC</span></article>
</section>

<div class="admin-layout">
  <section class="card">
    <div class="section-heading">
      <div>
        <h2>Ringkasan Status Bimbingan</h2>
        <p class="muted">Jumlah bimbingan berdasarkan status saat ini.</p>
      </div>
    </div>
    <div class="admin-status-row"><span>Pengajuan Judul</span><strong><?=e($totalPengajuan)?></strong></div>
    <div class="admin-status-row"><span>Revisi Judul</span><strong><?=e($totalRevisiJudul)?></strong></div>
    <div class="admin-status-row"><span>Aktif</span><strong><?=e($totalAktif)?></strong></div>
    <div class="admin-status-row"><span>Ditangguhkan</span><strong><?=e($totalDitangguhkan)?></strong></div>
    <div class="admin-status-row"><span>Selesai</span><strong><?=e($totalSelesai)?></strong></div>
  </section>

  <section class="card">
    <div class="section-heading">
      <div>
        <h2>Pengguna Terbaru</h2>
        <p class="muted">Akun yang baru dibuat.</p>
      </div>
    </div>
    <?php if(!$users): ?>
      <div class="empty-state">Belum ada pengguna.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama</th><th>Peran</th></tr></thead>
          <tbody>
          <?php foreach($users as $u): ?>
            <tr>
              <td><strong><?=e($u['nama_lengkap'])?></strong><br><small class="muted"><?=e($u['email'])?></small></td>
              <td><?=e(ucfirst($u['role']))?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="admin-layout" id="tambah-dosen">
  <section class="card" id="daftar-dosen">
    <div class="section-heading">
      <div>
        <h2>Daftar Dosen</h2
        <p class="muted">Akun dosen yang dapat dipilih sebagai pembimbing.</p>
      </div>
    </div>
    <?php if(!$dosenRows): ?>
      <div class="empty-state">Belum ada akun dosen. Tambahkan lewat formulir di samping.</div>
    <?php else: ?>
      <form method="get" class="account-toolbar">
        <input type="hidden" name="page" value="admin-dashboard"><input type="hidden" name="dosen_page" value="1">
        <div class="account-search"><div><label for="dosen_q">Cari dosen</label><div class="account-search-row"><input id="dosen_q" name="dosen_q" type="search" value="<?=e($dosenSearch)?>" placeholder="Nama, username, email, atau telepon"><button class="btn btn-secondary" type="submit">Cari</button><?php if($dosenSearch!==''): ?><a class="btn btn-secondary" href="?page=admin-dashboard#daftar-dosen">Reset</a><?php endif; ?></div></div></div>
        <div class="account-limit"><label for="dosen_limit_top">Tampilkan</label><select id="dosen_limit_top" name="dosen_limit" onchange="this.form.submit()"><option value="10" <?=$dosenLimit===10?'selected':''?>>10</option><option value="20" <?=$dosenLimit===20?'selected':''?>>20</option><option value="50" <?=$dosenLimit===50?'selected':''?>>50</option></select></div>
      </form>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama</th><th>Username</th><th>Kontak</th><th>Bimbingan</th></tr></thead>
          <tbody>
          <?php foreach($dosenRows as $dl): ?>
            <tr>
              <td><strong><?=e($dl['nama_lengkap'])?></strong></td>
              <td><?=e($dl['username'])?></td>
              <td><?=e($dl['email'])?><?php if(!empty($dl['no_telp'])): ?><br><small class="muted"><?=e($dl['no_telp'])?></small><?php endif; ?></td>
              <td><?=e((int)$dl['total_bimbingan'])?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?=render_pagination($dosenPage,$dosenTotalPages,$dosenTotal,$dosenOffset,$dosenLimit,['page'=>'admin-dashboard','dosen_q'=>$dosenSearch,'dosen_limit'=>$dosenLimit],'dosen_page','#daftar-dosen','dosen')?>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="section-heading">
      <div>
        <h2>Tambah Akun Dosen</h2>
        <p class="muted">Buat akun dosen baru. Password awal sampaikan langsung kepada dosen.</p>
      </div>
    </div>
    <form method="post" action="?page=admin-dashboard#tambah-dosen" autocomplete="off">
      <input type="hidden" name="action" value="admin_create_dosen">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <div class="form-group"><label for="dosen_nama">Nama Lengkap</label><input id="dosen_nama" name="nama_lengkap" required maxlength="150" value="<?=e($old['nama_lengkap']??'')?>"></div>
      <div class="form-group"><label for="dosen_username">Username</label><input id="dosen_username" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" title="Huruf, angka, titik, garis bawah, atau strip (3–50 karakter)" value="<?=e($old['username']??'')?>"></div>
      <div class="form-group"><label for="dosen_email">Email</label><input id="dosen_email" name="email" type="email" required maxlength="100" value="<?=e($old['email']??'')?>"></div>
      <div class="form-group"><label for="dosen_telp">No. Telepon (opsional)</label><input id="dosen_telp" name="no_telp" maxlength="15" value="<?=e($old['no_telp']??'')?>"></div>
      <div class="form-group"><label for="dosen_password">Password Awal</label><input id="dosen_password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
      <div class="form-group"><label for="dosen_confirm">Konfirmasi Password</label><input id="dosen_confirm" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
      <button class="btn btn-success" type="submit">Buat Akun Dosen</button>
    </form>
  </section>
</div>

<section class="card" style="margin-top:18px" id="manajemen-akun">
  <div class="section-heading">
    <div>
      <h2>Manajemen Akun</h2>
      <p class="muted">Hapus akun mahasiswa atau dosen beserta data bimbingan dan file terkait.</p>
    </div>
  </div>
  <form method="get" class="account-toolbar">
    <input type="hidden" name="page" value="admin-dashboard">
    <div class="account-search">
      <div>
        <label for="account_q">Cari berdasarkan nama</label>
        <div class="account-search-row">
          <input id="account_q" name="account_q" type="search" value="<?=e($accountSearch)?>" placeholder="Masukkan nama lengkap">
          <button class="btn btn-secondary" type="submit">Cari</button>
          <?php if($accountSearch!==''): ?><a class="btn btn-secondary" href="?page=admin-dashboard#manajemen-akun">Reset</a><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="account-limit">
      <label for="account_limit_top">Tampilkan</label>
      <select id="account_limit_top" name="account_limit" onchange="this.form.submit()">
        <option value="10" <?=$accountLimit===10?'selected':''?>>10</option>
        <option value="20" <?=$accountLimit===20?'selected':''?>>20</option>
        <option value="50" <?=$accountLimit===50?'selected':''?>>50</option>
      </select>
    </div>
  </form>
  <?php if(!$accountRows): ?>
    <div class="empty-state">Belum ada akun pengguna.</div>
  <?php else: ?>
    <form method="get" class="account-toolbar">
      <input type="hidden" name="page" value="admin-dashboard"><input type="hidden" name="bimbingan_page" value="1">
      <div class="account-search"><div><label for="bimbingan_q">Cari bimbingan</label><div class="account-search-row"><input id="bimbingan_q" name="bimbingan_q" type="search" value="<?=e($bimbinganSearch)?>" placeholder="Mahasiswa, dosen, judul, atau status"><button class="btn btn-secondary" type="submit">Cari</button><?php if($bimbinganSearch!==''): ?><a class="btn btn-secondary" href="?page=admin-dashboard#manajemen-bimbingan">Reset</a><?php endif; ?></div></div></div>
      <div class="account-limit"><label for="bimbingan_limit_top">Tampilkan</label><select id="bimbingan_limit_top" name="bimbingan_limit" onchange="this.form.submit()"><option value="10" <?=$bimbinganLimit===10?'selected':''?>>10</option><option value="20" <?=$bimbinganLimit===20?'selected':''?>>20</option><option value="50" <?=$bimbinganLimit===50?'selected':''?>>50</option></select></div>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Dibuat</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($accountRows as $u): ?>
          <tr>
            <td><strong><?=e($u['nama_lengkap'])?></strong><br><small class="muted"><?=e($u['username'])?></small></td>
            <td><?=e($u['email'])?></td>
            <td><?=e(ucfirst($u['role']))?></td>
            <td><?=e(formatDate($u['created_at']))?></td>
            <td>
              <?php if((int)$u['id']===$uid): ?>
                <span class="muted">Akun aktif</span>
              <?php else: ?>
                <?php if($u['role']==='mahasiswa'): ?><div style="margin-bottom:8px"><?=impersonate_form((int)$u['id'],(string)$u['nama_lengkap'])?></div><?php endif; ?>
                <form method="post" onsubmit="return confirm(<?=e(json_encode('Hapus akun '.$u['nama_lengkap'].'? Data bimbingan, revisi, notifikasi, dan file terkait akun ini juga akan dihapus. Tindakan ini tidak dapat dibatalkan.',JSON_UNESCAPED_UNICODE))?>);">
                  <input type="hidden" name="action" value="admin_delete_user">
                  <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="user_id" value="<?=e($u['id'])?>">
                  <button class="btn btn-danger" type="submit">Hapus Akun</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
      $pageNumbers=[1];
      $start=max(2,$accountPage-2);
      $end=min($accountTotalPages-1,$accountPage+2);
      for($p=$start;$p<=$end;$p++) $pageNumbers[]=$p;
      if($accountTotalPages>1) $pageNumbers[]=$accountTotalPages;
      $pageNumbers=array_values(array_unique($pageNumbers));
    ?>
    <div class="account-pagination">
      <div class="account-pagination-info">Menampilkan <?=e($accountTotal ? $accountOffset+1 : 0)?>–<?=e(min($accountOffset+$accountLimit,$accountTotal))?> dari <?=e($accountTotal)?> akun</div>
      <div class="account-pagination-controls">
        <?php if($accountPage>1): ?><a class="account-page-link" href="?page=admin-dashboard&amp;account_q=<?=urlencode($accountSearch)?>&amp;account_limit=<?=$accountLimit?>&amp;account_page=<?=$accountPage-1?>#manajemen-akun">‹ Sebelumnya</a><?php else: ?><span class="account-page-link disabled">‹ Sebelumnya</span><?php endif; ?>
        <?php $previousPage=0; foreach($pageNumbers as $p): ?>
          <?php if($previousPage && $p>$previousPage+1): ?><span class="account-page-ellipsis">…</span><?php endif; ?>
          <?php if($p===$accountPage): ?><span class="account-page-link active" aria-current="page"><?=$p?></span><?php else: ?><a class="account-page-link" href="?page=admin-dashboard&amp;account_q=<?=urlencode($accountSearch)?>&amp;account_limit=<?=$accountLimit?>&amp;account_page=<?=$p?>#manajemen-akun"><?=$p?></a><?php endif; ?>
          <?php $previousPage=$p; ?>
        <?php endforeach; ?>
        <?php if($accountPage<$accountTotalPages): ?><a class="account-page-link" href="?page=admin-dashboard&amp;account_q=<?=urlencode($accountSearch)?>&amp;account_limit=<?=$accountLimit?>&amp;account_page=<?=$accountPage+1?>#manajemen-akun">Berikutnya ›</a><?php else: ?><span class="account-page-link disabled">Berikutnya ›</span><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>

<section class="card" id="manajemen-bimbingan">
  <div class="section-heading">
    <div>
      <h2>Manajemen Bimbingan</h2
      <p class="muted">Pantau seluruh mahasiswa, dosen pembimbing, progres Bab 1–5, dan status bimbingan.</p>
    </div>
  </div>

  <?php if(!$bimbingan): ?>
    <div class="empty-state">Belum ada data bimbingan.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Mahasiswa</th>
            <th>Judul</th>
            <th>Pembimbing</th>
            <th>Progress</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($bimbingan as $b): $progress=min(100,(int)$b['bab_disetujui']*20); ?>
          <tr>
            <td><strong><?=e($b['mahasiswa_nama'])?></strong></td>
            <td><div class="admin-title"><a href="?page=bimbingan-detail&id=<?=e($b['id'])?>"><?=e($b['judul_skripsi'])?></a></div></td>
            <td>
              <form method="post" class="inline-form">
                <input type="hidden" name="action" value="admin_assign_dosen">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                <input type="hidden" name="bimbingan_id" value="<?=e($b['id'])?>">
                <select name="dosen_id" aria-label="Dosen pembimbing">
                  <?php foreach($dosen as $d): ?>
                    <option value="<?=$d['id']?>" <?=((int)$d['id']===(int)$b['dosen_id']?'selected':'')?>><?=e($d['nama_lengkap'])?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-secondary" type="submit">Simpan</button>
              </form>
            </td>
            <td class="admin-progress">
              <strong><?=e($progress)?>%</strong>
              <div class="admin-progress-track"><div class="admin-progress-bar" style="width:<?=e($progress)?>%"></div></div>
              <small class="muted"><?=e((int)$b['bab_disetujui'])?> dari 5 bab ACC</small>
            </td>
            <td><?=getStatusBadge($b['status'])?></td>
            <td>
              <div class="admin-table-actions">
                <a class="btn btn-secondary" href="?page=bimbingan-detail&id=<?=e($b['id'])?>">Detail Bimbingan</a>
                <form method="post">
                  <input type="hidden" name="action" value="admin_bimbingan_status">
                  <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="bimbingan_id" value="<?=e($b['id'])?>">
                  <select name="status" aria-label="Status bimbingan">
                    <option value="pengajuan_judul" <?=$b['status']==='pengajuan_judul'?'selected':''?>>Pengajuan Judul</option>
                    <option value="aktif" <?=$b['status']==='aktif'?'selected':''?>>Aktif</option>
                    <option value="selesai" <?=$b['status']==='selesai'?'selected':''?>>Selesai</option>
                    <option value="ditangguhkan" <?=$b['status']==='ditangguhkan'?'selected':''?>>Ditangguhkan</option>
                  </select>
                  <button class="btn btn-secondary" type="submit">Simpan Status</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?=render_pagination($bimbinganPage,$bimbinganTotalPages,$bimbinganTotal,$bimbinganOffset,$bimbinganLimit,['page'=>'admin-dashboard','bimbingan_q'=>$bimbinganSearch,'bimbingan_limit'=>$bimbinganLimit],'bimbingan_page','#manajemen-bimbingan','bimbingan')?>
  <?php endif; ?>
</section>

<section class="card" style="margin-top:18px">
  <h2>Ruang Lingkup Admin</h2>
  <p class="muted">Admin menangani administrasi pengguna dan bimbingan. Persetujuan judul serta review dan ACC Bab 1–5 tetap mengikuti dosen pembimbing. Perubahan dosen atau status administratif dari panel ini mengirim notifikasi kepada mahasiswa terkait.</p>
</section>

<script>
(function(){
  const button=document.querySelector('[data-refresh-now]');
  if(button) button.addEventListener('click',function(){ window.location.reload(); });
})();
</script>
