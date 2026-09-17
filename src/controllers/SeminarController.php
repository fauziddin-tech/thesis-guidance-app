<?php
class SeminarController {
    public function __construct(private mysqli $conn) {}
    public function listForUser(int $userId,string $role): array {
        $sql=$role==='admin' ? "SELECT j.*,b.judul_skripsi,m.nama_lengkap mahasiswa_nama,d.nama_lengkap dosen_nama FROM jadwal_seminar j JOIN bimbingan b ON b.id=j.bimbingan_id JOIN users m ON m.id=b.mahasiswa_id JOIN users d ON d.id=b.dosen_id ORDER BY j.tanggal_seminar DESC" : "SELECT j.*,b.judul_skripsi,m.nama_lengkap mahasiswa_nama,d.nama_lengkap dosen_nama FROM jadwal_seminar j JOIN bimbingan b ON b.id=j.bimbingan_id JOIN users m ON m.id=b.mahasiswa_id JOIN users d ON d.id=b.dosen_id WHERE b.mahasiswa_id=? OR b.dosen_id=? ORDER BY j.tanggal_seminar DESC";
        $s=$this->conn->prepare($sql);if($role==='admin')$s->execute();else{$s->bind_param('ii',$userId,$userId);$s->execute();}$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $rows;
    }
    public function create(int $bimbinganId,string $tipe,string $tanggal,string $lokasi,?int $p1,?int $p2,?int $p3,string $catatan): bool {
        if(!in_array($tipe,['proposal','hasil','sidang'],true)||$tanggal==='')return false;$s=$this->conn->prepare('INSERT INTO jadwal_seminar(bimbingan_id,tipe_seminar,tanggal_seminar,lokasi,penguji_1,penguji_2,penguji_3,catatan) VALUES(?,?,?,?,?,?,?,?)');$s->bind_param('isssiiis',$bimbinganId,$tipe,$tanggal,$lokasi,$p1,$p2,$p3,$catatan);$ok=$s->execute();$s->close();return $ok;
    }
    public function updateStatus(int $id,string $status): bool {if(!in_array($status,['terjadwal','berlangsung','selesai','dibatalkan'],true))return false;$s=$this->conn->prepare('UPDATE jadwal_seminar SET status=? WHERE id=?');$s->bind_param('si',$status,$id);$ok=$s->execute();$s->close();return $ok;}
}
