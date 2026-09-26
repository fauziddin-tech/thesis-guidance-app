<?php
/**
 * Pembimbing 2: satu bimbingan dapat memiliki pembimbing kedua (tabel mythesis_pembimbing2).
 * Keputusan review dicatat per dosen di tabel mythesis_review_dosen. Aturan pembagian tugas
 * ada di atas p2_is_format_doc().
 * Semua ID yang disisipkan langsung ke SQL di bawah sudah berupa integer.
 */
function p2_ready(mysqli $db): bool {
    static $ready;
    if ($ready === null) {
        $r=$db->query("SHOW TABLES LIKE 'mythesis_pembimbing2'");
        $s=$db->query("SHOW TABLES LIKE 'mythesis_review_dosen'");
        $ready=$r && $s && $r->num_rows>0 && $s->num_rows>0;
    }
    return $ready;
}
function p2_scope(mysqli $db, string $alias, int $id): string {
    $base="$alias.dosen_id=$id";
    return p2_ready($db) ? "($base OR EXISTS (SELECT 1 FROM mythesis_pembimbing2 p2 WHERE p2.bimbingan_id=$alias.id AND p2.dosen_id=$id))" : "($base)";
}
function p2_team(mysqli $db, int $bid): array {
    if(isset($GLOBALS['p2TeamCache'][$bid]))return $GLOBALS['p2TeamCache'][$bid];
    $sql="SELECT u.id,u.nama_lengkap,1 AS urutan FROM bimbingan b JOIN users u ON u.id=b.dosen_id WHERE b.id=$bid";
    if(p2_ready($db)) $sql.=" UNION ALL SELECT u.id,u.nama_lengkap,2 AS urutan FROM mythesis_pembimbing2 p JOIN users u ON u.id=p.dosen_id WHERE p.bimbingan_id=$bid";
    return $db->query($sql.' ORDER BY urutan')->fetch_all(MYSQLI_ASSOC);
}
function p2_is_member(mysqli $db, int $bid, int $uid): bool {
    $scope=p2_scope($db,'b',$uid);
    return $db->query("SELECT b.id FROM bimbingan b WHERE b.id=$bid AND $scope")->num_rows>0;
}
function p2_decisions(mysqli $db, string $kind, int $id): array {
    if(isset($GLOBALS['p2DecisionCache'][$kind][$id]))return $GLOBALS['p2DecisionCache'][$kind][$id];
    if(!p2_ready($db))return [];
    $s=$db->prepare('SELECT dosen_id,keputusan FROM mythesis_review_dosen WHERE jenis=? AND objek_id=?');
    $s->bind_param('si',$kind,$id);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
    return array_column($rows,'keputusan','dosen_id');
}
/**
 * Pembagian tugas (sejak 1.10.0):
 * - Pembimbing 1 (substansi) sendirian mereview judul dan setiap bab (Bab 1–5).
 * - Pembimbing 2 (format penulisan) baru dapat dipilih setelah Bab 1–3 disetujui Pembimbing 1,
 *   lalu mereview satu file gabungan "Naskah Proposal (Bab 1-3)".
 * - Tanpa Pembimbing 2, mahasiswa siap seminar proposal begitu Bab 1–3 disetujui.
 */
const P2_FORMAT_DOC = 'Naskah Proposal (Bab 1-3)';
const P2_FORMAT_TARGET = 90; // kode target unggah untuk naskah proposal

function p2_is_format_doc(string $name): bool {
    return stripos(trim($name), 'Naskah Proposal') === 0;
}
function p2_second_id(mysqli $db, int $bid): int {
    foreach(p2_team($db,$bid) as $teacher)if((int)$teacher['urutan']===2)return (int)$teacher['id'];
    return 0;
}
function p2_first_id(mysqli $db, int $bid): int {
    foreach(p2_team($db,$bid) as $teacher)if((int)$teacher['urutan']===1)return (int)$teacher['id'];
    return 0;
}
/** Versi terakhir tiap dokumen: ['bab' => [1..5 => baris|null], 'naskah' => baris|null]. */
function p2_latest_documents(mysqli $db, int $bid): array {
    if(isset($GLOBALS['p2LatestCache'][$bid]))return $GLOBALS['p2LatestCache'][$bid];
    $latest=['bab'=>array_fill(1,5,null),'naskah'=>null,'proposal'=>null];
    $result=$db->query("SELECT id,nama_bab,versi,status FROM bab_skripsi WHERE bimbingan_id=$bid ORDER BY versi,id");
    while($result && ($row=$result->fetch_assoc())){
        if(p2_is_format_doc((string)$row['nama_bab']))$latest['naskah']=$row;
        elseif(proposal_is_doc((string)$row['nama_bab']))$latest['proposal']=$row;
        elseif($number=chapter_number((string)$row['nama_bab']))$latest['bab'][$number]=$row;
    }
    return $GLOBALS['p2LatestCache'][$bid]=$latest;
}
/** Jumlah Bab 1–3 yang versi terakhirnya sudah disetujui Pembimbing 1. */
function p2_proposal_chapters_done(mysqli $db, int $bid): int {
    $done=0;
    foreach([1,2,3] as $number)if((p2_latest_documents($db,$bid)['bab'][$number]['status']??'')==='disetujui')$done++;
    return $done;
}
/** Status ringkas untuk bagian Pembimbing 2: belum | pembimbing2 | siap (siap seminar) | selesai. */
function p2_proposal_state(mysqli $db, int $bid): string {
    $stage=proposal_stage($db,$bid)['tahap'];
    if(in_array($stage,['selesai','lama'],true))return 'selesai';
    if($stage==='siap_seminar')return 'siap';
    return $stage==='terkunci'?'belum':'pembimbing2';
}
function p2_proposal_badge(mysqli $db, int $bid): string {
    return proposal_badge($db,$bid);
}
/** Aturan unggah naskah proposal untuk Pembimbing 2, dengan bentuk yang sama seperti chapter_upload_state(). */
function p2_format_upload_state(mysqli $db, int $bid): array {
    $locked=function(string $reason){return ['bab'=>0,'nama'=>P2_FORMAT_DOC,'versi'=>0,'alasan'=>$reason];};
    if(!p2_ready($db)||!p2_second_id($db,$bid))return $locked('Naskah proposal diunggah setelah Pembimbing 2 dipilih.');
    if(p2_proposal_chapters_done($db,$bid)<3)return $locked('Naskah proposal dibuka setelah Bab 1–3 disetujui Pembimbing 1.');
    $row=p2_latest_documents($db,$bid)['naskah'];
    if(!$row)return ['bab'=>P2_FORMAT_TARGET,'nama'=>P2_FORMAT_DOC,'versi'=>1,'alasan'=>'Gabungkan Bab 1–3 dalam satu file Word untuk diperiksa format penulisannya oleh Pembimbing 2.'];
    if($row['status']==='direvisi')return ['bab'=>P2_FORMAT_TARGET,'nama'=>P2_FORMAT_DOC,'versi'=>(int)$row['versi']+1,'alasan'=>'Unggah perbaikan naskah proposal sesuai catatan Pembimbing 2.'];
    if($row['status']==='disetujui')return $locked('Naskah Bab 1–3 sudah disetujui Pembimbing 2. Lanjutkan dengan mengunggah proposal penelitian lengkap.');
    return $locked('Naskah proposal versi '.(int)$row['versi'].' masih menunggu review Pembimbing 2.');
}
/** Dosen yang berwenang mereview: judul dan Bab 1–5 → Pembimbing 1; naskah proposal → Pembimbing 2. */
function p2_reviewer(mysqli $db, int $bid, string $kind, int $id): int {
    if($kind==='bab'){
        $name=$GLOBALS['p2DocNameCache'][$id]??null;
        if($name===null){$row=$db->query("SELECT nama_bab FROM bab_skripsi WHERE id=$id")->fetch_assoc();$name=$GLOBALS['p2DocNameCache'][$id]=(string)($row['nama_bab']??'');}
        if(p2_is_format_doc($name))return p2_second_id($db,$bid);
    }
    return p2_first_id($db,$bid);
}
function p2_waiting_label(mysqli $db, int $bid, string $kind, int $id): string {
    return p2_reviewer($db,$bid,$kind,$id)===p2_second_id($db,$bid)&&p2_second_id($db,$bid)?'Menunggu review Pembimbing 2':'Menunggu review Pembimbing 1';
}
function p2_summary(mysqli $db, int $bid, string $kind, int $id): string {
    $reviewer=p2_reviewer($db,$bid,$kind,$id);
    if(!$reviewer)return '<small class="table-subtext">Pembimbing 2 belum dipilih</small>';
    $decision=p2_decisions($db,$kind,$id)[$reviewer]??'';
    foreach(p2_team($db,$bid) as $d){
        if((int)$d['id']!==$reviewer)continue;
        $label=['disetujui'=>'Disetujui','direvisi'=>'Perlu revisi'][$decision]??'Belum direview';
        $role=(int)$d['urutan']===2?' (format penulisan)':'';
        return '<small class="table-subtext">Pembimbing '.(int)$d['urutan'].$role.' — '.h($d['nama_lengkap']).': '.h($label).'</small>';
    }
    return '';
}
function p2_can_review(mysqli $db, int $bid, string $kind, int $id, int $uid): bool {
    $reviewer=p2_reviewer($db,$bid,$kind,$id);
    return $reviewer>0 && $uid===$reviewer;
}
/** Dipanggil di dalam transaksi. Status dokumen/judul langsung mengikuti keputusan dosen yang berwenang. */
function p2_decide(mysqli $db, string $kind, int $id, int $uid, string $decision, string $revisionStatus='ditangguhkan'): bool {
    if(!in_array($kind,['bab','judul'],true)||!in_array($decision,['disetujui','direvisi'],true))return false;
    $table=$kind==='bab'?'bab_skripsi':'bimbingan';
    $r=$db->query("SELECT * FROM $table WHERE id=$id")->fetch_assoc();
    if(!$r)return false;
    $bid=$kind==='bab'?(int)$r['bimbingan_id']:$id;
    $db->query("SELECT id FROM bimbingan WHERE id=$bid FOR UPDATE");
    if(!p2_is_member($db,$bid,$uid) || !p2_can_review($db,$bid,$kind,$id,$uid))return false;
    if(p2_ready($db)){
        $s=$db->prepare('INSERT INTO mythesis_review_dosen(jenis,objek_id,dosen_id,keputusan) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE keputusan=VALUES(keputusan),updated_at=CURRENT_TIMESTAMP');
        $s->bind_param('siis',$kind,$id,$uid,$decision);$ok=$s->execute();$s->close();if(!$ok)return false;
    }
    $status=$kind==='bab'?$decision:($decision==='disetujui'?'aktif':$revisionStatus);
    $s=$db->prepare("UPDATE $table SET status=? WHERE id=?");$s->bind_param('si',$status,$id);$ok=$s->execute();$s->close();
    unset($GLOBALS['p2LatestCache'][$bid]);
    return $ok;
}
function p2_write(mysqli $db, string $sql): void {
    if($db->query($sql)===false)throw new RuntimeException('Perubahan bimbingan belum dapat disimpan.');
}
/** Mahasiswa memilih Pembimbing 2: hanya setelah Bab 1–3 disetujui Pembimbing 1, dan hanya sekali (penggantian lewat admin). */
function p2_choose(mysqli $db, int $student, int $bid, int $lecturer): void {
    if(!p2_ready($db))throw new RuntimeException('Admin perlu menjalankan migrasi Pembimbing 2 terlebih dahulu.');
    $db->begin_transaction();
    try{
        $s=$db->prepare('SELECT * FROM bimbingan WHERE id=? AND mahasiswa_id=? FOR UPDATE');$s->bind_param('ii',$bid,$student);$s->execute();$b=$s->get_result()->fetch_assoc();$s->close();
        if(!$b)throw new RuntimeException('Bimbingan tidak ditemukan.');
        if($b['status']!=='aktif')throw new RuntimeException('Pembimbing 2 hanya dapat dipilih pada bimbingan yang aktif.');
        if(p2_proposal_chapters_done($db,$bid)<3)throw new RuntimeException('Pembimbing 2 dapat dipilih setelah Bab 1, 2, dan 3 disetujui Pembimbing 1.');
        if(p2_latest_documents($db,$bid)['proposal'])throw new RuntimeException('Pembimbing 2 dipilih sebelum mengunggah proposal penelitian. Hubungi administrator bila tetap diperlukan.');
        if($db->query("SELECT dosen_id FROM mythesis_pembimbing2 WHERE bimbingan_id=$bid")->num_rows>0)throw new RuntimeException('Pembimbing 2 sudah dipilih. Hubungi administrator jika perlu diganti.');
        if((int)$b['dosen_id']===$lecturer)throw new RuntimeException('Pembimbing 1 dan Pembimbing 2 harus berbeda.');
        $s=$db->prepare("SELECT id FROM users WHERE id=? AND role='dosen'");$s->bind_param('i',$lecturer);$s->execute();$valid=$s->get_result()->num_rows>0;$s->close();
        if(!$valid)throw new RuntimeException('Pilih dosen yang sudah terdaftar.');
        $s=$db->prepare('INSERT INTO mythesis_pembimbing2(bimbingan_id,dosen_id) VALUES(?,?)');$s->bind_param('ii',$bid,$lecturer);if(!$s->execute())throw new RuntimeException('Pilihan belum dapat disimpan.');$s->close();
        // Keputusan lama dosen ini pada naskah proposal (bila pernah menjadi Pembimbing 2 lalu dilepas) tidak dipakai lagi.
        p2_write($db,"DELETE r FROM mythesis_review_dosen r JOIN bab_skripsi bs ON r.jenis='bab' AND r.objek_id=bs.id WHERE bs.bimbingan_id=$bid AND r.dosen_id=$lecturer AND bs.nama_bab LIKE 'Naskah Proposal%'");
        $db->commit();
        unset($GLOBALS['p2TeamCache'][$bid]);
        $message='Anda dipilih sebagai Pembimbing 2 (format penulisan) untuk skripsi berjudul: '.$b['judul_skripsi'].'. Mahasiswa akan mengunggah naskah Bab 1–3 dalam satu file untuk Anda periksa format penulisannya sebelum mengunggah proposal penelitian.';
        notify_user($db,$lecturer,'bimbingan_baru',$message,'?page=dashboard#mahasiswa-bimbingan','Anda dipilih sebagai Pembimbing 2','Mahasiswa bimbingan baru (Pembimbing 2)','Lihat Mahasiswa Bimbingan');
    }catch(Throwable $e){$db->rollback();throw $e;}
}
/** Admin melepas Pembimbing 2 (mis. mahasiswa salah pilih). Riwayat review dan komentar tetap tersimpan. */
function p2_release(mysqli $db, int $bid): void {
    if(!p2_ready($db))throw new RuntimeException('Fitur Pembimbing 2 belum diaktifkan.');
    $row=$db->query("SELECT p.dosen_id,b.mahasiswa_id,b.judul_skripsi FROM mythesis_pembimbing2 p JOIN bimbingan b ON b.id=p.bimbingan_id WHERE p.bimbingan_id=$bid")->fetch_assoc();
    if(!$row)throw new RuntimeException('Bimbingan ini tidak memiliki Pembimbing 2.');
    p2_write($db,"DELETE FROM mythesis_pembimbing2 WHERE bimbingan_id=$bid");
    unset($GLOBALS['p2TeamCache'][$bid]);
    notify_user($db,(int)$row['mahasiswa_id'],'bimbingan_baru','Administrator melepas Pembimbing 2 dari bimbingan Anda. Setelah Bab 1–3 disetujui Pembimbing 1, Anda dapat memilih Pembimbing 2 kembali atau langsung mengajukan seminar proposal.','?page=dashboard#pembimbing-dua','Pembimbing 2 dilepas','Pembimbing 2 dilepas dari bimbingan Anda','Lihat Bimbingan');
    notify_user($db,(int)$row['dosen_id'],'bimbingan_baru','Anda tidak lagi menjadi Pembimbing 2 untuk skripsi berjudul: '.$row['judul_skripsi'].'.','?page=dashboard','Tidak lagi menjadi Pembimbing 2','Perubahan pembimbing','Buka Dashboard');
}
function p2_match(mysqli $db, string $alias): string {
    return p2_ready($db) ? "? IN ($alias.dosen_id,COALESCE((SELECT p.dosen_id FROM mythesis_pembimbing2 p WHERE p.bimbingan_id=$alias.id),0))" : "$alias.dosen_id=?";
}

/** Batch data for the rendering phase only, after all POST handlers redirect. */
function p2_preload(mysqli $db, array $guidances, array $documents): void {
    $bids=array_map('intval',array_column($guidances,'id'));
    $ids=array_map('intval',array_column($documents,'id'));
    foreach($documents as $document)$GLOBALS['p2DocNameCache'][(int)$document['id']]=(string)$document['nama_bab'];
    if($bids){
        $list=implode(',',$bids);
        foreach($bids as $bid)$GLOBALS['p2TeamCache'][$bid]=[];
        $sql="SELECT b.id AS bid,u.id,u.nama_lengkap,1 AS urutan FROM bimbingan b JOIN users u ON u.id=b.dosen_id WHERE b.id IN ($list)";
        if(p2_ready($db))$sql.=" UNION ALL SELECT p.bimbingan_id AS bid,u.id,u.nama_lengkap,2 AS urutan FROM mythesis_pembimbing2 p JOIN users u ON u.id=p.dosen_id WHERE p.bimbingan_id IN ($list)";
        foreach($db->query($sql.' ORDER BY urutan')->fetch_all(MYSQLI_ASSOC) as $row)$GLOBALS['p2TeamCache'][(int)$row['bid']][]=$row;
    }
    foreach(['judul'=>$bids,'bab'=>$ids] as $kind=>$objects){
        foreach($objects as $id)$GLOBALS['p2DecisionCache'][$kind][$id]=[];
        if(!$objects||!p2_ready($db))continue;
        $list=implode(',',$objects);
        foreach($db->query("SELECT * FROM mythesis_review_dosen WHERE jenis='$kind' AND objek_id IN ($list)")->fetch_all(MYSQLI_ASSOC) as $r)$GLOBALS['p2DecisionCache'][$kind][(int)$r['objek_id']][(int)$r['dosen_id']]=$r['keputusan'];
    }
    $GLOBALS['p2History']=[];
    if($ids){
        $list=implode(',',$ids);
        foreach($db->query("SELECT r.*,u.nama_lengkap FROM revisi r JOIN users u ON u.id=r.dosen_id WHERE r.bab_id IN ($list) ORDER BY r.created_at DESC,r.id DESC")->fetch_all(MYSQLI_ASSOC) as $r)$GLOBALS['p2History'][(int)$r['bab_id']][]=$r;
    }
}
