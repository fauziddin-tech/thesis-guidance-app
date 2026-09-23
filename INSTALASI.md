# Instalasi MyThesis di cPanel

## 1. Siapkan file dan basis data

1. Unggah atau clone repository ke folder domain.
2. Buat database dan pengguna MySQL melalui cPanel.
3. Berikan seluruh hak yang diperlukan kepada pengguna database.
4. Import `schema.sql` melalui phpMyAdmin.

### Migrasi untuk instalasi lama

Jika database sudah terpasang sebelumnya, jalankan file di folder `migrations/` yang belum pernah dijalankan, berurutan sesuai tanggal:

1. `20260921_add_revision_file.sql` — lampiran file revisi dosen.
2. `20260923_add_academic_periods.sql` — periode akademik, program studi, serta NIM/prodi/angkatan mahasiswa.
3. `20260924_add_guidance_card.sql` — tanggal persetujuan bab dan kode verifikasi kartu bimbingan.
4. `20260925_add_lecturer_homepage.sql` — pilihan menampilkan dosen di beranda.
5. `20260926_restore_features.sql` — Pembimbing 2, alur judul, lupa password, pembatasan login, dan log pratinjau (database mythesis.my.id sudah memilikinya).

Cadangkan database sebelum menjalankan migrasi. Setelah migrasi periode akademik, masuk sebagai admin lalu tambahkan program studi; pendaftaran mahasiswa baru ditutup sampai minimal satu program studi tersedia.

## 2. Buat konfigurasi lokal

Salin `config/database.example.php` menjadi `config/database.local.php`, kemudian isi data database, `APP_URL`, serta pengaturan email Resend (`MAIL_FROM`, `MAIL_FROM_NAME`, `RESEND_API_KEY`). Format array di bawah ini juga masih didukung untuk database:

```php
<?php
return [
    'host' => 'localhost',
    'user' => 'username_database',
    'pass' => 'password_database',
    'name' => 'nama_database',
];
```

File `database.local.php` telah diabaikan oleh Git dan tidak boleh dikirim ke repository.

## 3. Atur penyimpanan dokumen

Pastikan folder `storage/uploads` tersedia dan dapat ditulis oleh PHP. Mulai dengan permission `750`; gunakan `755` hanya jika konfigurasi hosting memerlukannya.

## 4. Uji aplikasi

1. Buka halaman beranda dan pendaftaran.
2. Daftarkan akun mahasiswa.
3. Buat akun dosen dan relasi bimbingan melalui administrator basis data.
4. Uji unggah, unduh, revisi, dan persetujuan menggunakan file contoh.

## Catatan keamanan

- Ganti password database jika pernah tersimpan pada repository publik.
- Jangan mengunggah file konfigurasi lokal, log, backup database, atau dokumen mahasiswa ke GitHub.
- Gunakan HTTPS pada domain produksi.
- Cadangkan basis data dan folder `storage/uploads` secara berkala.

### Backup database otomatis

`src/tools/backup-database.php` membuat salinan lengkap database (struktur dan data) tanpa `mysqldump`, dalam bentuk `.sql.gz`, ke folder `~/backup-mythesis` di luar `public_html`. Backup yang lebih lama dari 14 hari dihapus otomatis. Status terakhir tampil di halaman Pemeriksaan Sistem.

1. cPanel → **Cron Jobs** → *Add New Cron Job*, pilih *Once Per Day* (misalnya pukul 02:00).
2. Command: `php /home/USERNAME/public_html/mythesis/src/tools/backup-database.php`
3. Opsional di `config/database.local.php`: `define('BACKUP_DIR', '/home/USERNAME/backup-mythesis');` dan `define('BACKUP_KEEP_DAYS', 14);`

Memulihkan: unduh file `.sql.gz`, ekstrak, lalu impor lewat phpMyAdmin → Impor.

