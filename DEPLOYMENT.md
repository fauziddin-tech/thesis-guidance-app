# Panduan Auto-Pull Deployment GitHub ke cPanel

Dokumentasi lengkap untuk setup automatic deployment dari GitHub ke cPanel.

## Metode 1: Manual Deploy dengan HTTP Request (Recommended untuk pemula)

### Langkah 1: Setup Git di cPanel

1. **Login ke cPanel**
2. **Buka Terminal (SSH)**
   - Atau gunakan cPanel > Terminal
3. **Navigate ke folder public_html**
   ```bash
   cd ~/public_html
   ```

4. **Clone repository GitHub**
   ```bash
   git clone https://github.com/fauziddin-tech/thesis-guidance-app.git .
   ```
   (Catatan: Titik di akhir untuk clone ke folder saat ini)

5. **Set permission folder uploads**
   ```bash
   chmod 755 public/uploads
   chmod 644 public/uploads/.htaccess 2>/dev/null || true
   ```

### Langkah 2: Konfigurasi Deploy Script

1. **Edit file `deploy.php`**
   - Ganti `your_secret_token_123` dengan token unik Anda
   - Contoh: `$secret_token = 'abc123xyz789def456mno789pqr'`

2. **Upload script ke public_html**
   - File sudah tersedia di repository

3. **Test deploy script**
   ```
   http://mythesis.my.id/deploy.php?token=abc123xyz789def456mno789pqr
   ```
   Response:
   ```json
   {
       "status": "success",
       "message": "Deployment berhasil!",
       "timestamp": "2024-09-15 10:30:45",
       "output": "Already up to date."
   }
   ```

### Langkah 3: Lihat Log Deployment

1. **Via File Manager cPanel**
   - Buka `deploy.log` di root folder aplikasi
   - Lihat history setiap deployment

2. **Via SSH Terminal**
   ```bash
   tail -f ~/public_html/deploy.log
   ```

---

## Metode 2: GitHub Webhook (Automatic Deployment)

### Kelebihan:
- Deployment otomatis setiap ada push ke GitHub
- Tidak perlu manual trigger
- Real-time update aplikasi

### Langkah 1: Setup Git SSH Key (Optional tapi Recommended)

Jika menggunakan private repository:

1. **Generate SSH key di cPanel**
   ```bash
   cd ~/.ssh
   ssh-keygen -t rsa -b 4096 -f id_rsa -N ""
   cat id_rsa.pub
   ```

2. **Add SSH key ke GitHub**
   - GitHub Settings > SSH and GPG keys > New SSH key
   - Paste public key
   - Save

3. **Clone menggunakan SSH**
   ```bash
   git clone git@github.com:fauziddin-tech/thesis-guidance-app.git .
   ```

### Langkah 2: Setup GitHub Webhook

1. **Buka repository GitHub**
   - Ke: https://github.com/fauziddin-tech/thesis-guidance-app

2. **Settings > Webhooks > Add webhook**
   
3. **Isi form webhook:**
   - **Payload URL**: `http://mythesis.my.id/deploy-webhook.php`
   - **Content type**: `application/json`
   - **Secret**: (opsional, untuk keamanan) Ganti `your_github_webhook_secret` di `deploy-webhook.php`
   - **Events**: Pilih "Just the push event"
   - **Active**: Centang

4. **Klik Add webhook**

### Langkah 3: Konfigurasi deploy-webhook.php

1. **Edit file `deploy-webhook.php`**
   ```php
   $github_secret = 'your_github_webhook_secret'; // Ganti dengan secret dari GitHub (atau kosongkan)
   ```

2. **Upload ke public_html**

### Langkah 4: Test Webhook

1. **Di GitHub, buka Webhooks**
2. **Klik webhook yang baru dibuat**
3. **Lihat tab "Recent Deliveries"**
4. **Click pada delivery terbaru untuk melihat response**

---

## Workflow Lengkap:

### Saat Anda Push ke GitHub:
1. Push code ke GitHub
   ```bash
   git add .
   git commit -m "Update fitur"
   git push origin main
   ```

2. GitHub mengirim webhook ke `deploy-webhook.php`

3. Script melakukan `git pull` di server

4. Aplikasi di cPanel otomatis terupdate

5. Lihat log di file `webhook.log`

---

## Troubleshooting

### Error: "Permission denied (publickey)"
**Solusi:**
- Setup SSH key dengan benar
- Test koneksi: `ssh -T git@github.com`

### Error: "fatal: not a git repository"
**Solusi:**
- Pastikan folder sudah di-clone dengan `git clone`
- Check dengan: `ls -la .git`

### Webhook tidak bekerja
**Debugging:**
1. Cek file `webhook.log`
2. Lihat "Recent Deliveries" di GitHub
3. Pastikan URL webhook benar dan accessible
4. Test manual: `curl http://mythesis.my.id/deploy-webhook.php`

### Permission error di folder uploads
**Solusi:**
```bash
chmod 755 public/uploads
chmod 644 public/uploads/*
```

---

## Security Best Practices

1. **Gunakan token yang kuat**
   ```bash
   openssl rand -hex 32  # Generate token yang aman
   ```

2. **Jangan share token/secret**

3. **Gunakan HTTPS** untuk webhook
   - GitHub otomatis menggunakan HTTPS

4. **Batasi akses deploy.php**
   - Tambah di `.htaccess` jika ingin extra protection

5. **Monitor log files**
   - Cek `deploy.log` dan `webhook.log` secara regular

---

## Perintah Git Berguna

```bash
# Cek status repository
git status

# Lihat log commit
git log --oneline -10

# Cek remote URL
git remote -v

# Manual pull (jika perlu)
git pull origin main

# Reset ke commit tertentu (jika ada error)
git reset --hard HEAD~1
```

---

## Support

Jika ada masalah:
1. Cek log files: `deploy.log` dan `webhook.log`
2. Hubungi support cPanel hosting Anda
3. Buka issue di GitHub repository
