<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">PLATFORM BIMBINGAN SKRIPSI</span>
        <h1>MyThesis</h1>
        <p>Ruang kerja akademik untuk mahasiswa dan dosen dalam mengelola bimbingan, naskah, revisi, dan progres skripsi secara terstruktur.</p>
        <div class="actions">
            <?php if(empty($_SESSION['user'])): ?>
                <a class="btn btn-primary" href="?page=register">Mulai sebagai Mahasiswa</a>
                <a class="btn btn-secondary" href="?page=login">Masuk</a>
            <?php else: ?>
                <a class="btn btn-primary" href="?page=dashboard">Buka Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section-heading">
    <span class="eyebrow">FUNGSI UTAMA</span>
    <h2>Bimbingan skripsi yang lebih teratur</h2>
    <p class="muted">Seluruh proses berfokus pada penyelesaian Bab 1 sampai Bab 5.</p>
</section>

<div class="grid-3">
    <article class="card feature">
        <div class="feature-number">01</div>
        <h2>Dokumen</h2>
        <p>Upload Bab 1–5 dengan validasi format, batas ukuran, dan pencatatan versi dokumen.</p>
    </article>
    <article class="card feature">
        <div class="feature-number">02</div>
        <h2>Revisi</h2>
        <p>Dosen memberikan catatan revisi pada versi terbaru sebelum mahasiswa mengunggah perbaikan.</p>
    </article>
    <article class="card feature">
        <div class="feature-number">03</div>
        <h2>Persetujuan</h2>
        <p>Bab diproses secara berurutan. Setelah Bab 5 mendapat ACC, proses bimbingan dinyatakan selesai.</p>
    </article>
</div>
