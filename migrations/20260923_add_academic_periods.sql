-- Periode akademik, program studi, dan data akademik mahasiswa.
-- Jalankan SATU KALI melalui phpMyAdmin pada database MyThesis yang sudah terpasang.
-- Cadangkan (export) database terlebih dahulu sebelum menjalankan file ini.

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

ALTER TABLE users
    ADD COLUMN nim VARCHAR(30) NULL AFTER nama_lengkap,
    ADD COLUMN prodi_id INT NULL AFTER nim,
    ADD COLUMN angkatan SMALLINT NULL AFTER prodi_id,
    ADD UNIQUE KEY uq_users_nim (nim),
    ADD CONSTRAINT fk_users_prodi FOREIGN KEY (prodi_id) REFERENCES program_studi(id) ON DELETE SET NULL;

ALTER TABLE bimbingan
    ADD COLUMN periode_id INT NULL AFTER dosen_id,
    ADD KEY idx_bimbingan_periode (periode_id),
    ADD CONSTRAINT fk_bimbingan_periode FOREIGN KEY (periode_id) REFERENCES periode_akademik(id) ON DELETE SET NULL;

-- Periode awal. Ubah tahun ajaran/semester di bawah jika perlu sebelum dijalankan.
INSERT INTO periode_akademik (tahun_ajaran, semester, is_aktif)
VALUES ('2026/2027', 'ganjil', 1)
ON DUPLICATE KEY UPDATE is_aktif = 1;

-- Bimbingan lama dimasukkan ke periode aktif; admin dapat memindahkannya dari dashboard.
UPDATE bimbingan
SET periode_id = (SELECT id FROM periode_akademik WHERE is_aktif = 1 ORDER BY id DESC LIMIT 1)
WHERE periode_id IS NULL;

-- Contoh menambah program studi (bisa juga melalui dashboard admin):
-- INSERT INTO program_studi (kode, nama, jenjang) VALUES ('PGPAUD', 'Pendidikan Guru PAUD', 'S1');
