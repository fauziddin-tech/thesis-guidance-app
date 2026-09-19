# MyThesis

Aplikasi manajemen bimbingan skripsi berbasis PHP Native + MySQL.

## Fitur saat ini

- Login dengan username/email
- Registrasi mahasiswa
- Session regeneration dan logout POST + CSRF
- Role protection untuk mahasiswa/dosen/admin
- Pengajuan bimbingan mahasiswa
- Upload Bab 1–5 (PDF/DOC/DOCX, maksimal 10 MB)
- Versioning file bab
- Review/revisi dosen
- Dashboard dasar untuk mahasiswa, dosen, dan admin
- Admin dapat membuat akun dosen dari Dashboard Admin
- Admin dan dosen dapat membuka akun mahasiswa (login sebagai) untuk menelusuri kendala; akses tercatat di tabel impersonation_logs
- Notifikasi, konsultasi, dan jadwal seminar sudah disiapkan di skema database untuk tahap berikutnya

## Instalasi

1. Import `schema.sql` ke MySQL/MariaDB.
2. Konfigurasikan environment variable `DB_HOST`, `DB_USER`, `DB_PASS`, dan `DB_NAME` pada hosting.
3. Pastikan folder `uploads/bab` dapat ditulis oleh PHP.
4. Untuk lokal, salin `config/database.local.example.php` menjadi `config/database.local.php` dan isi kredensial lokal. File tersebut di-ignore oleh Git.

## Keamanan

Jangan menyimpan password database, token, log production, atau file `.env` ke repository. Jika kredensial pernah ter-commit, rotasi kredensial tersebut di hosting.
