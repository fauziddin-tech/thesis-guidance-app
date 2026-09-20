# Pembaruan melalui Terminal cPanel

Gunakan terminal atau SSH; aplikasi tidak lagi menyediakan endpoint deployment publik.

```bash
cd ~/public_html
git status
git pull --ff-only origin main
```

Jika aplikasi berada di subfolder, ganti `~/public_html` dengan lokasi aplikasi. Jangan menjalankan `git reset --hard` pada server karena dapat menghapus konfigurasi atau dokumen lokal.

Setelah pembaruan:

```bash
test -f config/database.local.php && echo "Konfigurasi tersedia"
test -w storage/uploads && echo "Folder unggahan dapat ditulis"
```

Jika menggunakan cache PHP/OPcache, reset dari panel hosting atau tunggu cache berakhir. Simpan `config/database.local.php` dan seluruh isi `storage/uploads` sebagai data server, bukan bagian dari repository.
