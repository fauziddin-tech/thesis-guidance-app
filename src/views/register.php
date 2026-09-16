<?php
// Handle register form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = sanitize($_POST['nama_lengkap'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $no_telp = sanitize($_POST['no_telp'] ?? '');
    $role = $_POST['role'] ?? 'mahasiswa';
    
    $errors = [];
    
    // Validasi input
    if (empty($nama_lengkap)) $errors[] = 'Nama lengkap harus diisi';
    if (empty($email)) $errors[] = 'Email harus diisi';
    if (!isValidEmail($email)) $errors[] = 'Format email tidak valid';
    if (empty($username)) $errors[] = 'Username harus diisi';
    if (strlen($username) < 3) $errors[] = 'Username minimal 3 karakter';
    if (empty($password)) $errors[] = 'Password harus diisi';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter';
    if ($password !== $password_confirm) $errors[] = 'Password tidak cocok';
    if (empty($no_telp)) $errors[] = 'No. telepon harus diisi';
    
    if (empty($errors)) {
        // Cek username/email sudah ada
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username atau email sudah terdaftar';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $query = "INSERT INTO users (username, email, password, role, nama_lengkap, no_telp) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssss', $username, $email, $hashed_password, $role, $nama_lengkap, $no_telp);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Pendaftaran berhasil! Silakan login.';
                $_SESSION['message_type'] = 'success';
                header('Location: ?page=login');
                exit();
            } else {
                $errors[] = 'Gagal mendaftar. Silakan coba lagi.';
            }
        }
    }
}

require_once 'src/helpers/Functions.php';
?>

<div style="max-width: 500px; margin: 50px auto;">
    <div class="card">
        <h2>Pendaftaran Akun</h2>
        <p>Buat akun baru untuk menggunakan aplikasi</p>
        
        <?php if (!empty($errors)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <strong>❌ Terjadi kesalahan:</strong>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap *</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" required value="<?php echo htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" minlength="3">
                <small style="color: #666;">Minimal 3 karakter, hanya huruf, angka, dan underscore</small>
            </div>
            
            <div class="form-group">
                <label for="no_telp">No. Telepon *</label>
                <input type="tel" id="no_telp" name="no_telp" required value="<?php echo htmlspecialchars($_POST['no_telp'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="role">Daftar Sebagai *</label>
                <select id="role" name="role" required>
                    <option value="mahasiswa" selected>Mahasiswa</option>
                    <option value="dosen">Dosen</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required minlength="6">
                <small style="color: #666;">Minimal 6 karakter</small>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Konfirmasi Password *</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
            </div>
            
            <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 10px;">Daftar</button>
        </form>
        
        <p style="text-align: center; margin-top: 15px;">
            Sudah punya akun? <a href="?page=login">Login di sini</a>
        </p>
    </div>
</div>
