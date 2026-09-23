-- Kartu bimbingan: tanggal persetujuan bab dan kode verifikasi kartu.
-- Jalankan SATU KALI melalui phpMyAdmin. Cadangkan database terlebih dahulu.

ALTER TABLE bab_skripsi
    ADD COLUMN disetujui_at TIMESTAMP NULL DEFAULT NULL AFTER status;

ALTER TABLE bimbingan
    ADD COLUMN kode_verifikasi VARCHAR(16) NULL AFTER status,
    ADD UNIQUE KEY uq_bimbingan_kode_verifikasi (kode_verifikasi);
