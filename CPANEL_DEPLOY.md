# Deploy MyThesis ke cPanel

## 1. Source code
Gunakan branch `main` dari repository `spasr1890-hub/mythesis`. Jangan upload folder `.git` bila memakai File Manager.

## 2. Konfigurasi database production
Buat file `config/database.local.php` di server (file ini sengaja tidak ada di GitHub):

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'USERNAME_DATABASE_CPANEL');
define('DB_PASS', 'PASSWORD_DATABASE_BARU');
define('DB_NAME', 'NAMA_DATABASE_CPANEL');
```

Jangan menaruh password production di GitHub.

## 3. Database
Import `schema.sql` melalui phpMyAdmin. Jika database sudah berisi tabel MyThesis, gunakan backup terlebih dahulu dan jalankan perubahan schema yang diperlukan secara bertahap.

## 4. Folder upload
Pastikan folder `uploads/bab` dapat ditulis PHP. Folder `uploads` sudah memiliki aturan agar file PHP tidak dieksekusi.

## 5. PHP
Gunakan PHP 8.1 atau lebih baru dan aktifkan extension `mysqli` serta `fileinfo`.

## 6. Uji setelah deploy
- Beranda
- Register mahasiswa
- Login/logout
- Pengajuan bimbingan
- Upload Bab 1 (PDF/DOC/DOCX, maksimum 10 MB)
- Login dosen dan review/revisi
- Konsultasi
- Login admin dan penjadwalan seminar/sidang
- Notifikasi

## 7. Keamanan
Password database yang pernah terekspos harus sudah dirotasi. Jangan commit `database.local.php`, file log, atau file upload.
