<?php
if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit();
}

require_once 'src/controllers/UserController.php';
require_once 'src/helpers/Functions.php';

$user_ctrl = new UserController($conn);
$user_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';
    
    $errors = [];
    
    if (empty($old_password)) $errors[] = 'Password lama harus diisi';
    if (empty($new_password)) $errors[] = 'Password baru harus diisi';
    if (strlen($new_password) < 6) $errors[] = 'Password baru minimal 6 karakter';
    if ($new_password !== $new_password_confirm) $errors[] = 'Password baru tidak cocok';
    
    if (empty($errors)) {
        $result = $user_ctrl->changePassword($user_id, $old_password, $new_password);
        if ($result['success']) {
            $_SESSION['message'] = 'Password berhasil diubah';
            $_SESSION['message_type'] = 'success';
            header('Location: ?page=profile');
            exit();
        } else {
            $error = $result['error'];
        }
    }
}

$user = $_SESSION['user'];
?>

<div style="max-width: 600px; margin: 30px auto;">
    <div class="card">
        <h2>Ubah Password</h2>
        
        <?php if (isset($error)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <strong>❌ Terjadi kesalahan:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="old_password">Password Lama *</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">Password Baru *</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
                <small style="color: #666;">Minimal 6 karakter</small>
            </div>
            
            <div class="form-group">
                <label for="new_password_confirm">Konfirmasi Password Baru *</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="6">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">Simpan Password Baru</button>
                <a href="?page=profile" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
