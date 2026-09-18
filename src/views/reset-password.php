<?php $resetToken=$_GET['token']??''; ?>
<div class="auth-card card"><h1>Reset Password</h1><p class="muted">Buat password baru. Tautan reset berlaku selama 1 jam.</p>
<form method="post"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="token" value="<?=e($resetToken)?>">
<div class="form-group"><label for="new_password">Password Baru</label><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" required></div>
<div class="form-group"><label for="new_password_confirm">Konfirmasi Password Baru</label><input id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password" minlength="8" required></div>
<button class="btn btn-success" type="submit">Simpan Password Baru</button> <a class="btn btn-secondary" href="?page=login">Kembali</a>
</form></div>