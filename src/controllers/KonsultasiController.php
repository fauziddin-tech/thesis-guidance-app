<?php
class KonsultasiController {
    private mysqli $conn;
    public function __construct(mysqli $connection) { $this->conn=$connection; }
    public function getForUser(int $userId,string $role,string $search='',int $limit=10,int $offset=0): array {
        $limit=max(1,min(50,$limit));$offset=max(0,$offset);$like='%'.$search.'%';
        $sql=$role==='mahasiswa'
            ? "SELECT k.*,b.judul_skripsi,d.nama_lengkap AS dosen_nama FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users d ON d.id=b.dosen_id WHERE b.mahasiswa_id=? AND (k.topik LIKE ? OR k.deskripsi LIKE ? OR d.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ?) ORDER BY k.created_at DESC,k.id DESC LIMIT ? OFFSET ?"
            : "SELECT k.*,b.judul_skripsi,m.nama_lengkap AS mahasiswa_nama FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users m ON m.id=b.mahasiswa_id WHERE b.dosen_id=? AND (k.topik LIKE ? OR k.deskripsi LIKE ? OR m.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ?) ORDER BY k.created_at DESC,k.id DESC LIMIT ? OFFSET ?";
        $s=$this->conn->prepare($sql);$s->bind_param('issssii',$userId,$like,$like,$like,$like,$limit,$offset);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $rows;
    }
    public function countForUser(int $userId,string $role,string $search=''): int {
        $like='%'.$search.'%';
        $sql=$role==='mahasiswa'
            ? "SELECT COUNT(*) total FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users d ON d.id=b.dosen_id WHERE b.mahasiswa_id=? AND (k.topik LIKE ? OR k.deskripsi LIKE ? OR d.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ?)"
            : "SELECT COUNT(*) total FROM konsultasi k JOIN bimbingan b ON b.id=k.bimbingan_id JOIN users m ON m.id=b.mahasiswa_id WHERE b.dosen_id=? AND (k.topik LIKE ? OR k.deskripsi LIKE ? OR m.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ?)";
        $s=$this->conn->prepare($sql);$s->bind_param('issss',$userId,$like,$like,$like,$like);$s->execute();$total=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();return $total;
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
