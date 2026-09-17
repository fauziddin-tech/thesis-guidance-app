<?php

class BimbinganController {
    private mysqli $conn;

    public function __construct(mysqli $connection) { $this->conn = $connection; }

    public function getDetail(int $bimbinganId): ?array {
        $sql = "SELECT b.*, m.nama_lengkap AS mahasiswa_nama, m.email AS mahasiswa_email, m.no_telp AS mahasiswa_telp,
                       d.nama_lengkap AS dosen_nama, d.email AS dosen_email, d.no_telp AS dosen_telp
                FROM bimbingan b
                JOIN users m ON m.id = b.mahasiswa_id
                JOIN users d ON d.id = b.dosen_id
                WHERE b.id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql); $stmt->bind_param('i', $bimbinganId); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $row ?: null;
    }

    public function getBabSkripsi(int $bimbinganId): array {
        $sql = "SELECT bs.*, COUNT(r.id) AS total_revisi
                FROM bab_skripsi bs LEFT JOIN revisi r ON r.bab_id = bs.id
                WHERE bs.bimbingan_id = ? AND bs.nama_bab IN ('Bab 1','Bab 2','Bab 3','Bab 4','Bab 5')
                GROUP BY bs.id ORDER BY bs.nama_bab ASC, bs.versi DESC, bs.id DESC";
        $stmt = $this->conn->prepare($sql); $stmt->bind_param('i', $bimbinganId); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }

    public function getProgress(int $bimbinganId): float {
        $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT nama_bab) AS total FROM bab_skripsi WHERE bimbingan_id = ? AND status = 'disetujui' AND nama_bab IN ('Bab 1','Bab 2','Bab 3','Bab 4','Bab 5')");
        $stmt->bind_param('i', $bimbinganId); $stmt->execute();
        $approved = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0); $stmt->close();
        return min(100, ($approved / 5) * 100);
    }

    public function create(int $mahasiswaId, int $dosenId, string $judul, string $deskripsi): array {
        $stmt = $this->conn->prepare("INSERT INTO bimbingan (mahasiswa_id, dosen_id, judul_skripsi, deskripsi, status) VALUES (?, ?, ?, ?, 'aktif')");
        $stmt->bind_param('iiss', $mahasiswaId, $dosenId, $judul, $deskripsi);
        $ok = $stmt->execute(); $id = $this->conn->insert_id; $error = $stmt->error; $stmt->close();
        return $ok ? ['success'=>true,'id'=>$id] : ['success'=>false,'error'=>$error];
    }

    public function updateStatus(int $bimbinganId, string $status): array {
        if (!in_array($status, ['aktif','selesai','ditangguhkan'], true)) return ['success'=>false,'error'=>'Status tidak valid.'];
        if ($status === 'selesai') {
            $stmt = $this->conn->prepare("SELECT id FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab='Bab 5' AND status='disetujui' ORDER BY versi DESC,id DESC LIMIT 1");
            $stmt->bind_param('i', $bimbinganId); $stmt->execute();
            $approved = $stmt->get_result()->num_rows > 0; $stmt->close();
            if (!$approved) return ['success'=>false,'error'=>'Bimbingan hanya dapat selesai setelah Bab 5 mendapat ACC.'];
        }
        $stmt = $this->conn->prepare('UPDATE bimbingan SET status = ? WHERE id = ?'); $stmt->bind_param('si',$status,$bimbinganId);
        $ok=$stmt->execute(); $error=$stmt->error; $stmt->close(); return $ok ? ['success'=>true] : ['success'=>false,'error'=>$error];
    }
}
