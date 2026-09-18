<div class="auth-card card"><h1>Lupa Password</h1><p class="muted">Masukkan email akun Anda. Jika email terdaftar, kami akan mengirim tautan untuk mengatur ulang password.</p>
<form method="post"><input type="hidden" name="action" value="forgot_password"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required></div>
<button class="btn btn-primary" type="submit">Kirim Tautan Reset</button> <a class="btn btn-secondary" href="?page=login">Kembali</a>
</form></div>