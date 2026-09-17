CREATE TABLE IF NOT EXISTS users (
 id INT PRIMARY KEY AUTO_INCREMENT,
 username VARCHAR(100) UNIQUE NOT NULL,
 email VARCHAR(100) UNIQUE NOT NULL,
 password VARCHAR(255) NOT NULL,
 role ENUM('admin','dosen','mahasiswa') NOT NULL DEFAULT 'mahasiswa',
 nama_lengkap VARCHAR(150) NOT NULL,
 no_telp VARCHAR(20),
 dosen_pembimbing_id INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_users_dosen_pembimbing (dosen_pembimbing_id),
 FOREIGN KEY (dosen_pembimbing_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bimbingan (
 id INT PRIMARY KEY AUTO_INCREMENT,
 mahasiswa_id INT NOT NULL,
 dosen_id INT NOT NULL,
 judul_skripsi VARCHAR(255) NOT NULL,
 deskripsi TEXT,
 status ENUM('aktif','selesai','ditangguhkan') DEFAULT 'aktif',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_bimbingan_mahasiswa (mahasiswa_id), INDEX idx_bimbingan_dosen (dosen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bab_skripsi (
 id INT PRIMARY KEY AUTO_INCREMENT,
 bimbingan_id INT NOT NULL,
 nama_bab VARCHAR(100) NOT NULL,
 file_path VARCHAR(255) NOT NULL,
 versi INT NOT NULL DEFAULT 1,
 status ENUM('draft','menunggu_review','direvisi','disetujui') DEFAULT 'draft',
 uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (bimbingan_id) REFERENCES bimbingan(id) ON DELETE CASCADE,
 INDEX idx_bab_bimbingan (bimbingan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revisi (
 id INT PRIMARY KEY AUTO_INCREMENT,
 bab_id INT NOT NULL,
 dosen_id INT NOT NULL,
 komentar TEXT NOT NULL,
 tipe_revisi ENUM('minor','major','kritis') DEFAULT 'minor',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (bab_id) REFERENCES bab_skripsi(id) ON DELETE CASCADE,
 FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS konsultasi (
 id INT PRIMARY KEY AUTO_INCREMENT,
 bimbingan_id INT NOT NULL,
 topik VARCHAR(255) NOT NULL,
 deskripsi TEXT,
 file_lampiran VARCHAR(255),
 status ENUM('pending','diterima','ditolak') DEFAULT 'pending',
 jawaban TEXT,
 jawaban_oleh INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 answered_at TIMESTAMP NULL,
 FOREIGN KEY (bimbingan_id) REFERENCES bimbingan(id) ON DELETE CASCADE,
 FOREIGN KEY (jawaban_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifikasi (
 id INT PRIMARY KEY AUTO_INCREMENT,
 user_id INT NOT NULL,
 tipe VARCHAR(50) NOT NULL,
 pesan TEXT NOT NULL,
 link VARCHAR(255),
 dibaca TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notifikasi_user (user_id, dibaca)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
