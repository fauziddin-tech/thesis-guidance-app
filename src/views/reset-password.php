<?php
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$tokenValid = false;
if (preg_match('/^[a-f0-9]{64}$/', $token) && isset($conn)) {
    $hash = hash('sha256', $token);
    $s = $conn->prepare('SELECT id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
    if ($s) {
        $s->bind_param('s', $hash);
        $s->execute();
        $tokenValid = (bool)$s->get_result()->fetch_assoc();
        $s->close();
    }
}
?>
<div class="auth-card card">
<h1>Reset Password</h1>
<?php if (!$tokenValid): ?>
    <p class="muted">Tautan reset tidak valid, sudah dipakai, atau sudah kedaluwarsa. Silakan minta tautan baru.</p>
    <a class="btn btn-primary" href="?page=forgot-password">Minta Tautan Baru</a>
<?php else: ?>
    <p class="muted">Masukkan password baru untuk akun Anda (minimal 8 karakter).</p>
    <form method="post">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
        <input type="hidden" name="token" value="<?=e($token)?>">
        <div class="form-group">
            <label for="password">Password baru</label>
            <input id="password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="form-group">
            <label for="password_confirm">Ulangi password baru</label>
            <input id="password_confirm" name="new_password_confirm" type="password" minlength="8" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary" type="submit">Simpan Password</button>
        <a class="btn btn-secondary" href="?page=login">Batal</a>
    </form>
<?php endif; ?>
</div>
