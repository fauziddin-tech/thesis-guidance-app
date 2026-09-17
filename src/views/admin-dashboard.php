<?php
require_role(['admin']);
$totalUsers=(int)$conn->query('SELECT COUNT(*) total FROM users')->fetch_assoc()['total'];
$totalMhs=(int)$conn->query("SELECT COUNT(*) total FROM users WHERE role='mahasiswa'")->fetch_assoc()['total'];
$totalDosen=(int)$conn->query("SELECT COUNT(*) total FROM users WHERE role='dosen'")->fetch_assoc()['total'];
$totalBimbingan=(int)$conn->query('SELECT COUNT(*) total FROM bimbingan')->fetch_assoc()['total'];
$users=$conn->query('SELECT id,nama_lengkap,email,role,created_at FROM users ORDER BY created_at DESC LIMIT 20')->fetch_all(MYSQLI_ASSOC);
?>
<div class="card"><h2>Dashboard Admin</h2><p>Monitoring pengguna dan proses bimbingan MyThesis.</p></div>
<div class="stats-grid"><div class="stat-card"><strong><?=$totalUsers?></strong><span>Total User</span></div><div class="stat-card"><strong><?=$totalMhs?></strong><span>Mahasiswa</span></div><div class="stat-card"><strong><?=$totalDosen?></strong><span>Dosen</span></div><div class="stat-card"><strong><?=$totalBimbingan?></strong><span>Bimbingan</span></div></div>
<div class="card"><h3>Pengguna Terbaru</h3><table><tr><th>Nama</th><th>Email</th><th>Role</th><th>Daftar</th></tr><?php foreach($users as $row): ?><tr><td><?=e($row['nama_lengkap'])?></td><td><?=e($row['email'])?></td><td><?=e(ucfirst($row['role']))?></td><td><?=e(date('d-m-Y',strtotime($row['created_at'])))?></td></tr><?php endforeach; ?></table></div>
