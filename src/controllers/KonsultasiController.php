<?php

class KonsultasiController {
    private mysqli $conn;
    public function __construct(mysqli $connection) { $this->conn=$connection; }

    public function getForUser(int $userId, string $role): array {
        if($role==='mahasiswa') {
            $sql="SELECT k.*, b.judul_skripsi, d.nama_lengkap AS dosen_nama FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users d ON d.id=b.dosen_id WHERE b.mahasiswa_id=? ORDER BY k.created_at DESC";
        } else {
            $sql="SELECT k.*, b.judul_skripsi, m.nama_lengkap AS mahasiswa_nama FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users m ON m.id=b.mahasiswa_id WHERE b.dosen_id=? ORDER BY k.created_at DESC";
        }
        $stmt=$this->conn->prepare($sql);$stmt->bind_param('i',$userId);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
    }
}
