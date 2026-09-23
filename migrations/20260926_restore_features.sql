-- Tabel untuk Pembimbing 2, review berurutan, alur judul, lupa password,
-- pembatasan percobaan login, dan log pratinjau akun mahasiswa.
-- Database mythesis.my.id SUDAH memiliki semua tabel ini; file ini untuk instalasi baru
-- atau server lain. Aman dijalankan ulang (IF NOT EXISTS). Cadangkan database terlebih dahulu.

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

-- Status bimbingan untuk alur pengajuan dan revisi judul.
ALTER TABLE bimbingan
    MODIFY COLUMN status ENUM('pengajuan_judul','revisi_judul','aktif','selesai','ditangguhkan') DEFAULT 'pengajuan_judul';
