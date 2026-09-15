<div class="card" style="max-width: 500px; margin: 50px auto;">
    <h2>Login</h2>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Query user dari database
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role' => $user['role'],
                    'email' => $user['email']
                ];
                
                echo '<div class="alert alert-success">Login berhasil! Mengalihkan...</div>';
                header('Location: ?page=dashboard');
                exit();
            } else {
                echo '<div class="alert alert-danger">Password salah!</div>';
            }
        } else {
            echo '<div class="alert alert-danger">Username atau email tidak ditemukan!</div>';
        }
        
        $stmt->close();
    }
    ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Username atau Email</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
    </form>
    
    <p style="margin-top: 20px; text-align: center;">
        Belum punya akun? <a href="?page=register">Daftar di sini</a>
    </p>
</div>
