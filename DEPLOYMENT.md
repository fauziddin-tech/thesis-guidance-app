# Pembaruan melalui Terminal cPanel

Gunakan terminal atau SSH; aplikasi tidak menyediakan endpoint deployment publik.
Folder aplikasi di server: `~/public_html/mythesis`, mengikuti branch `main`.

## Pembaruan rutin

```bash
cd ~/public_html/mythesis
git pull --ff-only origin main
```

Setelah pembaruan, buka menu **Pemeriksaan** sebagai admin. Jika ada migrasi berstatus
"Belum dijalankan", jalankan file tersebut dari folder `migrations/` melalui phpMyAdmin.

Jangan menjalankan `git reset --hard` atau `git clean` tanpa cadangan. Data server yang tidak
disimpan di Git: `config/database.local.php`, `storage/uploads`, `storage/avatars`, `uploads/`
(dokumen aplikasi lama), dan `logo.png`.

## Baris handler PHP cPanel di .htaccess

cPanel (MultiPHP) menambahkan blok `# php -- BEGIN cPanel-generated handler` di akhir
`.htaccess` server. Blok ini khusus server, sehingga tidak disimpan di repository dan
`.htaccess` server ditandai `skip-worktree`. Jika suatu saat `git pull` menolak karena
`.htaccess` berubah di GitHub:

```bash
cd ~/public_html/mythesis
sed -n '/# php -- BEGIN cPanel-generated handler/,/# php -- END cPanel-generated handler/p' .htaccess > ~/mythesis-php-handler.txt
git update-index --no-skip-worktree .htaccess
git checkout -- .htaccess
git pull --ff-only origin main
printf '\n' >> .htaccess && cat ~/mythesis-php-handler.txt >> .htaccess
git update-index --skip-worktree .htaccess
```

## Pemeriksaan cepat

```bash
test -f config/database.local.php && echo "Konfigurasi tersedia"
test -w storage/uploads && echo "Folder unggahan dapat ditulis"
git status --short
```

Jika menggunakan cache PHP/OPcache, reset dari panel hosting atau tunggu cache berakhir.
