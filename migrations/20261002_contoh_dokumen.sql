-- Contoh dokumen (PDF) yang diunggah dosen/admin sebagai pedoman tata tulis dan struktur
-- (mis. contoh proposal lengkap, contoh skripsi). Mahasiswa melihatnya di dashboard dengan pratinjau.
-- Aman dijalankan ulang. Cadangkan database terlebih dahulu.

CREATE TABLE IF NOT EXISTS contoh_dokumen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pengunggah_id INT NOT NULL,
    judul VARCHAR(200) NOT NULL,
    kategori VARCHAR(30) NOT NULL DEFAULT 'proposal',
    keterangan VARCHAR(500) NULL,
    visibilitas VARCHAR(20) NOT NULL DEFAULT 'semua',
    file_path VARCHAR(255) NOT NULL,
    ukuran INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contoh_pengunggah (pengunggah_id),
    KEY idx_contoh_kategori (kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
