<?php
class KonsultasiController {
    private mysqli $conn;
    public function __construct(mysqli $connection) { $this->conn=$connection; }
    public function getForUser(int $userId,string $role): array {
        $sql=$role==='mahasiswa'
            ? "SELECT k.*,b.judul_skripsi,d.nama_lengkap AS dosen_nama FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users d ON d.id=b.dosen_id WHERE b.mahasiswa_id=? ORDER BY k.created_at DESC"
            : "SELECT k.*,b.judul_skripsi,m.nama_lengkap AS mahasiswa_nama FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users m ON m.id=b.mahasiswa_id WHERE b.dosen_id=? ORDER BY k.created_at DESC";
        $s=$this->conn->prepare($sql);$s->bind_param('i',$userId);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $rows;
    }
    public function create(int $studentId,int $bimbinganId,string $topik,string $deskripsi): bool {
        if($topik==='')return false;$s=$this->conn->prepare("SELECT id FROM bimbingan WHERE id=? AND mahasiswa_id=? AND status='aktif'");$s->bind_param('ii',$bimbinganId,$studentId);$s->execute();$valid=(bool)$s->get_result()->num_rows;$s->close();if(!$valid)return false;
        $s=$this->conn->prepare('INSERT INTO konsultasi(bimbingan_id,topik,deskripsi) VALUES(?,?,?)');$s->bind_param('iss',$bimbinganId,$topik,$deskripsi);$ok=$s->execute();$s->close();return $ok;
    }
    public function answer(int $dosenId,int $id,string $jawaban,string $status): bool {
        if($jawaban===''||!in_array($status,['diterima','ditolak'],true))return false;$s=$this->conn->prepare("SELECT k.id FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id WHERE k.id=? AND b.dosen_id=? AND k.status='pending'");$s->bind_param('ii',$id,$dosenId);$s->execute();$valid=(bool)$s->get_result()->num_rows;$s->close();if(!$valid)return false;
        $s=$this->conn->prepare('UPDATE konsultasi SET jawaban=?,jawaban_oleh=?,status=?,answered_at=NOW() WHERE id=?');$s->bind_param('sisi',$jawaban,$dosenId,$status,$id);$ok=$s->execute();$s->close();return $ok;
    }
    public function getOwnerIds(int $id): ?array {$s=$this->conn->prepare('SELECT b.mahasiswa_id,b.dosen_id FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id WHERE k.id=?');$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return $r?:null;}
}
