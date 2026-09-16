<?php

class BabSkripsiController {
    private $conn;
    private $upload_dir = 'public/uploads/';
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Upload bab skripsi dengan validation
     */
    public function upload($bimbingan_id, $nama_bab, $file) {
        // Validasi file
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Generate nama file unik
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'bab_' . time() . '_' . uniqid() . '.' . $ext;
        $filepath = $this->upload_dir . $filename;
        
        // Buat folder jika belum ada
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
        
        // Upload file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Gagal mengupload file'];
        }
        
        // Simpan ke database
        $query = "INSERT INTO bab_skripsi (bimbingan_id, nama_bab, file_path, status, versi) 
                  VALUES (?, ?, ?, 'menunggu_review', 1)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iss', $bimbingan_id, $nama_bab, $filepath);
        
        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->insert_id, 'file' => $filename];
        }
        
        // Hapus file jika gagal insert
        unlink($filepath);
        return ['success' => false, 'error' => 'Gagal menyimpan ke database'];
    }
    
    /**
     * Validasi file upload
     */
    private function validateFile($file) {
        $allowed_ext = ['pdf', 'doc', 'docx'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        // Cek file ada
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'error' => 'File tidak ada'];
        }
        
        // Cek ukuran
        if ($file['size'] > $max_size) {
            return ['success' => false, 'error' => 'Ukuran file terlalu besar (max 10MB)'];
        }
        
        // Cek ekstensioni
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            return ['success' => false, 'error' => 'Tipe file tidak diizinkan. Gunakan PDF, DOC, atau DOCX'];
        }
        
        // Validasi MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        $allowed_mime = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (!in_array($mime, $allowed_mime)) {
            return ['success' => false, 'error' => 'Format file tidak valid'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Get revisi untuk bab tertentu
     */
    public function getRevisi($bab_id) {
        $query = "SELECT r.*, u.nama_lengkap as dosen_nama
                  FROM revisi r
                  JOIN users u ON r.dosen_id = u.id
                  WHERE r.bab_id = ?
                  ORDER BY r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $bab_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Update status bab
     */
    public function updateStatus($bab_id, $status) {
        $allowed_status = ['draft', 'menunggu_review', 'direvisi', 'disetujui'];
        
        if (!in_array($status, $allowed_status)) {
            return ['success' => false, 'error' => 'Status tidak valid'];
        }
        
        $query = "UPDATE bab_skripsi SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $status, $bab_id);
        
        return ['success' => $stmt->execute()];
    }
}
?>
