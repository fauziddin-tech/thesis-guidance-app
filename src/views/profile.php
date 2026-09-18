<?php
require_login();
$userId=(int)$_SESSION['user']['id'];
$user=$_SESSION['user'];
?>
<div class="card profile-card">
  <div class="profile-header">
    <div class="profile-photo-wrap">
      <?php if(!empty($user['profile_photo'])): ?>
        <img class="profile-photo" src="profile-photo.php" alt="Foto profil">
      <?php else: ?>
        <div class="profile-photo profile-photo-placeholder">Foto</div>
      <?php endif; ?>
    </div>
    <div>
      <h2>Profil Saya</h2>
      <p class="muted">Kelola informasi akun dan foto profil Anda.</p>
    </div>
  </div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_profile">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <div class="form-group">
      <label>Foto Profil</label>
      <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
      <small class="muted">JPG, PNG, atau WEBP. Maksimal 2 MB.</small>
    </div>
    <div class="form-group"><label>Username</label><input value="<?=e($user['username'])?>" disabled></div>
    <div class="form-group"><label>Nama Lengkap</label><input name="nama_lengkap" required value="<?=e($user['nama_lengkap'])?>"></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?=e($user['email'])?>"></div>
    <div class="form-group"><label>No. Telepon</label><input name="no_telp" value="<?=e($user['no_telp']??'')?>"></div>
    <div class="form-group"><label>Role</label><input value="<?=e(ucfirst($user['role']))?>" disabled></div>
    <div class="actions">
      <button class="btn btn-success" type="submit">Simpan Perubahan</button>
      <a class="btn btn-secondary" href="?page=change-password">Ubah Password</a>
      <a class="btn btn-secondary" href="?page=dashboard">Kembali</a>
    </div>
  </form>
</div>
