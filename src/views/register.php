<div class="auth-card card"><h1>Buat Akun Mahasiswa</h1><p class="muted">Akun dosen dan admin dibuat oleh administrator.</p>
<form method="post"><input type="hidden" name="action" value="register"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="form-grid"><div class="form-group"><label>Nama Lengkap</label><input name="nama_lengkap" required maxlength="150"></div><div class="form-group"><label>No. Telepon</label><input name="no_telp" maxlength="15"></div></div>
<div class="form-group"><label>Username</label><input name="username" autocomplete="username" required maxlength="100"></div>
<div class="form-group"><label>Email</label><input name="email" type="email" autocomplete="email" required maxlength="100"></div>
<div class="form-grid"><div class="form-group"><label>Password</label><input name="password" type="password" autocomplete="new-password" minlength="8" required></div><div class="form-group"><label>Konfirmasi Password</label><input name="confirm_password" type="password" autocomplete="new-password" minlength="8" required></div></div>
<button class="btn btn-success" type="submit">Daftar</button></form><p class="muted">Sudah punya akun? <a href="?page=login">Masuk</a></p></div>
