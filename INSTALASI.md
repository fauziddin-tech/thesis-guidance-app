# Instalasi MyThesis di cPanel

## 1. Siapkan file dan basis data

1. Unggah atau clone repository ke folder domain.
2. Buat database dan pengguna MySQL melalui cPanel.
3. Berikan seluruh hak yang diperlukan kepada pengguna database.
4. Import `schema.sql` melalui phpMyAdmin.

## 2. Buat konfigurasi lokal

Salin `config/database.example.php` menjadi `config/database.local.php`, kemudian isi data cPanel:

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
