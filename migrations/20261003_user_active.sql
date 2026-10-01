-- Status aktif akun: dosen yang tidak lagi membimbing dinonaktifkan (bukan dihapus) agar riwayat bimbingan tetap utuh.
-- Jalankan SATU KALI melalui phpMyAdmin. Cadangkan database terlebih dahulu.
ALTER TABLE users
    ADD COLUMN is_aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
