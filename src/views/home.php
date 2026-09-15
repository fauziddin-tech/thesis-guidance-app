<div class="card">
    <h2>Selamat Datang di Aplikasi Bimbingan Skripsi</h2>
    <p>Aplikasi ini dirancang untuk memudahkan proses bimbingan skripsi dengan fitur-fitur lengkap yang komprehensif.</p>
    
    <div style="margin-top: 30px;">
        <h3>Fitur Utama</h3>
        <ul style="margin-left: 20px; margin-top: 15px;">
            <li><strong>Upload Bab Skripsi</strong> - Unggah file bab skripsi dengan mudah untuk diproses</li>
            <li><strong>Konsultasi Online</strong> - Konsultasikan masalah skripsi dengan dosen pembimbing secara langsung</li>
            <li><strong>Sistem Revisi</strong> - Dapatkan feedback dan revisi terperinci dari dosen pembimbing</li>
            <li><strong>Penjadwalan Seminar</strong> - Atur jadwal seminar proposal, hasil, dan sidang dengan mudah</li>
            <li><strong>Dashboard Monitoring</strong> - Monitor progres skripsi secara real-time</li>
            <li><strong>Sistem Notifikasi</strong> - Dapatkan notifikasi untuk setiap update penting</li>
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
    <h2>Panduan Penggunaan</h2>
    <p>Berikut adalah langkah-langkah menggunakan aplikasi ini:</p>
    
    <ol style="margin-left: 20px; margin-top: 15px;">
        <li><strong>Daftar Akun</strong> - Buat akun baru sebagai mahasiswa atau dosen</li>
        <li><strong>Login</strong> - Masuk dengan username dan password Anda</li>
        <li><strong>Buat Bimbingan</strong> - Mahasiswa membuat data bimbingan skripsi baru</li>
        <li><strong>Upload Bab</strong> - Unggah file bab skripsi untuk di-review oleh dosen</li>
        <li><strong>Terima Feedback</strong> - Dosen memberikan revisi dan komentar terperinci</li>
        <li><strong>Jadwalkan Seminar</strong> - Atur jadwal seminar dengan dosen dan penguji</li>
        <li><strong>Selesaikan Bimbingan</strong> - Tandai bimbingan sebagai selesai setelah semua tahap terpenuhi</li>
    </ol>
</div>
