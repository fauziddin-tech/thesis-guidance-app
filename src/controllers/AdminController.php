<?php

class AdminController {
    private mysqli $conn;

    public function __construct(mysqli $connection) { $this->conn = $connection; }

    public function getUsers(): array {
        $result = $this->conn->query("SELECT id, username, email, role, nama_lengkap, no_telp, created_at FROM users ORDER BY role, nama_lengkap");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getDosen(): array {
        $result = $this->conn->query("SELECT id, nama_lengkap, email FROM users WHERE role='dosen' ORDER BY nama_lengkap");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getBimbingan(): array {
        $sql = "SELECT b.*, m.nama_lengkap AS mahasiswa_nama, d.nama_lengkap AS dosen_nama,
                       (SELECT COUNT(*) FROM bab_skripsi bs WHERE bs.bimbingan_id=b.id AND bs.status='disetujui') AS bab_disetujui
                FROM bimbingan b
                JOIN users m ON m.id=b.mahasiswa_id
                JOIN users d ON d.id=b.dosen_id
                ORDER BY b.updated_at DESC";
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function assignDosen(int $bimbinganId, int $dosenId): bool {
        $check=$this->conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen' LIMIT 1");
        $check->bind_param('i',$dosenId); $check->execute(); $valid=$check->get_result()->num_rows===1; $check->close();
        if(!$valid) return false;
        $stmt=$this->conn->prepare('UPDATE bimbingan SET dosen_id=? WHERE id=?');
        $stmt->bind_param('ii',$dosenId,$bimbinganId); $ok=$stmt->execute(); $stmt->close(); return $ok;
    }

    public function updateBimbinganStatus(int $id, string $status): bool {
        if (!in_array($status, ['aktif','selesai','ditangguhkan'], true)) return false;
        $stmt=$this->conn->prepare('UPDATE bimbingan SET status=? WHERE id=?');
        $stmt->bind_param('si',$status,$id); $ok=$stmt->execute(); $stmt->close(); return $ok;
    }
}
