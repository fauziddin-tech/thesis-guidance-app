-- Database untuk Aplikasi Bimbingan Skripsi

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'dosen', 'mahasiswa') NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    no_telp VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bimbingan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mahasiswa_id INT NOT NULL,
    dosen_id INT NOT NULL,
    judul_skripsi VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    status ENUM('aktif', 'selesai', 'ditangguhkan') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bab_skripsi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bimbingan_id INT NOT NULL,
    nama_bab VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    versi INT DEFAULT 1,
    status ENUM('draft', 'menunggu_review', 'direvisi', 'disetujui') DEFAULT 'draft',
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
