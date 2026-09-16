<?php

class Email {
    private $from = 'noreply@mythesis.my.id';
    
    /**
     * Send email verification
     */
    public function sendVerification($to, $nama, $verification_link) {
        $subject = 'Verifikasi Email - Aplikasi Bimbingan Skripsi';
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Verifikasi Email</h2>
            <p>Halo $nama,</p>
            <p>Terima kasih telah mendaftar di Aplikasi Bimbingan Skripsi.</p>
            <p>Silakan verifikasi email Anda dengan klik link di bawah:</p>
            <p><a href='$verification_link' style='background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Verifikasi Email</a></p>
            <p>Link berlaku selama 24 jam.</p>
            <hr>
            <p style='color: #999; font-size: 12px;'>Jika Anda tidak membuat akun ini, abaikan email ini.</p>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($to, $nama, $reset_link) {
        $subject = 'Reset Password - Aplikasi Bimbingan Skripsi';
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Reset Password</h2>
            <p>Halo $nama,</p>
            <p>Kami menerima permintaan untuk mereset password Anda.</p>
            <p>Silakan klik link di bawah untuk membuat password baru:</p>
            <p><a href='$reset_link' style='background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Reset Password</a></p>
            <p>Link berlaku selama 1 jam.</p>
            <hr>
            <p style='color: #999; font-size: 12px;'>Jika Anda tidak meminta reset password, abaikan email ini.</p>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send notifikasi revisi
     */
    public function sendRevisionNotif($to, $nama, $nama_bab, $link) {
        $subject = 'Revisi Bab Skripsi Diterima';
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Notifikasi Revisi</h2>
            <p>Halo $nama,</p>
            <p>Dosen pembimbing Anda telah memberikan revisi untuk <strong>$nama_bab</strong>.</p>
            <p>Silakan login untuk melihat detail revisi:</p>
            <p><a href='$link' style='background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Lihat Revisi</a></p>
            <hr>
            <p style='color: #999; font-size: 12px;'>Email ini otomatis dikirim. Tidak perlu di-reply.</p>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Fungsi helper untuk send email
     */
    private function send($to, $subject, $body) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: " . $this->from . "\r\n";
        
        // Di production, gunakan PHPMailer atau library sejenis
        return mail($to, $subject, $body, $headers);
    }
}
?>
