-- Gelar depan, gelar belakang, dan nama dasar dosen (urutan dan pencarian memakai nama dasar).
-- Jalankan SATU KALI melalui phpMyAdmin. Cadangkan database terlebih dahulu.
-- Nilai awal diisi otomatis oleh aplikasi dari nama lengkap yang sudah ada.
ALTER TABLE users
    ADD COLUMN gelar_depan VARCHAR(40) NULL AFTER nama_lengkap,
    ADD COLUMN gelar_belakang VARCHAR(80) NULL AFTER gelar_depan,
    ADD COLUMN nama_dasar VARCHAR(150) NULL AFTER gelar_belakang;
