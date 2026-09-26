-- Penerimaan pendaftaran mahasiswa oleh dosen pembimbing.
-- Pendaftaran mandiri baru bernilai 1 (menunggu diterima Pembimbing 1). Semua bimbingan yang sudah
-- ada otomatis bernilai 0 (dianggap sudah diterima), jadi tidak ada data lama yang terganggu.
-- Aman dijalankan ulang. Cadangkan database terlebih dahulu.

ALTER TABLE bimbingan
    ADD COLUMN IF NOT EXISTS menunggu_persetujuan TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
