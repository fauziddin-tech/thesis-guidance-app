<div class="card">
    <h2>Selamat Datang di Aplikasi Bimbingan Skripsi</h2>
    <p>Aplikasi ini dirancang untuk memudahkan proses bimbingan skripsi dengan fitur-fitur lengkap.</p>
    
    <div style="margin-top: 30px;">
        <h3>✨ Fitur Utama:</h3>
        <ul style="margin-left: 20px; margin-top: 15px;">
            <li><strong>📤 Upload Bab Skripsi</strong> - Unggah file bab skripsi dengan mudah</li>
            <li><strong>💬 Konsultasi Online</strong> - Konsultasikan masalah skripsi dengan dosen pembimbing</li>
            <li><strong>✏️ Sistem Revisi</strong> - Dapatkan feedback dan revisi dari dosen</li>
            <li><strong>📅 Penjadwalan Seminar</strong> - Atur jadwal seminar proposal, hasil, dan sidang</li>
            <li><strong>📊 Dashboard</strong> - Monitor progres skripsi secara real-time</li>
            <li><strong>🔔 Notifikasi</strong> - Dapatkan notifikasi untuk setiap update</li>
        </ul>
    </div>
    
    <div style="margin-top: 30px; text-align: center;">
        <?php if (!isset($_SESSION['user'])): ?>
            <a href="?page=login" class="btn btn-primary">Login</a>
            <a href="?page=register" class="btn btn-success" style="margin-left: 10px;">Daftar</a>
        <?php else: ?>
            <a href="?page=dashboard" class="btn btn-primary">Ke Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>📋 Panduan Penggunaan</h2>
    <p>Berikut adalah langkah-langkah menggunakan aplikasi ini:</p>
    
    <ol style="margin-left: 20px; margin-top: 15px;">
        <li><strong>Daftar Akun</strong> - Buat akun sebagai mahasiswa atau dosen</li>
        <li><strong>Login</strong> - Masuk dengan username dan password Anda</li>
        <li><strong>Buat Bimbingan</strong> - Mahasiswa membuat data bimbingan baru</li>
        <li><strong>Upload Bab</strong> - Unggah file bab skripsi untuk di-review</li>
        <li><strong>Terima Feedback</strong> - Dosen memberikan revisi dan komentar</li>
        <li><strong>Jadwalkan Seminar</strong> - Atur jadwal seminar dengan penguji</li>
        <li><strong>Selesaikan</strong> - Tandai bimbingan sebagai selesai</li>
    </ol>
</div>
