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

$dosenRows=[];
$r=$conn->query("SELECT u.id,u.nama_lengkap,u.username,u.email,u.no_telp,
                        (SELECT COUNT(*) FROM bimbingan b WHERE b.dosen_id=u.id) AS total_bimbingan
                 FROM users u WHERE u.role='dosen' ORDER BY u.nama_lengkap ASC");
if($r) $dosenRows=$r->fetch_all(MYSQLI_ASSOC);

$old=$_SESSION['old_dosen_form']??[];
unset($_SESSION['old_dosen_form']);

$bimbingan=[];
$r=$conn->query("SELECT b.id,b.judul_skripsi,b.status,b.updated_at,b.dosen_id,
                        m.nama_lengkap mahasiswa_nama,
                        d.nama_lengkap dosen_nama,
                        (SELECT COUNT(*) FROM bab_skripsi bs WHERE bs.bimbingan_id=b.id AND bs.status='disetujui' AND bs.nama_bab IN ('Bab 1','Bab 2','Bab 3','Bab 4','Bab 5')) AS bab_disetujui
                 FROM bimbingan b
                 JOIN users m ON m.id=b.mahasiswa_id
                 JOIN users d ON d.id=b.dosen_id
                 ORDER BY b.updated_at DESC,b.id DESC
                 LIMIT 30");
if($r) $bimbingan=$r->fetch_all(MYSQLI_ASSOC);
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
@media(max-width:1050px){.admin-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-layout{grid-template-columns:1fr}.admin-table-actions{min-width:190px}}
@media(max-width:640px){.admin-summary{grid-template-columns:1fr}.admin-table-actions form{align-items:stretch;flex-direction:column}.admin-table-actions select,.admin-table-actions .btn{width:100%}}
</style>

<div class="page-head">
  <div>
    <h1>Dashboard Admin</h1>
    <p class="muted">Pusat pemantauan pengguna, bimbingan, pengajuan judul, dan progres Bab 1–5.</p>
  </div>
  <span class="role-badge">Administrator</span>
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
  <section class="card">
    <div class="section-heading">
      <div>
        <h2>Daftar Dosen</h2>
        <p class="muted">Akun dosen yang dapat dipilih sebagai pembimbing.</p>
      </div>
    </div>
    <?php if(!$dosenRows): ?>
      <div class="empty-state">Belum ada akun dosen. Tambahkan lewat formulir di samping.</div>
    <?php else: ?>
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

<section class="card">
  <div class="section-heading">
    <div>
      <h2>Manajemen Bimbingan</h2>
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
  <?php endif; ?>
</section>

<section class="card" style="margin-top:18px">
  <h2>Ruang Lingkup Admin</h2>
  <p class="muted">Admin menangani administrasi pengguna dan bimbingan. Persetujuan judul serta review dan ACC Bab 1–5 tetap mengikuti dosen pembimbing. Perubahan dosen atau status administratif dari panel ini mengirim notifikasi kepada mahasiswa terkait.</p>
</section>
