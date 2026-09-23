<?php
/**
 * Pembimbing 2: satu bimbingan dapat memiliki pembimbing kedua (tabel mythesis_pembimbing2).
 * Review berurutan: Pembimbing 2 baru dapat memberi keputusan setelah Pembimbing 1 memberi ACC
 * pada dokumen atau judul yang sama (tabel mythesis_review_dosen).
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
function p2_summary(mysqli $db, int $bid, string $kind, int $id): string {
    $decisions=p2_decisions($db,$kind,$id);$parts=[];
    foreach(p2_team($db,$bid) as $d){
        $label=['disetujui'=>'Disetujui','direvisi'=>'Perlu revisi'][$decisions[$d['id']]??'']??'Belum direview';
        if((int)$d['urutan']===2 && !p2_can_review($db,$bid,$kind,$id,(int)$d['id']))$label='Menunggu ACC Pembimbing 1';
        $parts[]='<small class="table-subtext">Pembimbing '.(int)$d['urutan'].' — '.h($d['nama_lengkap']).': '.h($label).'</small>';
    }
    return implode('',$parts);
}
/** Review is sequential for each title/document version. */
function p2_can_review(mysqli $db, int $bid, string $kind, int $id, int $uid): bool {
    $team=p2_team($db,$bid);
    $first=0;$second=0;
    foreach($team as $teacher){
        if((int)$teacher['urutan']===1)$first=(int)$teacher['id'];
        if((int)$teacher['urutan']===2)$second=(int)$teacher['id'];
    }
    if($uid===$first)return true;
    return $uid===$second && $second>0 && (p2_decisions($db,$kind,$id)[$first]??'')==='disetujui';
}
/** Called inside a transaction. Locks guidance first to serialize both reviewers. */
function p2_decide(mysqli $db, string $kind, int $id, int $uid, string $decision, string $revisionStatus='ditangguhkan'): bool {
    if(!in_array($kind,['bab','judul'],true)||!in_array($decision,['disetujui','direvisi'],true))return false;
    $table=$kind==='bab'?'bab_skripsi':'bimbingan';
    $r=$db->query("SELECT * FROM $table WHERE id=$id")->fetch_assoc();
    if(!$r)return false;
    $bid=$kind==='bab'?(int)$r['bimbingan_id']:$id;
    $db->query("SELECT id FROM bimbingan WHERE id=$bid FOR UPDATE");
    if(!p2_is_member($db,$bid,$uid) || !p2_can_review($db,$bid,$kind,$id,$uid))return false;
    $status=$kind==='bab'?$decision:($decision==='disetujui'?'aktif':$revisionStatus);
    if(p2_ready($db)){
        $team=p2_team($db,$bid);$before=p2_decisions($db,$kind,$id);
        $first=(int)$team[0]['id'];
        // If the first supervisor requests revision again, second-stage ACC
        // must be renewed. Existing comments/history are not deleted.
        if($uid===$first && $decision==='direvisi'){
            $s=$db->prepare('DELETE FROM mythesis_review_dosen WHERE jenis=? AND objek_id=? AND dosen_id<>?');
            $s->bind_param('sii',$kind,$id,$first);$ok=$s->execute();$s->close();if(!$ok)return false;
        }
        $s=$db->prepare('INSERT INTO mythesis_review_dosen(jenis,objek_id,dosen_id,keputusan) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE keputusan=VALUES(keputusan),updated_at=CURRENT_TIMESTAMP');
        $s->bind_param('siis',$kind,$id,$uid,$decision);$ok=$s->execute();$s->close();if(!$ok)return false;
        $states=p2_decisions($db,$kind,$id);
        if($uid===$first && $decision==='disetujui' && ($before[$first]??'')!=='disetujui'){
            foreach($team as $teacher){
                if((int)$teacher['urutan']!==2)continue;
                $recipient=(int)$teacher['id'];
                $label=$kind==='bab'?($r['nama_bab'].' versi '.$r['versi']):'Pengajuan judul';
                $message=$label.' mendapat ACC Pembimbing 1 dan siap Anda review sebagai Pembimbing 2.';
                $link='?page=dashboard#mahasiswa-bimbingan';
                notify_user($db,$recipient,'unggah_bab',$message,$link,'Siap direview Pembimbing 2: '.$label,'Menunggu review Anda sebagai Pembimbing 2','Tinjau Sekarang');
            }
        }
        $all=true;$rev=false;
        foreach($team as $d){$state=$states[$d['id']]??'';$all=$all&&$state==='disetujui';$rev=$rev||$state==='direvisi';}
        $pendingTitle=$revisionStatus;
        $column=$kind==='judul'?$db->query("SHOW COLUMNS FROM bimbingan LIKE 'status'")->fetch_assoc():null;
        if($column && strpos($column['Type'],"'pengajuan_judul'")!==false)$pendingTitle='pengajuan_judul';
        $status=$kind==='bab'?($rev?'direvisi':($all?'disetujui':'menunggu_review')):($rev?$revisionStatus:($all?'aktif':$pendingTitle));
    }
    $s=$db->prepare("UPDATE $table SET status=? WHERE id=?");$s->bind_param('si',$status,$id);$ok=$s->execute();$s->close();return $ok;
}
function p2_write(mysqli $db, string $sql): void {
    if($db->query($sql)===false)throw new RuntimeException('Perubahan bimbingan belum dapat disimpan.');
}
function p2_choose(mysqli $db, int $student, int $bid, int $lecturer): void {
    if(!p2_ready($db))throw new RuntimeException('Admin perlu menjalankan migrasi Pembimbing 2 terlebih dahulu.');
    $db->begin_transaction();
    try{
        $s=$db->prepare('SELECT * FROM bimbingan WHERE id=? AND mahasiswa_id=? FOR UPDATE');$s->bind_param('ii',$bid,$student);$s->execute();$b=$s->get_result()->fetch_assoc();$s->close();
        if(!$b)throw new RuntimeException('Bimbingan tidak ditemukan.');
        if((int)$b['dosen_id']===$lecturer)throw new RuntimeException('Pembimbing 1 dan Pembimbing 2 harus berbeda.');
        $s=$db->prepare("SELECT id FROM users WHERE id=? AND role='dosen' FOR UPDATE");$s->bind_param('i',$lecturer);$s->execute();$valid=$s->get_result()->num_rows>0;$s->close();
        if(!$valid)throw new RuntimeException('Pilih dosen yang sudah terdaftar.');
        $old=$db->query("SELECT dosen_id FROM mythesis_pembimbing2 WHERE bimbingan_id=$bid")->fetch_assoc();
        if($old && (int)$old['dosen_id']===$lecturer){$db->commit();return;}
        // Preserve first-supervisor decisions recorded before this feature.
        if(!$old){
            $first=(int)$b['dosen_id'];
            p2_write($db,"INSERT IGNORE INTO mythesis_review_dosen(jenis,objek_id,dosen_id,keputusan) SELECT 'bab',id,$first,status FROM bab_skripsi WHERE bimbingan_id=$bid AND status IN ('disetujui','direvisi')");
            if(in_array($b['status'],['aktif','selesai'],true))p2_write($db,"INSERT IGNORE INTO mythesis_review_dosen(jenis,objek_id,dosen_id,keputusan) VALUES('judul',$bid,$first,'disetujui')");
        }
        $s=$db->prepare('INSERT INTO mythesis_pembimbing2(bimbingan_id,dosen_id) VALUES(?,?) ON DUPLICATE KEY UPDATE dosen_id=VALUES(dosen_id)');$s->bind_param('ii',$bid,$lecturer);if(!$s->execute())throw new RuntimeException('Pilihan belum dapat disimpan.');$s->close();
        // A replacement supervisor must issue their own decisions; old history remains.
        p2_write($db,"DELETE r FROM mythesis_review_dosen r JOIN bab_skripsi bs ON r.jenis='bab' AND r.objek_id=bs.id WHERE bs.bimbingan_id=$bid AND r.dosen_id=$lecturer");
        p2_write($db,"DELETE FROM mythesis_review_dosen WHERE jenis='judul' AND objek_id=$bid AND dosen_id=$lecturer");
        p2_write($db,"UPDATE bimbingan SET status='aktif' WHERE id=$bid AND status='selesai'");
        p2_write($db,"UPDATE bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id LEFT JOIN mythesis_review_dosen r ON r.jenis='bab' AND r.objek_id=bs.id AND r.dosen_id=b.dosen_id SET bs.status=CASE WHEN r.keputusan='direvisi' THEN 'direvisi' ELSE 'menunggu_review' END WHERE bs.bimbingan_id=$bid AND bs.status<>'draft'");
        $db->commit();
        $message='Anda dipilih sebagai Pembimbing 2 untuk skripsi berjudul: '.$b['judul_skripsi'].'. Silakan lihat mahasiswa bimbingan Anda.';
        notify_user($db,$lecturer,'bimbingan_baru',$message,'?page=dashboard#mahasiswa-bimbingan','Anda dipilih sebagai Pembimbing 2','Mahasiswa bimbingan baru (Pembimbing 2)','Lihat Mahasiswa Bimbingan');
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function p2_match(mysqli $db, string $alias): string {
    return p2_ready($db) ? "? IN ($alias.dosen_id,COALESCE((SELECT p.dosen_id FROM mythesis_pembimbing2 p WHERE p.bimbingan_id=$alias.id),0))" : "$alias.dosen_id=?";
}

/** Batch data for the rendering phase only, after all POST handlers redirect. */
function p2_preload(mysqli $db, array $guidances, array $documents): void {
    $bids=array_map('intval',array_column($guidances,'id'));
    $ids=array_map('intval',array_column($documents,'id'));
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
