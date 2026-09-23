-- Pilihan menampilkan dosen (foto dan nama) di beranda.
-- Jalankan SATU KALI melalui phpMyAdmin. Cadangkan database terlebih dahulu.
ALTER TABLE users
    ADD COLUMN tampil_beranda TINYINT(1) NOT NULL DEFAULT 0 AFTER angkatan;
