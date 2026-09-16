<?php

/**
 * Database Seeder - Insert test data
 * Run: php database/seeder.php
 */

require_once dirname(__DIR__) . '/config/database.php';

echo "🌱 Starting Database Seeder...\n\n";

// Hash password: password123
$default_password = password_hash('password123', PASSWORD_DEFAULT);

// 1. Insert Admin
$query = "INSERT INTO users (username, email, password, role, nama_lengkap, no_telp) 
          VALUES ('admin', 'admin@mythesis.my.id', ?, 'admin', 'Administrator', '081234567890')";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $default_password);
if ($stmt->execute()) {
    echo "✅ Admin created: username=admin, password=password123\n";
} else {
    echo "❌ Failed to create admin\n";
}

// 2. Insert Dosen
$query = "INSERT INTO users (username, email, password, role, nama_lengkap, no_telp) 
          VALUES ('dosen1', 'dosen1@mythesis.my.id', ?, 'dosen', 'Dr. Budi Santoso, M.Kom', '082345678901')";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $default_password);
if ($stmt->execute()) {
    echo "✅ Dosen 1 created: username=dosen1, password=password123\n";
    $dosen1_id = $conn->insert_id;
} else {
    echo "❌ Failed to create dosen1\n";
}

$query = "INSERT INTO users (username, email, password, role, nama_lengkap, no_telp) 
          VALUES ('dosen2', 'dosen2@mythesis.my.id', ?, 'dosen', 'Prof. Siti Rahayu, Ph.D', '082456789012')";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $default_password);
if ($stmt->execute()) {
    echo "✅ Dosen 2 created: username=dosen2, password=password123\n";
    $dosen2_id = $conn->insert_id;
} else {
    echo "❌ Failed to create dosen2\n";
}

// 3. Insert Mahasiswa
$query = "INSERT INTO users (username, email, password, role, nama_lengkap, no_telp) 
          VALUES ('mahasiswa1', 'mahasiswa1@mythesis.my.id', ?, 'mahasiswa', 'Ahmad Rasyid', '083456789012')";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $default_password);
if ($stmt->execute()) {
    echo "✅ Mahasiswa 1 created: username=mahasiswa1, password=password123\n";
    $mahasiswa1_id = $conn->insert_id;
} else {
    echo "❌ Failed to create mahasiswa1\n";
}

$query = "INSERT INTO users (username, email, password, role, nama_lengkap, no_telp) 
          VALUES ('mahasiswa2', 'mahasiswa2@mythesis.my.id', ?, 'mahasiswa', 'Siti Nur Azizah', '083567890123')";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $default_password);
if ($stmt->execute()) {
    echo "✅ Mahasiswa 2 created: username=mahasiswa2, password=password123\n";
    $mahasiswa2_id = $conn->insert_id;
} else {
    echo "❌ Failed to create mahasiswa2\n";
}

// 4. Insert Bimbingan
$dosen1_id = $conn->query("SELECT id FROM users WHERE username='dosen1'")[0] ?? 2;
$mahasiswa1_id = $conn->query("SELECT id FROM users WHERE username='mahasiswa1'")[0] ?? 4;

$query = "INSERT INTO bimbingan (mahasiswa_id, dosen_id, judul_skripsi, deskripsi, status) 
          VALUES (?, ?, ?, ?, 'aktif')";
$judul = 'Sistem Informasi Manajemen Bimbingan Skripsi Berbasis Web';
$deskripsi = 'Penelitian tentang pengembangan sistem informasi untuk mengelola proses bimbingan skripsi secara digital';
$mahasiswa_id = 4;
$dosen_id = 2;
$stmt = $conn->prepare($query);
$stmt->bind_param('iiss', $mahasiswa_id, $dosen_id, $judul, $deskripsi);
if ($stmt->execute()) {
    echo "✅ Bimbingan 1 created\n";
    $bimbingan1_id = $conn->insert_id;
} else {
    echo "❌ Failed to create bimbingan\n";
}

// 5. Insert Bab Skripsi
$bab_data = [
    ['Bab I - Pendahuluan', 'bab_1_pendahuluan.pdf', 'disetujui'],
    ['Bab II - Tinjauan Pustaka', 'bab_2_tinjauan.pdf', 'disetujui'],
    ['Bab III - Metodologi', 'bab_3_metodologi.pdf', 'direvisi'],
    ['Bab IV - Hasil dan Pembahasan', 'bab_4_hasil.pdf', 'menunggu_review'],
    ['Bab V - Kesimpulan', 'bab_5_kesimpulan.pdf', 'draft'],
];

foreach ($bab_data as $bab) {
    $query = "INSERT INTO bab_skripsi (bimbingan_id, nama_bab, file_path, status) 
              VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isss', $bimbingan1_id, $bab[0], $bab[1], $bab[2]);
    if ($stmt->execute()) {
        echo "✅ {$bab[0]} uploaded\n";
    }
}

// 6. Insert Revisi
$query = "INSERT INTO revisi (bab_id, dosen_id, komentar, tipe_revisi) 
          VALUES (?, ?, ?, ?)";
$dosen_id = 2;
$bab_id = $conn->query("SELECT id FROM bab_skripsi WHERE nama_bab='Bab II - Tinjauan Pustaka'")[0] ?? 2;
$komentar = 'Tambahkan lebih banyak referensi terbaru. Minimal 20 referensi dari jurnal internasional.';
$tipe = 'minor';
$stmt = $conn->prepare($query);
$stmt->bind_param('iiss', $bab_id, $dosen_id, $komentar, $tipe);
if ($stmt->execute()) {
    echo "✅ Revisi added\n";
}

echo "\n✨ Database seeding completed!\n";
echo "\n📋 Test Credentials:\n";
echo "├─ Admin: admin / password123\n";
echo "├─ Dosen: dosen1 / password123\n";
echo "└─ Mahasiswa: mahasiswa1 / password123\n";

$conn->close();
?>
