<?php

class Email {
    private string $from = 'noreply@mythesis.my.id';
    public function sendVerification(string $to,string $nama,string $verificationLink): bool {
        $subject='Verifikasi Email - MyThesis';
        $nama=htmlspecialchars($nama,ENT_QUOTES,'UTF-8'); $link=htmlspecialchars($verificationLink,ENT_QUOTES,'UTF-8');
        $body="<html><body style='font-family:Arial,sans-serif'><h2>Verifikasi Email</h2><p>Halo {$nama},</p><p>Silakan verifikasi email Anda:</p><p><a href='{$link}'>Verifikasi Email</a></p><p>Link berlaku selama 24 jam.</p></body></html>";
        return $this->send($to,$subject,$body);
    }
    public function sendPasswordReset(string $to,string $nama,string $resetLink): bool {
        $subject='Reset Password - MyThesis';
        $nama=htmlspecialchars($nama,ENT_QUOTES,'UTF-8'); $link=htmlspecialchars($resetLink,ENT_QUOTES,'UTF-8');
        $body="<html><body style='font-family:Arial,sans-serif'><h2>Reset Password</h2><p>Halo {$nama},</p><p>Gunakan tautan berikut untuk membuat password baru:</p><p><a href='{$link}'>Reset Password</a></p><p>Link berlaku selama 1 jam.</p></body></html>";
        return $this->send($to,$subject,$body);
    }
    public function sendRevisionNotif(string $to,string $nama,string $namaBab,string $link): bool {
        $subject='Revisi Bab Skripsi - MyThesis';
        $nama=htmlspecialchars($nama,ENT_QUOTES,'UTF-8'); $bab=htmlspecialchars($namaBab,ENT_QUOTES,'UTF-8'); $link=htmlspecialchars($link,ENT_QUOTES,'UTF-8');
        $body="<html><body style='font-family:Arial,sans-serif'><h2>Notifikasi Revisi</h2><p>Halo {$nama}, dosen pembimbing memberikan revisi untuk <strong>{$bab}</strong>.</p><p><a href='{$link}'>Lihat Revisi</a></p></body></html>";
        return $this->send($to,$subject,$body);
    }
    private function send(string $to,string $subject,string $body): bool {
        $headers="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: {$this->from}\r\n";
        return mail($to,$subject,$body,$headers);
    }
}
