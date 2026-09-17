<?php
require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $old=$_POST['old_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['new_password_confirm']??'';
    if($old==='' || strlen($new)<8 || $new!==$confirm) {
        flash('danger','Password lama wajib diisi, password baru minimal 8 karakter dan konfirmasi harus sama.');
    } else {
        $id=(int)$_SESSION['user']['id']; $stmt=$conn->prepare('SELECT password FROM users WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if(!$row || !password_verify($old,$row['password'])) flash('danger','Password lama tidak sesuai.');
        else {
            $hash=password_hash($new,PASSWORD_DEFAULT); $stmt=$conn->prepare('UPDATE users SET password=? WHERE id=?'); $stmt->bind_param('si',$hash,$id); $ok=$stmt->execute(); $stmt->close();
            flash($ok?'success':'danger',$ok?'Password berhasil diubah.':'Password gagal diubah.');
        }
    }
    redirect('?page=change-password');
}
?>
<div class="card" style="max-width:600px;margin:20px auto"><h2>Ubah Password</h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="form-group"><label>Password Lama</label><input type="password" name="old_password" required></div>
<div class="form-group"><label>Password Baru</label><input type="password" name="new_password" required minlength="8"></div>
<div class="form-group"><label>Konfirmasi Password Baru</label><input type="password" name="new_password_confirm" required minlength="8"></div>
<button class="btn btn-success">Simpan Password</button> <a class="btn btn-secondary" href="?page=profile">Kembali</a>
</form></div>
