<?php
$loginError='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $identity=trim($_POST['username']??'');$password=$_POST['password']??'';
    if(!verify_csrf()){$loginError='Sesi formulir telah berakhir. Muat ulang halaman dan coba kembali.';}
    else{
        $stmt=$conn->prepare('SELECT * FROM users WHERE username=? OR email=? LIMIT 1');$stmt->bind_param('ss',$identity,$identity);$stmt->execute();$account=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($account&&password_verify($password,$account['password'])){unset($account['password']);session_regenerate_id(true);$_SESSION['user']=$account;unset($_SESSION['csrf_token']);header('Location: ?page=dashboard');exit;}
        $loginError='Username, email, atau password tidak sesuai.';
    }
}
?>
<div class="auth-shell"><aside class="auth-intro"><span class="eyebrow">SELAMAT DATANG KEMBALI</span><h1>Lanjutkan proses skripsi Anda.</h1><p>Masuk untuk melihat dokumen, catatan revisi, dan progres bimbingan terbaru.</p><ul><li>Dokumen tersimpan teratur</li><li>Status bimbingan mudah dipantau</li><li>Akses sesuai peran pengguna</li></ul></aside><div class="auth-card card"><span class="eyebrow">AKUN MYTHESIS</span><h2>Masuk</h2><p>Gunakan username atau alamat email yang terdaftar.</p><?php if($loginError!==''): ?><div class="alert alert-danger" role="alert"><?=h($loginError)?></div><?php endif; ?><form method="post"><?=csrf_field()?><div class="form-group"><label for="username">Username atau Email</label><input type="text" id="username" name="username" autocomplete="username" value="<?=h($_POST['username']??'')?>" required autofocus></div><div class="form-group"><label for="password">Password</label><div class="password-field"><input type="password" id="password" name="password" autocomplete="current-password" required><button type="button" data-password-toggle aria-controls="password">Lihat</button></div></div><button type="submit" class="btn btn-primary btn-block">Masuk ke MyThesis</button></form><p class="auth-switch">Belum punya akun? <a href="?page=register">Daftar sebagai mahasiswa</a></p></div></div>
