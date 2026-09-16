<?php
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ?page=login');
    exit();
}

require_once 'src/controllers/UserController.php';
require_once 'src/helpers/Functions.php';

$user_ctrl = new UserController($conn);
?>

<div class="card">
    <h2>Admin Dashboard</h2>
    <p>Kelola aplikasi bimbingan skripsi</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px;">
    <?php
    $total_users = $user_ctrl->getTotalUsers();
    $mahasiswa = count($user_ctrl->getUsersByRole('mahasiswa'));
    $dosen = count($user_ctrl->getUsersByRole('dosen'));
    $total_bimbingan = $conn->query("SELECT COUNT(*) as total FROM bimbingan")->fetch_assoc()['total'];
    ?>
    
    <div class="card" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <h3 style="margin-top: 0; color: white;">Total User</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo $total_users; ?></p>
        <small>Pengguna terdaftar</small>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #27ae60, #229954); color: white;">
        <h3 style="margin-top: 0; color: white;">Mahasiswa</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo $mahasiswa; ?></p>
        <small>Total mahasiswa</small>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #f39c12, #d68910); color: white;">
        <h3 style="margin-top: 0; color: white;">Dosen</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo $dosen; ?></p>
        <small>Total dosen</small>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white;">
        <h3 style="margin-top: 0; color: white;">Bimbingan</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo $total_bimbingan; ?></p>
        <small>Total bimbingan aktif</small>
    </div>
</div>

<!-- Manage Users -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <h3>Daftar User</h3>
        <p style="margin-bottom: 15px;">
            <a href="?page=admin-users-add" class="btn btn-success">Tambah User</a>
        </p>
        
        <?php
        $users = $user_ctrl->getAllUsers(10);
        ?>
        
        <table>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Aksi</th>
            </tr>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['nama_lengkap']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo ucfirst($u['role']); ?></td>
                <td>
                    <a href="?page=admin-users-edit&id=<?php echo $u['id']; ?>" class="btn btn-primary" style="font-size: 11px; padding: 4px 8px;">Edit</a>
                    <a href="?page=admin-users-delete&id=<?php echo $u['id']; ?>" class="btn btn-danger" style="font-size: 11px; padding: 4px 8px;" onclick="return confirm('Yakin ingin hapus user ini?');">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="card">
        <h3>Statistik</h3>
        <p style="font-size: 14px; line-height: 2;">
            <strong>User aktif bulan ini:</strong> <span style="float: right; font-weight: bold; color: #3498db;">12</span><br>
            <strong>Bimbingan selesai:</strong> <span style="float: right; font-weight: bold; color: #27ae60;">8</span><br>
            <strong>Bimbingan aktif:</strong> <span style="float: right; font-weight: bold; color: #f39c12;">5</span><br>
            <strong>Bab yang di-upload:</strong> <span style="float: right; font-weight: bold; color: #e74c3c;">34</span><br>
        </p>
    </div>
</div>
