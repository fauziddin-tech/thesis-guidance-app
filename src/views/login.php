<div class="auth-card card">
<h1>Masuk ke MyThesis</h1><p class="muted">Kelola bimbingan skripsi dalam satu tempat.</p>
<form method="post">
<input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="form-group"><label for="identity">Username atau Email</label><input id="identity" name="identity" type="text" autocomplete="username" required></div>
<div class="form-group"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
<button class="btn btn-primary" type="submit">Masuk</button>
</form><p class="muted"><a href="?page=forgot-password">Lupa password?</a></p><p class="muted">Belum punya akun? <a href="?page=register">Daftar sebagai mahasiswa</a></p>
</div>