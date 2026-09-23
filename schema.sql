-- Database untuk Aplikasi Bimbingan Skripsi

CREATE TABLE IF NOT EXISTS program_studi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode VARCHAR(20) NULL,
    nama VARCHAR(150) NOT NULL,
    jenjang ENUM('D3', 'D4', 'S1', 'S2', 'S3') NOT NULL DEFAULT 'S1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_program_studi (nama, jenjang)
);

CREATE TABLE IF NOT EXISTS periode_akademik (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tahun_ajaran CHAR(9) NOT NULL,
    semester ENUM('ganjil', 'genap') NOT NULL,
    is_aktif TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_periode_akademik (tahun_ajaran, semester)
);

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'dosen', 'mahasiswa') NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    nim VARCHAR(30) NULL,
    prodi_id INT NULL,
    angkatan SMALLINT NULL,
    tampil_beranda TINYINT(1) NOT NULL DEFAULT 0,
    no_telp VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_nim (nim),
    FOREIGN KEY (prodi_id) REFERENCES program_studi(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bimbingan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mahasiswa_id INT NOT NULL,
    dosen_id INT NOT NULL,
    periode_id INT NULL,
    judul_skripsi VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    status ENUM('pengajuan_judul', 'revisi_judul', 'aktif', 'selesai', 'ditangguhkan') DEFAULT 'pengajuan_judul',
    kode_verifikasi VARCHAR(16) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (periode_id) REFERENCES periode_akademik(id) ON DELETE SET NULL,
    UNIQUE KEY uq_bimbingan_kode_verifikasi (kode_verifikasi)
);

CREATE TABLE IF NOT EXISTS bab_skripsi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bimbingan_id INT NOT NULL,
    nama_bab VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    versi INT DEFAULT 1,
    status ENUM('draft', 'menunggu_review', 'direvisi', 'disetujui') DEFAULT 'draft',
    disetujui_at TIMESTAMP NULL DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bimbingan_id) REFERENCES bimbingan(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS revisi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bab_id INT NOT NULL,
    dosen_id INT NOT NULL,
    komentar TEXT NOT NULL,
    tipe_revisi ENUM('minor', 'major', 'kritis') DEFAULT 'minor',
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bab_id) REFERENCES bab_skripsi(id) ON DELETE CASCADE,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS konsultasi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bimbingan_id INT NOT NULL,
    topik VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    file_lampiran VARCHAR(255),
    status ENUM('pending', 'diterima', 'ditolak') DEFAULT 'pending',
    jawaban TEXT,
    jawaban_oleh INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    answered_at TIMESTAMP NULL,
    FOREIGN KEY (bimbingan_id) REFERENCES bimbingan(id) ON DELETE CASCADE,
    FOREIGN KEY (jawaban_oleh) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS jadwal_seminar (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bimbingan_id INT NOT NULL,
    tipe_seminar ENUM('proposal', 'hasil', 'sidang') NOT NULL,
    tanggal_seminar DATETIME NOT NULL,
    lokasi VARCHAR(150),
    status ENUM('terjadwal', 'berlangsung', 'selesai', 'dibatalkan') DEFAULT 'terjadwal',
    penguji_1 INT,
    penguji_2 INT,
    penguji_3 INT,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bimbingan_id) REFERENCES bimbingan(id) ON DELETE CASCADE,
    FOREIGN KEY (penguji_1) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (penguji_2) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (penguji_3) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifikasi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tipe VARCHAR(50) NOT NULL,
    pesan TEXT NOT NULL,
    link VARCHAR(255),
    dibaca INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pembimbing 2, review berurutan, lupa password, pembatasan login, log pratinjau akun
CREATE TABLE IF NOT EXISTS mythesis_pembimbing2 (
    bimbingan_id BIGINT NOT NULL PRIMARY KEY,
    dosen_id BIGINT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY dosen_id (dosen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mythesis_review_dosen (
    jenis VARCHAR(10) NOT NULL,
    objek_id BIGINT NOT NULL,
    dosen_id BIGINT NOT NULL,
    keputusan VARCHAR(20) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (jenis, objek_id, dosen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_reset_token (token_hash),
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(32) NOT NULL,
    rkey VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lookup (bucket, rkey, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS impersonation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    impersonator_id INT NOT NULL,
    impersonator_nama VARCHAR(150) NOT NULL,
    impersonator_role VARCHAR(20) NOT NULL,
    target_id INT NOT NULL,
    target_nama VARCHAR(150) NOT NULL,
    mode VARCHAR(10) NOT NULL DEFAULT 'view',
    ip_address VARCHAR(45) NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    KEY idx_impersonation_target (target_id),
    KEY idx_impersonation_actor (impersonator_id),
    KEY idx_impersonation_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
