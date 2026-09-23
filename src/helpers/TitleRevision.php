<?php
/**
 * Alur judul: mahasiswa mengajukan (status pengajuan_judul), Pembimbing 1 lalu Pembimbing 2 menyetujui
 * atau meminta revisi (status revisi_judul), mahasiswa mengajukan ulang. Riwayat disimpan di tabel konsultasi
 * dengan topik 'Revisi Pengajuan Judul', 'Persetujuan Pengajuan Judul', dan 'Pengajuan Ulang Judul'.
 */
const TITLE_HISTORY_TOPICS = ['Revisi Pengajuan Judul', 'Persetujuan Pengajuan Judul', 'Pengajuan Ulang Judul'];

function title_revision_requested(array $guidance, array $history, array $decisions): bool {
    // "ditangguhkan" berarti bimbingan dihentikan sementara oleh admin, bukan permintaan revisi judul.
    if(!in_array($guidance['status'],['pengajuan_judul','revisi_judul'],true))return false;
    if(($history[0]['topik']??'')==='Pengajuan Ulang Judul')return false;
    if(in_array('direvisi',$decisions,true))return true;
    return $guidance['status']==='revisi_judul';
}

// Status awal bimbingan yang diajukan mahasiswa: menunggu persetujuan judul bila basis data mendukungnya.
function initial_guidance_status(mysqli $db): string {
    static $status=null;
    if($status!==null)return $status;
    $column=$db->query("SHOW COLUMNS FROM bimbingan LIKE 'status'");
    $row=$column?$column->fetch_assoc():null;
    $status=($row && strpos((string)$row['Type'],"'pengajuan_judul'")!==false)?'pengajuan_judul':'aktif';
    return $status;
}

// Riwayat review judul per bimbingan, terbaru lebih dulu.
function title_history_by_guidance(mysqli $db, array $guidanceIds): array {
    $guidanceIds=array_values(array_unique(array_map('intval',$guidanceIds)));
    if(!$guidanceIds || !column_exists($db,'konsultasi','topik'))return [];
    $list=implode(',',$guidanceIds);
    $sql="SELECT k.*,u.nama_lengkap AS reviewer_nama FROM konsultasi k LEFT JOIN users u ON u.id=k.jawaban_oleh WHERE k.bimbingan_id IN ($list) AND k.topik IN ('Revisi Pengajuan Judul','Persetujuan Pengajuan Judul','Pengajuan Ulang Judul') ORDER BY k.created_at DESC,k.id DESC";
    $result=$db->query($sql);$grouped=[];
    while($result && ($row=$result->fetch_assoc()))$grouped[(int)$row['bimbingan_id']][]=$row;
    return $grouped;
}
function title_revision_fingerprint(array $guidance): string {
    return hash('sha256',($guidance['judul_skripsi']??'')."\n".($guidance['deskripsi']??''));
}
function title_resubmit(mysqli $db, int $student, int $bid, string $title, string $description, string $fingerprint): void {
    if(mb_strlen($title)<5 || mb_strlen($title)>255)throw new RuntimeException('Judul harus terdiri dari 5–255 karakter.');
    if(mb_strlen($description)>2000)throw new RuntimeException('Deskripsi maksimal 2.000 karakter.');
    $db->begin_transaction();
    try {
        $s=$db->prepare('SELECT * FROM bimbingan WHERE id=? AND mahasiswa_id=? FOR UPDATE');
        $s->bind_param('ii',$bid,$student);$s->execute();$b=$s->get_result()->fetch_assoc();$s->close();
        if(!$b)throw new RuntimeException('Bimbingan tidak ditemukan atau bukan milik Anda.');
        $s=$db->prepare("SELECT topik FROM konsultasi WHERE bimbingan_id=? AND topik IN ('Revisi Pengajuan Judul','Persetujuan Pengajuan Judul','Pengajuan Ulang Judul') ORDER BY created_at DESC,id DESC LIMIT 1");
        $s->bind_param('i',$bid);$s->execute();$history=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
        $states=p2_decisions($db,'judul',$bid);$currentStates=[];
        foreach(p2_team($db,$bid) as $d)if(isset($states[$d['id']]))$currentStates[]=$states[$d['id']];
        if(!title_revision_requested($b,$history,$currentStates))throw new RuntimeException('Judul tidak sedang diminta revisi atau sudah diajukan ulang.');
        if(!hash_equals(title_revision_fingerprint($b),$fingerprint))throw new RuntimeException('Judul telah berubah. Periksa data terbaru sebelum mengirim kembali.');
        if($title===$b['judul_skripsi'] && $description===(string)($b['deskripsi']??''))throw new RuntimeException('Perbaiki judul atau deskripsi sesuai catatan dosen sebelum mengajukan ulang.');
        $status='pengajuan_judul';
        $column=$db->query("SHOW COLUMNS FROM bimbingan LIKE 'status'")->fetch_assoc();
        if(strpos($column['Type'],'enum(')===0 && strpos($column['Type'],"'pengajuan_judul'")===false)$status='ditangguhkan';
        $s=$db->prepare('UPDATE bimbingan SET judul_skripsi=?,deskripsi=?,status=? WHERE id=?');
        $s->bind_param('sssi',$title,$description,$status,$bid);if(!$s->execute())throw new RuntimeException('Judul belum dapat disimpan.');$s->close();
        if(p2_ready($db)){
            $s=$db->prepare("DELETE FROM mythesis_review_dosen WHERE jenis='judul' AND objek_id=?");
            $s->bind_param('i',$bid);if(!$s->execute())throw new RuntimeException('Status review belum dapat diperbarui.');$s->close();
        }
        $topic='Pengajuan Ulang Judul';$historyStatus='pending';
        $snapshot="Judul sebelumnya: ".$b['judul_skripsi']."\nDeskripsi sebelumnya: ".($b['deskripsi']??'');
        $answer="Judul perbaikan: ".$title."\nDeskripsi perbaikan: ".$description;
        $s=$db->prepare('INSERT INTO konsultasi(bimbingan_id,topik,deskripsi,status,jawaban,jawaban_oleh,answered_at) VALUES(?,?,?,?,?,?,NOW())');
        $s->bind_param('issssi',$bid,$topic,$snapshot,$historyStatus,$answer,$student);if(!$s->execute())throw new RuntimeException('Riwayat pengajuan belum dapat disimpan.');$s->close();
        $db->commit();
        $recipient=(int)$b['dosen_id'];$message='Mahasiswa mengajukan ulang judul setelah revisi: '.$title;
        notify_user($db,$recipient,'bimbingan_baru',$message,'?page=dashboard#mahasiswa-bimbingan','Pengajuan ulang judul skripsi','Judul diajukan ulang setelah revisi','Tinjau Judul');
    }catch(Throwable $e){$db->rollback();throw $e;}
}
