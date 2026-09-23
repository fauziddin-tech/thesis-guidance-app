<?php
// Salin menjadi config/database.local.php lalu isi data asli. File lokal tidak boleh dikirim ke GitHub.
define('DB_HOST', 'localhost');
define('DB_USER', 'nama_user_database');
define('DB_PASS', 'password_database');
define('DB_NAME', 'nama_database');

// Alamat aplikasi, dipakai untuk tautan di email dan QR kartu bimbingan.
define('APP_URL', 'https://mythesis.my.id');

// Email melalui Resend (https://resend.com). Domain MAIL_FROM harus sudah terverifikasi di Resend.
define('MAIL_FROM', 'noreply@mythesis.my.id');
define('MAIL_FROM_NAME', 'MyThesis');
define('RESEND_API_KEY', 're_xxxxxxxxxxxxxxxxxxxx');
