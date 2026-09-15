# Panduan Instalasi di cPanel

## Langkah 1: Download File
1. Download semua file dari GitHub
2. Extract file ke folder lokal Anda

## Langkah 2: Upload ke cPanel

### Menggunakan File Manager cPanel:
1. Login ke cPanel
2. Buka **File Manager**
3. Navigasi ke folder **public_html**
4. Upload semua file (gunakan drag & drop atau upload satu per satu)

### Menggunakan FTP:
1. Gunakan FTP client (FileZilla, WinSCP, dll)
2. Koneksi ke server dengan kredensial FTP cPanel Anda
3. Upload semua file ke folder **public_html**

## Langkah 3: Buat Database

1. Login ke cPanel
2. Buka **MySQL Databases** atau **phpMyAdmin**
3. Buat database baru (contoh: `skripsi_db`)
4. Buat user baru dengan password (contoh: `user_skripsi`)
5. Berikan semua privileges kepada user untuk database tersebut

## Langkah 4: Konfigurasi Database

1. Edit file `config/database.php`
2. Ganti nilai berikut:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'user_skripsi');        // username database
   define('DB_PASS', 'your_password');       // password database
   define('DB_NAME', 'skripsi_db');          // nama database
   ```

3. Upload file yang sudah diedit kembali ke server

## Langkah 5: Import Database Schema

1. Login ke cPanel
2. Buka **phpMyAdmin**
3. Pilih database yang sudah dibuat
4. Klik tab **Import**
5. Upload file `schema.sql` dari folder aplikasi
6. Klik **Go** untuk menjalankan query

## Langkah 6: Buat Folder Uploads

1. Di File Manager cPanel, navigasi ke folder aplikasi
2. Masuk ke folder `public`
3. Buat folder baru bernama `uploads`
4. Atur permission folder menjadi `755`

## Langkah 7: Atur Permission File

1. Folder `public/uploads` → Permission `755`
2. File `config/database.php` → Permission `644`
3. File `.htaccess` → Permission `644`

## Langkah 8: Akses Aplikasi

1. Buka browser dan akses: `http://yourdomain.com`
2. Atau jika di subfolder: `http://yourdomain.com/thesis-guidance-app`

## Langkah 9: Register & Login

1. Klik tombol **Daftar**
2. Isi form pendaftaran
   - Pilih role: **Mahasiswa** atau **Dosen**
3. Klik **Daftar**
4. Login dengan akun yang baru dibuat

---

## Troubleshooting

### Error: "Koneksi Gagal"
- Pastikan username, password, dan nama database sudah benar di `config/database.php`
- Pastikan database sudah dibuat di cPanel
- Pastikan user database memiliki privileges yang cukup

### Error: "File tidak ditemukan"
- Pastikan semua file sudah terupload ke folder yang benar
- Cek apakah file `.htaccess` ada di root folder aplikasi

### Permission Denied
- Ubah permission folder `public/uploads` menjadi `755`
- Ubah permission file ke `644`

### Upload file tidak berfungsi
- Pastikan folder `public/uploads` ada dan permission `755`
- Cek setting max upload size di `php.ini` atau hubungi hosting support

---

## Kontak Support
Jika ada pertanyaan atau masalah, hubungi support hosting Anda atau buka issue di GitHub.
