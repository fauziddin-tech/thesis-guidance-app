# Aplikasi Bimbingan Skripsi

Aplikasi web untuk memudahkan proses bimbingan skripsi dengan fitur:
- Konsultasi online
- Upload bab skripsi
- Revisi dan feedback
- Penjadwalan seminar

## Teknologi
- Frontend: HTML, CSS, JavaScript
- Backend: PHP
- Database: MySQL

## Instalasi di cPanel

1. Download semua file
2. Extract di direktori public_html
3. Buat database MySQL melalui cPanel
4. Update konfigurasi di `config/database.php`
5. Jalankan `schema.sql` untuk membuat tabel
6. Akses melalui browser: http://yourdomain.com

## Struktur Folder
```
thesis-guidance-app/
├── config/
│   └── database.php
├── public/
│   ├── css/
│   ├── js/
│   └── uploads/
├── src/
│   ├── controllers/
│   ├── models/
│   └── views/
├── schema.sql
└── index.php
```
