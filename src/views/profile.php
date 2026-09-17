<?php
require_login();
$userId=(int)$_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $nama=trim($_POST['nama_lengkap']??''); $email=trim($_POST['email']??''); $telp=trim($_POST['no_telp']??'');
    if ($nama==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        flash('danger','Nama lengkap dan email yang valid wajib diisi.');
    } else {
        $stmt=$conn->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1'); $stmt->bind_param('si',$email,$userId); $stmt->execute(); $exists=$stmt->get_result()->num_rows>0; $stmt->close();
        if($exists) flash('danger','Email sudah digunakan pengguna lain.');
        else {
            $stmt=$conn->prepare('UPDATE users SET nama_lengkap=?,email=?,no_telp=? WHERE id=?'); $stmt->bind_param('sssi',$nama,$email,$telp,$userId);
            if($stmt->execute()) { $_SESSION['user']['nama_lengkap']=$nama; $_SESSION['user']['email']=$email; $_SESSION['user']['no_telp']=$telp; flash('success','Profil berhasil diperbarui.'); }
            else flash('danger','Profil gagal diperbarui.');
            $stmt->close();
        }
    }
    redirect('?page=profile');
}
$user=$_SESSION['user'];
?>
<div class="card" style="max-width:700px;margin:20px auto">
<h2>Profil Saya</h2>
<form method="post">
<input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="form-group"><label>Username</label><input value="<?=e($user['username'])?>" disabled></div>
<div class="form-group"><label>Nama Lengkap</label><input name="nama_lengkap" required value="<?=e($user['nama_lengkap'])?>"></div>
<div class="form-group"><label>Email</label><input type="email" name="email" required value="<?=e($user['email'])?>"></div>
<div class="form-group"><label>No. Telepon</label><input name="no_telp" value="<?=e($user['no_telp']??'')?>"></div>
<div class="form-group"><label>Role</label><input value="<?=e(ucfirst($user['role']))?>" disabled></div>
<button class="btn btn-success">Simpan Perubahan</button>
<a class="btn btn-secondary" href="?page=change-password">Ubah Password</a>
<a class="btn btn-secondary" href="?page=dashboard">Kembali</a>
</form>
</div>
