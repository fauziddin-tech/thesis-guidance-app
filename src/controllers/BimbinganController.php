<?php

class BimbinganController {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get detail bimbingan berdasarkan ID
     */
    public function getDetail($bimbingan_id) {
        $query = "SELECT b.*, 
                         m.nama_lengkap as mahasiswa_nama, m.email as mahasiswa_email, m.no_telp as mahasiswa_telp,
                         d.nama_lengkap as dosen_nama, d.email as dosen_email, d.no_telp as dosen_telp
                  FROM bimbingan b
                  JOIN users m ON b.mahasiswa_id = m.id
                  JOIN users d ON b.dosen_id = d.id
                  WHERE b.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $bimbingan_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get semua bab skripsi untuk bimbingan tertentu
     */
    public function getBabSkripsi($bimbingan_id) {
        $query = "SELECT bs.*, COUNT(r.id) as total_revisi
                  FROM bab_skripsi bs
                  LEFT JOIN revisi r ON bs.id = r.bab_id
                  WHERE bs.bimbingan_id = ?
                  GROUP BY bs.id
                  ORDER BY bs.uploaded_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $bimbingan_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get progress bimbingan dalam persen
     */
    public function getProgress($bimbingan_id) {
        // Count bab yang sudah disetujui
        $query = "SELECT COUNT(*) as disetujui FROM bab_skripsi WHERE bimbingan_id = ? AND status = 'disetujui'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $bimbingan_id);
        $stmt->execute();
        $approved = $stmt->get_result()->fetch_assoc()['disetujui'];
        
        // Total bab expected (default 5 bab)
        $total_bab = 5;
        $progress = ($approved / $total_bab) * 100;
        
        return min($progress, 100);
    }
    
    /**
     * Create bimbingan baru
     */
    public function create($mahasiswa_id, $dosen_id, $judul, $deskripsi) {
        $query = "INSERT INTO bimbingan (mahasiswa_id, dosen_id, judul_skripsi, deskripsi, status) 
                  VALUES (?, ?, ?, ?, 'aktif')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iiss', $mahasiswa_id, $dosen_id, $judul, $deskripsi);
        
        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->insert_id];
        }
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Update status bimbingan
     */
    public function updateStatus($bimbingan_id, $status) {
        $allowed_status = ['aktif', 'selesai', 'ditangguhkan'];
        
        if (!in_array($status, $allowed_status)) {
            return ['success' => false, 'error' => 'Status tidak valid'];
        }
        
        $query = "UPDATE bimbingan SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $status, $bimbingan_id);
        
        return ['success' => $stmt->execute()];
    }
}
?>
