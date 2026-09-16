<?php
if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit();
}

require_once 'src/controllers/BimbinganController.php';
require_once 'src/controllers/BabSkripsiController.php';
require_once 'src/helpers/Functions.php';

$bimbingan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$bimbingan_id) {
    header('Location: ?page=dashboard');
    exit();
}

$bimbingan_ctrl = new BimbinganController($conn);
$bab_ctrl = new BabSkripsiController($conn);

$bimbingan = $bimbingan_ctrl->getDetail($bimbingan_id);
if (!$bimbingan) {
    header('Location: ?page=dashboard');
    exit();
}

// Validasi akses
$user = $_SESSION['user'];
if ($user['role'] === 'mahasiswa' && $bimbingan['mahasiswa_id'] != $user['id']) {
    header('Location: ?page=dashboard');
    exit();
}
if ($user['role'] === 'dosen' && $bimbingan['dosen_id'] != $user['id']) {
    header('Location: ?page=dashboard');
    exit();
}

$bab_list = $bab_ctrl->getBabSkripsi($bimbingan_id);
$progress = $bimbingan_ctrl->getProgress($bimbingan_id);
?>

<div class="card">
    <h2>Detail Bimbingan Skripsi</h2>
    <a href="?page=dashboard" class="btn btn-secondary" style="float: right; font-size: 12px; padding: 5px 10px;">Kembali</a>
    <div style="clear: both;"></div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <!-- Main Content -->
    <div>
        <!-- Info Bimbingan -->
        <div class="card">
            <h3>Informasi Bimbingan</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; width: 30%; font-weight: bold;">Judul Skripsi</td>
                    <td style="padding: 8px;"><?php echo htmlspecialchars($bimbingan['judul_skripsi']); ?></td>
                </tr>
                <tr style="background-color: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Status</td>
                    <td style="padding: 8px;"><?php echo getStatusBadge($bimbingan['status']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: bold;">Mahasiswa</td>
                    <td style="padding: 8px;"><?php echo htmlspecialchars($bimbingan['mahasiswa_nama']); ?><br><small style="color: #666;"><?php echo htmlspecialchars($bimbingan['mahasiswa_email']); ?></small></td>
                </tr>
                <tr style="background-color: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Dosen Pembimbing</td>
                    <td style="padding: 8px;"><?php echo htmlspecialchars($bimbingan['dosen_nama']); ?><br><small style="color: #666;"><?php echo htmlspecialchars($bimbingan['dosen_email']); ?></small></td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: bold;">Tgl Dimulai</td>
                    <td style="padding: 8px;"><?php echo formatDate($bimbingan['created_at']); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Progress Bar -->
        <div class="card">
            <h3>Progress Bimbingan</h3>
            <div style="background-color: #f0f0f0; border-radius: 10px; overflow: hidden; height: 30px; margin: 15px 0;">
                <div style="background-color: #27ae60; height: 100%; width: <?php echo $progress; ?>%; transition: width 0.3s;"></div>
            </div>
            <p style="text-align: center; font-weight: bold;"><?php echo round($progress); ?>% Selesai</p>
            <p style="font-size: 14px; color: #666;"><?php echo count(array_filter($bab_list, function($b) { return $b['status'] === 'disetujui'; })); ?> dari <?php echo count($bab_list); ?> bab sudah disetujui</p>
        </div>
        
        <!-- Daftar Bab -->
        <div class="card">
            <h3>Daftar Bab Skripsi</h3>
            <?php if (count($bab_list) > 0): ?>
                <table>
                    <tr>
                        <th>Bab</th>
                        <th>Status</th>
                        <th>Revisi</th>
                        <th>Upload</th>
                        <th>Aksi</th>
                    </tr>
                    <?php foreach ($bab_list as $bab): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bab['nama_bab']); ?></td>
                        <td><?php echo getStatusBadge($bab['status']); ?></td>
                        <td><span style="background-color: #e74c3c; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php echo $bab['total_revisi']; ?></span></td>
                        <td><?php echo formatDate($bab['uploaded_at']); ?></td>
                        <td>
                            <a href="<?php echo $bab['file_path']; ?>" class="btn btn-primary" style="font-size: 11px; padding: 4px 8px;" target="_blank">Download</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">Belum ada bab yang di-upload</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div>
        <!-- Quick Actions -->
        <div class="card">
            <h3>Aksi Cepat</h3>
            <?php if ($user['role'] === 'mahasiswa'): ?>
                <a href="#" class="btn btn-success" style="width: 100%; text-align: center; display: block; margin-bottom: 10px;">Upload Bab Baru</a>
                <a href="#" class="btn btn-primary" style="width: 100%; text-align: center; display: block;">Konsultasi</a>
            <?php else: ?>
                <a href="#" class="btn btn-primary" style="width: 100%; text-align: center; display: block; margin-bottom: 10px;">Berikan Revisi</a>
                <a href="#" class="btn btn-info" style="width: 100%; text-align: center; display: block;">Jadwal Seminar</a>
            <?php endif; ?>
        </div>
        
        <!-- Timeline -->
        <div class="card">
            <h3>Timeline</h3>
            <div style="border-left: 3px solid #3498db; padding-left: 15px; font-size: 13px;">
                <p><strong>Dimulai</strong><br><?php echo formatDateTime($bimbingan['created_at']); ?></p>
                <p style="margin-top: 15px; color: #999;">Menunggu aktivitas berikutnya...</p>
            </div>
        </div>
    </div>
</div>

<style>
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}
.badge-success { background-color: #27ae60; }
.badge-primary { background-color: #3498db; }
.badge-warning { background-color: #f39c12; }
.badge-danger { background-color: #e74c3c; }
.badge-info { background-color: #3498db; }
.badge-secondary { background-color: #95a5a6; }
</style>
