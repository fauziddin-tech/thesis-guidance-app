<?php
if (isset($_SESSION['user'])) {
    header('Location: ?page=dashboard');
    exit();
}

require_once 'src/helpers/Functions.php';

$message = flash('message');
$message_type = flash('message_type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        // Cek user
        $query = "SELECT id, username, password, role, nama_lengkap FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Login sukses
                $_SESSION['user'] = $user;
                header('Location: ?page=dashboard');
                exit();
            } else {
                $error = 'Password salah';
            }
        } else {
            $error = 'Username tidak ditemukan';
        }
    }
}
?>

<div style="max-width: 500px; margin: 50px auto;">
    <div class="card">
        <h2>Login</h2>
        
        <?php if ($message): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                ✅ <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
        
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
        
        <p style="text-align: center; margin-bottom: 10px;">
            Belum punya akun? <a href="?page=register">Daftar di sini</a>
        </p>
        
        <p style="text-align: center; font-size: 12px; color: #666;">
            <a href="?page=forgot-password">Lupa password?</a>
        </p>
    </div>
    
    <!-- Test Credentials Info -->
    <div class="card" style="margin-top: 20px; background-color: #e7f3ff; border-left: 4px solid #3498db;">
        <h4 style="margin-top: 0; color: #3498db;">🔐 Test Credentials</h4>
        <p style="font-size: 13px; margin: 5px 0;">
            <strong>Admin:</strong> admin / password123
        </p>
        <p style="font-size: 13px; margin: 5px 0;">
            <strong>Dosen:</strong> dosen1 / password123
        </p>
        <p style="font-size: 13px; margin: 5px 0;">
            <strong>Mahasiswa:</strong> mahasiswa1 / password123
        </p>
    </div>
</div>
