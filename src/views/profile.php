<?php
if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit();
}

require_once 'src/controllers/UserController.php';
require_once 'src/helpers/Functions.php';

$user_ctrl = new UserController($conn);
$user_id = $_SESSION['user']['id'];
$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = sanitize($_POST['nama_lengkap'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $no_telp = sanitize($_POST['no_telp'] ?? '');
    
    $errors = [];
    if (empty($nama_lengkap)) $errors[] = 'Nama lengkap harus diisi';
    if (empty($email)) $errors[] = 'Email harus diisi';
    if (!isValidEmail($email)) $errors[] = 'Format email tidak valid';
    if (empty($no_telp)) $errors[] = 'No. telepon harus diisi';
    
    if (empty($errors)) {
        $result = $user_ctrl->update($user_id, $nama_lengkap, $email, $no_telp);
        if ($result['success']) {
            // Update session
            $_SESSION['user']['nama_lengkap'] = $nama_lengkap;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['no_telp'] = $no_telp;
            
            $_SESSION['message'] = 'Profil berhasil diperbarui';
            $_SESSION['message_type'] = 'success';
            header('Location: ?page=profile');
            exit();
        }
    }
}
?>

<div style="max-width: 600px; margin: 30px auto;">
    <div class="card">
        <h2>Profil Saya</h2>
        
        <?php 
        $message = flash('message');
        if ($message): 
        ?>
            <div style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                ✅ <?php echo $message; ?>
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
                <label for="username">Username</label>
                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <small style="color: #666;">Tidak dapat diubah</small>
            </div>
            
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" required value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>
            
            <div class="form-group">
                <label for="no_telp">No. Telepon</label>
                <input type="tel" id="no_telp" name="no_telp" required value="<?php echo htmlspecialchars($user['no_telp'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled>
                <small style="color: #666;">Tidak dapat diubah</small>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                <a href="?page=change-password" class="btn btn-secondary">Ubah Password</a>
            </div>
        </form>
    </div>
</div>
