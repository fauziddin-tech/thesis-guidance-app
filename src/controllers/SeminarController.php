<?php
class SeminarController {
    public function __construct(private mysqli $conn) {}

    public function listForUser(int $userId, string $role): array {
        $sql = $role === 'admin'
            ? "SELECT j.*, b.judul_skripsi, m.nama_lengkap AS mahasiswa_nama,
                      d.nama_lengkap AS dosen_nama,
                      p1.nama_lengkap AS penguji_1_nama, p2.nama_lengkap AS penguji_2_nama,
                      p3.nama_lengkap AS penguji_3_nama
               FROM jadwal_seminar j
               JOIN bimbingan b ON b.id=j.bimbingan_id
               JOIN users m ON m.id=b.mahasiswa_id
               JOIN users d ON d.id=b.dosen_id
               LEFT JOIN users p1 ON p1.id=j.penguji_1
               LEFT JOIN users p2 ON p2.id=j.penguji_2
               LEFT JOIN users p3 ON p3.id=j.penguji_3
               ORDER BY j.tanggal_seminar DESC"
            : "SELECT j.*, b.judul_skripsi, m.nama_lengkap AS mahasiswa_nama,
                      d.nama_lengkap AS dosen_nama,
                      p1.nama_lengkap AS penguji_1_nama, p2.nama_lengkap AS penguji_2_nama,
                      p3.nama_lengkap AS penguji_3_nama
               FROM jadwal_seminar j
               JOIN bimbingan b ON b.id=j.bimbingan_id
               JOIN users m ON m.id=b.mahasiswa_id
               JOIN users d ON d.id=b.dosen_id
               LEFT JOIN users p1 ON p1.id=j.penguji_1
               LEFT JOIN users p2 ON p2.id=j.penguji_2
               LEFT JOIN users p3 ON p3.id=j.penguji_3
               WHERE b.mahasiswa_id=? OR b.dosen_id=? OR j.penguji_1=? OR j.penguji_2=? OR j.penguji_3=?
               ORDER BY j.tanggal_seminar DESC";
        $s = $this->conn->prepare($sql);
        if (!$s) return [];
        if ($role === 'admin') {
            $s->execute();
        } else {
            $s->bind_param('iiiii', $userId, $userId, $userId, $userId, $userId);
            $s->execute();
        }
        $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
        return $rows;
    }

    public function create(int $bimbinganId, string $tipe, string $tanggal, string $lokasi, ?int $p1, ?int $p2, ?int $p3, string $catatan): bool {
        if (!in_array($tipe, ['proposal', 'hasil', 'sidang'], true) || $tanggal === '') return false;

        $s = $this->conn->prepare("SELECT mahasiswa_id, dosen_id FROM bimbingan WHERE id=? AND status='aktif'");
        if (!$s) return false;
        $s->bind_param('i', $bimbinganId);
        $s->execute();
        $b = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$b) return false;

        $ids = array_values(array_filter([$p1, $p2, $p3], fn($v) => $v !== null && $v > 0));
        if (count($ids) !== count(array_unique($ids))) return false;
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql = "SELECT COUNT(*) AS n FROM users WHERE role='dosen' AND id IN ($placeholders)";
            $s = $this->conn->prepare($sql);
            $s->bind_param($types, ...$ids);
            $s->execute();
            $valid = (int)$s->get_result()->fetch_assoc()['n'] === count($ids);
            $s->close();
            if (!$valid) return false;
        }

        $s = $this->conn->prepare('INSERT INTO jadwal_seminar(bimbingan_id,tipe_seminar,tanggal_seminar,lokasi,penguji_1,penguji_2,penguji_3,catatan) VALUES(?,?,?,?,?,?,?,?)');
        if (!$s) return false;
        $s->bind_param('isssiiis', $bimbinganId, $tipe, $tanggal, $lokasi, $p1, $p2, $p3, $catatan);
        $ok = $s->execute();
        $s->close();
        return $ok;
    }

    public function updateStatus(int $id, string $status): bool {
        if (!in_array($status, ['terjadwal', 'berlangsung', 'selesai', 'dibatalkan'], true)) return false;
        $s = $this->conn->prepare('UPDATE jadwal_seminar SET status=? WHERE id=?');
        if (!$s) return false;
        $s->bind_param('si', $status, $id);
        $ok = $s->execute() && $s->affected_rows > 0;
        $s->close();
        return $ok;
    }

    public function getBimbingan(int $id): ?array {
        $s = $this->conn->prepare('SELECT id,mahasiswa_id,dosen_id,judul_skripsi FROM bimbingan WHERE id=?');
        if (!$s) return null;
        $s->bind_param('i', $id);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        return $r ?: null;
    }
}
