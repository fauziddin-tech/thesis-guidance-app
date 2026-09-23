<?php
// Atur ulang password memakai token dari email (disimpan sebagai hash SHA-256, berlaku 1 jam, sekali pakai).
$token = is_string($_GET['token'] ?? null) ? (string)$_GET['token'] : (string)($_POST['token'] ?? '');
$resetMessage = '';$resetDone = false;
$findToken = function (string $token) use ($conn): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || !column_exists($conn, 'password_resets', 'token_hash')) return null;
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT id,user_id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
    $stmt->bind_param('s', $hash);$stmt->execute();$row = $stmt->get_result()->fetch_assoc();$stmt->close();
    return $row ?: null;
};
$reset = $findToken($token);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $newPassword = (string)($_POST['new_password'] ?? '');$confirmPassword = (string)($_POST['new_password_confirm'] ?? '');
    if (!verify_csrf()) $resetMessage = 'Sesi formulir telah berakhir. Muat ulang halaman dan coba kembali.';
    elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) $resetMessage = 'Password baru harus 8–72 karakter.';
    elseif ($newPassword !== $confirmPassword) $resetMessage = 'Konfirmasi password tidak sama.';
    else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);$userIdToReset = (int)$reset['user_id'];$resetId = (int)$reset['id'];
        $conn->begin_transaction();
        $stmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');$stmt->bind_param('si', $hash, $userIdToReset);$ok = $stmt->execute();$stmt->close();
        if ($ok) {$stmt = $conn->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');$stmt->bind_param('i', $userIdToReset);$ok = $stmt->execute();$stmt->close();}
        if ($ok) $conn->commit(); else $conn->rollback();
        if ($ok) {
            $resetDone = true;
            send_user_email($conn, $userIdToReset, 'Password MyThesis Anda telah diubah', 'Password berhasil diubah', "Password akun MyThesis Anda baru saja diatur ulang. Jika bukan Anda yang melakukannya, segera hubungi administrator.", '?page=login', 'Masuk ke MyThesis');
        } else $resetMessage = 'Password belum dapat disimpan. Silakan coba kembali.';
    }
}
?>
<div class="auth-card card"><span class="eyebrow">PEMULIHAN AKUN</span><h1>Atur Ulang Password</h1>
<?php if($resetDone): ?><div class="alert alert-success" role="alert">Password berhasil diubah. Silakan masuk dengan password baru Anda.</div><a class="btn btn-primary btn-block" href="?page=login">Masuk ke MyThesis</a>
<?php elseif(!$reset): ?><p>Tautan atur ulang tidak valid, sudah dipakai, atau sudah kedaluwarsa (berlaku 1 jam).</p><a class="btn btn-primary btn-block" href="?page=forgot-password">Minta Tautan Baru</a>
<?php else: ?><p>Masukkan password baru untuk akun Anda.</p><?php if($resetMessage!==''): ?><div class="alert alert-danger" role="alert"><?=h($resetMessage)?></div><?php endif; ?><form method="post" action="?page=reset-password"><?=csrf_field()?><input type="hidden" name="token" value="<?=h($token)?>"><div class="form-group"><label for="new_password">Password baru</label><div class="password-field"><input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required><button type="button" data-password-toggle aria-controls="new_password">Lihat</button></div><small class="field-help">Minimal 8 karakter.</small></div><div class="form-group"><label for="new_password_confirm">Ulangi password baru</label><div class="password-field"><input id="new_password_confirm" name="new_password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required><button type="button" data-password-toggle aria-controls="new_password_confirm">Lihat</button></div></div><button class="btn btn-primary btn-block" type="submit">Simpan Password Baru</button></form><?php endif; ?>
</div>
