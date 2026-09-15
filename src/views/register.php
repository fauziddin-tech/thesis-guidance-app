<div class="card" style="max-width: 600px; margin: 30px auto;">
    <h2>Daftar Akun</h2>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $nama_lengkap = $_POST['nama_lengkap'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'mahasiswa';
        $no_telp = $_POST['no_telp'] ?? '';
        
        // Validasi
        if (empty($username) || empty($email) || empty($nama_lengkap) || empty($password)) {
            echo '<div class="alert alert-danger">Semua field harus diisi!</div>';
        } elseif ($password !== $confirm_password) {
            echo '<div class="alert alert-danger">Password tidak cocok!</div>';
        } elseif (strlen($password) < 6) {
            echo '<div class="alert alert-danger">Password minimal 6 karakter!</div>';
        } else {
            // Cek apakah username sudah terdaftar
            $query = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo '<div class="alert alert-danger">Username atau email sudah terdaftar!</div>';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user baru
                $query = "INSERT INTO users (username, email, password, role, nama_lengkap, no_telp) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssssss', $username, $email, $hashed_password, $role, $nama_lengkap, $no_telp);
                
                if ($stmt->execute()) {
                    echo '<div class="alert alert-success">Pendaftaran berhasil! <a href="?page=login">Login di sini</a></div>';
                } else {
                    echo '<div class="alert alert-danger">Gagal mendaftar! Error: ' . $stmt->error . '</div>';
                }
            }
            $stmt->close();
        }
    }
    ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="nama_lengkap">Nama Lengkap</label>
            <input type="text" id="nama_lengkap" name="nama_lengkap" required>
        </div>
        
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="no_telp">No. Telepon</label>
            <input type="text" id="no_telp" name="no_telp">
        </div>
        
        <div class="form-group">
            <label for="role">Pilih Role</label>
            <select id="role" name="role" required>
                <option value="mahasiswa">Mahasiswa</option>
                <option value="dosen">Dosen</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Konfirmasi Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" class="btn btn-success" style="width: 100%;">Daftar</button>
    </form>
    
    <p style="margin-top: 20px; text-align: center;">
        Sudah punya akun? <a href="?page=login">Login di sini</a>
    </p>
</div>
