<?php
if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit();
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$role = $user['role'];
?>

<div class="card">
    <h2>Dashboard - Selamat datang, <?php echo htmlspecialchars($user['nama_lengkap']); ?></h2>
    <p>Role: <strong><?php echo ucfirst($role); ?></strong></p>
</div>

<?php if ($role === 'mahasiswa'): ?>
    <!-- Dashboard Mahasiswa -->
    <div class="card">
        <h3>📚 Bimbingan Saya</h3>
        
        <?php
        $query = "SELECT b.*, u.nama_lengkap as dosen_nama 
                  FROM bimbingan b 
                  JOIN users u ON b.dosen_id = u.id 
                  WHERE b.mahasiswa_id = ?
                  ORDER BY b.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo '<table>';
            echo '<tr><th>Judul Skripsi</th><th>Dosen Pembimbing</th><th>Status</th><th>Aksi</th></tr>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['judul_skripsi']) . '</td>';
                echo '<td>' . htmlspecialchars($row['dosen_nama']) . '</td>';
                echo '<td><span style="padding: 5px 10px; border-radius: 3px; background-color: ' . 
                     ($row['status'] === 'aktif' ? '#27ae60' : '#e74c3c') . '; color: white;">' . 
                     ucfirst($row['status']) . '</span></td>';
                echo '<td>';
                echo '<a href="#" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">Detail</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p>Anda belum memiliki bimbingan. <a href="#">Buat bimbingan baru</a></p>';
        }
        $stmt->close();
        ?>
    </div>
    
    <div class="card">
        <h3>📤 Unggah Bab Skripsi</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="bimbingan_id">Pilih Bimbingan</label>
                <select id="bimbingan_id" name="bimbingan_id" required>
                    <option value="">-- Pilih --</option>
                    <?php
                    $query = "SELECT id, judul_skripsi FROM bimbingan WHERE mahasiswa_id = ? AND status = 'aktif'";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['judul_skripsi']) . '</option>';
                    }
                    $stmt->close();
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="nama_bab">Nama Bab</label>
                <input type="text" id="nama_bab" name="nama_bab" placeholder="cth: Bab I - Pendahuluan" required>
            </div>
            
            <div class="form-group">
                <label for="file_bab">Upload File (PDF/DOC/DOCX)</label>
                <input type="file" id="file_bab" name="file_bab" accept=".pdf,.doc,.docx" required>
            </div>
            
            <button type="submit" class="btn btn-success">Unggah Bab</button>
        </form>
    </div>

<?php elseif ($role === 'dosen'): ?>
    <!-- Dashboard Dosen -->
    <div class="card">
        <h3>👥 Mahasiswa Bimbingan Saya</h3>
        
        <?php
        $query = "SELECT b.*, u.nama_lengkap, u.email 
                  FROM bimbingan b 
                  JOIN users u ON b.mahasiswa_id = u.id 
                  WHERE b.dosen_id = ?
                  ORDER BY b.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo '<table>';
            echo '<tr><th>Nama Mahasiswa</th><th>Judul Skripsi</th><th>Status</th><th>Aksi</th></tr>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['nama_lengkap']) . '</td>';
                echo '<td>' . htmlspecialchars($row['judul_skripsi']) . '</td>';
                echo '<td><span style="padding: 5px 10px; border-radius: 3px; background-color: ' . 
                     ($row['status'] === 'aktif' ? '#27ae60' : '#e74c3c') . '; color: white;">' . 
                     ucfirst($row['status']) . '</span></td>';
                echo '<td><a href="#" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">Review</a></td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p>Anda belum memiliki mahasiswa bimbingan.</p>';
        }
        $stmt->close();
        ?>
    </div>
    
    <div class="card">
        <h3>✏️ Berikan Revisi</h3>
        <form method="POST">
            <div class="form-group">
                <label for="bab_id">Pilih Bab</label>
                <select id="bab_id" name="bab_id" required>
                    <option value="">-- Pilih --</option>
                    <?php
                    $query = "SELECT bs.id, bs.nama_bab, u.nama_lengkap 
                              FROM bab_skripsi bs 
                              JOIN bimbingan b ON bs.bimbingan_id = b.id 
                              JOIN users u ON b.mahasiswa_id = u.id 
                              WHERE b.dosen_id = ?
                              ORDER BY bs.uploaded_at DESC";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['nama_bab'] . ' - ' . $row['nama_lengkap']) . '</option>';
                    }
                    $stmt->close();
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="komentar">Komentar Revisi</label>
                <textarea id="komentar" name="komentar" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="tipe_revisi">Tipe Revisi</label>
                <select id="tipe_revisi" name="tipe_revisi" required>
                    <option value="minor">Minor (perbaikan kecil)</option>
                    <option value="major">Major (perubahan signifikan)</option>
                    <option value="kritis">Kritis (perubahan mendesak)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-success">Kirim Revisi</button>
        </form>
    </div>

<?php endif; ?>

<div class="card">
    <form method="GET" style="text-align: center;">
        <input type="hidden" name="page" value="logout">
        <button type="submit" class="btn btn-danger">Logout</button>
    </form>
</div>

<?php
if (isset($_GET['page']) && $_GET['page'] === 'logout') {
    session_destroy();
    header('Location: ?page=home');
    exit();
}
?>
