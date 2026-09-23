<?php
// Lupa password: kirim tautan atur ulang (berlaku 1 jam) ke email terdaftar melalui Resend.
$forgotMessage = '';$forgotType = 'success';
$resetReady = column_exists($conn, 'password_resets', 'token_hash');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $genericMessage = 'Jika email tersebut terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk dan folder spam.';
    if (!verify_csrf()) {$forgotMessage = 'Sesi formulir telah berakhir. Muat ulang halaman dan coba kembali.';$forgotType = 'danger';}
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {$forgotMessage = 'Masukkan alamat email yang valid.';$forgotType = 'danger';}
    elseif (!$resetReady) {$forgotMessage = 'Fitur lupa password belum aktif. Hubungi administrator.';$forgotType = 'danger';}
    else {
        $rateKeyEmail = rl_key($email);$rateKeyIp = rl_key(client_ip());
        // Pesan selalu sama agar tidak dapat dipakai menebak email yang terdaftar.
        if (rl_count($conn, 'forgot', $rateKeyEmail, 900) < 3 && rl_count($conn, 'forgot_ip', $rateKeyIp, 900) < 10) {
            rl_hit($conn, 'forgot', $rateKeyEmail);rl_hit($conn, 'forgot_ip', $rateKeyIp);
            $stmt = $conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$stmt->bind_param('s', $email);$stmt->execute();$account = $stmt->get_result()->fetch_assoc();$stmt->close();
            if ($account) {
                $token = bin2hex(random_bytes(32));$hash = hash('sha256', $token);$accountId = (int)$account['id'];
                $stmt = $conn->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');$stmt->bind_param('i', $accountId);$stmt->execute();$stmt->close();
                $stmt = $conn->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))');$stmt->bind_param('is', $accountId, $hash);
                if ($stmt->execute()) send_user_email($conn, $accountId, 'Atur ulang password MyThesis', 'Permintaan atur ulang password', "Kami menerima permintaan untuk mengatur ulang password akun MyThesis Anda.\n\nTautan berlaku selama 1 jam dan hanya dapat dipakai satu kali. Jika Anda tidak memintanya, abaikan email ini; password Anda tidak berubah.", '?page=reset-password&token=' . $token, 'Atur Ulang Password');
                $stmt->close();
            }
        }
        $forgotMessage = $genericMessage;
    }
}
?>
<div class="auth-card card"><span class="eyebrow">PEMULIHAN AKUN</span><h1>Lupa Password</h1><p>Masukkan email akun Anda. Jika terdaftar, kami kirimkan tautan untuk mengatur ulang password.</p><?php if($forgotMessage!==''): ?><div class="alert alert-<?=h($forgotType)?>" role="alert"><?=h($forgotMessage)?></div><?php endif; ?><form method="post"><?=csrf_field()?><div class="form-group"><label for="forgot_email">Email</label><input id="forgot_email" name="email" type="email" autocomplete="email" required maxlength="100" value="<?=h($_POST['email'] ?? '')?>"></div><button class="btn btn-primary btn-block" type="submit">Kirim Tautan Atur Ulang</button></form><p class="auth-switch"><a href="?page=login">Kembali ke halaman masuk</a></p></div>
