-- Log pertemuan bimbingan: dicatat mahasiswa, dikonfirmasi dosen yang ditemui,
-- dihitung ke target minimal pertemuan, dan dicetak di kartu bimbingan.
-- Aman dijalankan ulang (IF NOT EXISTS). Cadangkan database terlebih dahulu.

CREATE TABLE IF NOT EXISTS log_pertemuan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bimbingan_id INT NOT NULL,
    dosen_id INT NOT NULL,
    tanggal DATE NOT NULL,
    metode VARCHAR(20) NOT NULL DEFAULT 'tatap_muka',
    topik VARCHAR(255) NOT NULL,
    catatan TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'menunggu',
    alasan_tolak VARCHAR(500) NULL,
    dikonfirmasi_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_pertemuan_bimbingan (bimbingan_id, status),
    KEY idx_log_pertemuan_dosen (dosen_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
