# MyThesis

MyThesis adalah aplikasi bimbingan skripsi berbasis PHP dan MySQL untuk mengelola dokumen, review dosen, revisi, dan progres mahasiswa dalam satu ruang kerja.

## Fitur

- Dashboard terpisah untuk mahasiswa dan dosen
- Dashboard administrator untuk membuat akun dosen dan relasi bimbingan
- Foto profil untuk mahasiswa, dosen, dan administrator tanpa migrasi database
- Unggah dokumen Word (DOC dan DOCX) dengan versioning otomatis; PDF tidak diterima
- Periode akademik (tahun ajaran + semester) dengan satu periode aktif, filter periode untuk dosen dan admin
- Master program studi; pendaftaran mahasiswa mencatat NIM, program studi, dan tahun angkatan
- Revisi dosen dituliskan sebagai komentar di dalam file Word, lalu diunggah kembali untuk mahasiswa
- Antrean review, catatan revisi, dan persetujuan dokumen
- Unduhan dokumen dengan pemeriksaan hak akses
- Notifikasi aktivitas pada basis data
- Tema terang/gelap dan tampilan responsif
- Perlindungan CSRF dan validasi unggahan
- Kredensial basis data disimpan di luar Git

## Persyaratan

- PHP 7.4 atau lebih baru
- MySQL 5.7 atau MariaDB yang setara
- Ekstensi PHP: `mysqli`, `fileinfo`, dan `mbstring`
- Apache dengan `mod_rewrite`

## Instalasi ringkas

1. Salin aplikasi ke server.
2. Import `schema.sql` melalui phpMyAdmin.
3. Salin `config/database.example.php` menjadi `config/database.local.php`.
4. Isi koneksi basis data pada file lokal tersebut.
5. Pastikan `storage/uploads` dapat ditulis oleh PHP, umumnya permission `750` atau `755`.
6. Buka aplikasi dan daftarkan akun mahasiswa. Akun dosen harus dibuat oleh administrator.

Jangan mengunggah `config/database.local.php`, file log, atau dokumen pengguna ke GitHub. Lihat `INSTALASI.md` untuk panduan cPanel dan `DEPLOYMENT.md` untuk pembaruan melalui terminal.

Konfigurasi lokal lama yang menggunakan konstanta `DB_HOST`, `DB_USER`, `DB_PASS`, dan `DB_NAME` tetap didukung untuk memudahkan upgrade.
