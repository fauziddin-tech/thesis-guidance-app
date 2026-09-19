<?php
require_once __DIR__."/config/session.php";
require_once __DIR__.'/src/helpers/RateLimit.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/src/helpers/Functions.php';
require_once __DIR__.'/src/helpers/Email.php';

function redirect(string $url): void { header('Location: '.$url); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']??'')) { http_response_code(419); exit('Permintaan tidak valid. Silakan muat ulang halaman.'); } }
function require_login(): void { if (empty($_SESSION['user'])) redirect('?page=login'); }
function require_role(array $roles): void { require_login(); if (!in_array($_SESSION['user']['role']??'', $roles, true)) { http_response_code(403); exit('Akses ditolak.'); } }
function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function impersonation_stop(mysqli $conn): bool {
    $imp=$_SESSION['impersonator']??null;if(!$imp)return false;
    $lid=(int)($imp['log_id']??0);
    if($lid>0){$s=$conn->prepare('UPDATE impersonation_logs SET ended_at=NOW() WHERE id=? AND ended_at IS NULL');if($s){$s->bind_param('i',$lid);$s->execute();$s->close();}}
    $oid=(int)($imp['id']??0);$orig=null;
    $s=$conn->prepare('SELECT id,username,email,role,nama_lengkap,no_telp,profile_photo,dosen_pembimbing_id FROM users WHERE id=? LIMIT 1');
    if($s){$s->bind_param('i',$oid);$s->execute();$orig=$s->get_result()->fetch_assoc();$s->close();}
    unset($_SESSION['impersonator']);
    if($orig){$_SESSION['user']=$orig;}else{unset($_SESSION['user']);}
    session_regenerate_id(true);return true;
}
function notify_user(mysqli $conn,int $userId,string $type,string $message,?string $link=null): void { $s=$conn->prepare('INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,?,?,?)'); if($s){$s->bind_param('isss',$userId,$type,$message,$link);$s->execute();$s->close();} send_user_email($conn,$userId,'Notifikasi MyThesis','Aktivitas pada MyThesis',$message,$link,'Lihat Aktivitas'); }
function chapter_number(string $name): int { return preg_match('/^Bab ([1-5])$/', $name, $m) ? (int)$m[1] : 0; }
function latest_chapter(mysqli $conn,int $bid,string $name): ?array { $s=$conn->prepare('SELECT id,nama_bab,versi,status,file_path FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=? ORDER BY versi DESC,id DESC LIMIT 1'); $s->bind_param('is',$bid,$name); $s->execute(); $row=$s->get_result()->fetch_assoc(); $s->close(); return $row?:null; }

if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&empty($_POST)&&empty($_FILES)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0){
    error_log('[upload] POST melebihi post_max_size: content_length='.(int)$_SERVER['CONTENT_LENGTH'].' post_max_size='.ini_get('post_max_size'));
    flash('danger','Ukuran unggahan ('.format_bytes((int)$_SERVER['CONTENT_LENGTH']).') melebihi batas server ('.ini_get('post_max_size').'). Kecilkan ukuran file atau hubungi administrator.');
    redirect(!empty($_SESSION['user'])?'?page=dashboard':'?page=home');
}

$page=$_GET['page']??'home';
$action=$_POST['action']??null;

if(!empty($_SESSION['impersonator'])){
    if(time()>(int)($_SESSION['impersonator']['expires']??0)){impersonation_stop($conn);flash('danger','Sesi login sebagai mahasiswa berakhir (maksimal 60 menit). Anda kembali ke akun sendiri.');redirect('?page=dashboard');}
    if($page==='change-password'){flash('danger','Halaman ini tidak tersedia saat login sebagai mahasiswa.');redirect('?page=dashboard');}
    if($action!==null&&!in_array($action,['impersonate_stop','logout'],true)){
        if($action==='impersonate_start'){flash('danger','Kembali ke akun Anda terlebih dahulu sebelum membuka akun lain.');redirect('?page=dashboard');}
        if(in_array($action,['update_profile','forgot_password','reset_password','login','register'],true)){flash('danger','Tindakan ini tidak tersedia saat login sebagai mahasiswa.');redirect('?page=dashboard');}
        if(($_SESSION['impersonator']['mode']??'view')!=='act'){flash('danger','Mode "Lihat sebagai" hanya untuk melihat. Kembali ke akun Anda lalu pilih "Login penuh" jika perlu menguji unggah atau kirim data.');redirect('?page=dashboard');}
    }
}

if($action==='impersonate_start'){
    require_role(['admin','dosen']);verify_csrf();
    $actor=$_SESSION['user'];$actorId=(int)$actor['id'];$targetId=(int)($_POST['user_id']??0);$mode=(($_POST['mode']??'view')==='act')?'act':'view';
    $back=$actor['role']==='admin'?'?page=admin-dashboard#manajemen-akun':'?page=dashboard';
    $s=$conn->prepare("SELECT id,username,email,role,nama_lengkap,no_telp,profile_photo,dosen_pembimbing_id FROM users WHERE id=? AND role='mahasiswa' LIMIT 1");$s->bind_param('i',$targetId);$s->execute();$target=$s->get_result()->fetch_assoc();$s->close();
    if(!$target){flash('danger','Akun mahasiswa tidak ditemukan.');redirect($back);}
    if($actor['role']==='dosen'){
        $s=$conn->prepare('SELECT 1 FROM users u WHERE u.id=? AND (u.dosen_pembimbing_id=? OR EXISTS(SELECT 1 FROM bimbingan b WHERE b.mahasiswa_id=u.id AND b.dosen_id=?)) LIMIT 1');$s->bind_param('iii',$targetId,$actorId,$actorId);$s->execute();$allowed=$s->get_result()->num_rows>0;$s->close();
        if(!$allowed){http_response_code(403);exit('Akses ditolak.');}
    }
    $logId=0;
    try{
        $s=$conn->prepare('INSERT INTO impersonation_logs(impersonator_id,impersonator_nama,impersonator_role,target_id,target_nama,mode,ip_address) VALUES(?,?,?,?,?,?,?)');
        if(!$s)throw new Exception('prepare');
        $an=(string)$actor['nama_lengkap'];$ar=(string)$actor['role'];$tn=(string)$target['nama_lengkap'];$ip=client_ip();
        $s->bind_param('ississs',$actorId,$an,$ar,$targetId,$tn,$mode,$ip);
        if(!$s->execute())throw new Exception('execute');
        $logId=(int)$conn->insert_id;$s->close();
    }catch(Throwable $e){$logId=0;}
    if($logId<1){flash('danger','Login sebagai belum dapat dipakai karena tabel log belum tersedia. Jalankan migration_add_impersonation_logs.sql di database.');redirect($back);}
    $msg=ucfirst($actor['role']).' '.$actor['nama_lengkap'].' membuka akun Anda ('.($mode==='act'?'login penuh':'hanya melihat').') untuk membantu menelusuri kendala.';
    $s=$conn->prepare('INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,?,?,NULL)');if($s){$tipe='akses_akun';$s->bind_param('iss',$targetId,$tipe,$msg);$s->execute();$s->close();}
    $_SESSION['impersonator']=['id'=>$actorId,'role'=>$actor['role'],'nama'=>$actor['nama_lengkap'],'mode'=>$mode,'log_id'=>$logId,'expires'=>time()+3600];
    $_SESSION['user']=$target;session_regenerate_id(true);
    redirect('?page=dashboard');
}

if($action==='impersonate_stop'){
    require_login();verify_csrf();
    $wasAdmin=(($_SESSION['impersonator']['role']??'')==='admin');
    if(impersonation_stop($conn))flash('success','Anda kembali ke akun Anda.');
    redirect($wasAdmin?'?page=admin-dashboard#manajemen-akun':'?page=dashboard');
}

if($action==='logout'){
    verify_csrf();
    if(!empty($_SESSION['impersonator']['log_id'])){$lid=(int)$_SESSION['impersonator']['log_id'];$s=$conn->prepare('UPDATE impersonation_logs SET ended_at=NOW() WHERE id=? AND ended_at IS NULL');if($s){$s->bind_param('i',$lid);$s->execute();$s->close();}}
    $_SESSION=[];
    if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
    session_destroy(); redirect('?page=home');
}

if($action==='login'){
    verify_csrf(); $identity=trim($_POST['identity']??''); $password=$_POST['password']??'';
    if($identity===''||$password===''){flash('danger','Username/email dan password wajib diisi.');redirect('?page=login');}
    $rlId=rl_key($identity);$rlIp=rl_key(client_ip());
    if(rl_count($conn,'login',$rlId,900)>=5||rl_count($conn,'login_ip',$rlIp,900)>=20){flash('danger','Terlalu banyak percobaan login. Coba lagi dalam 15 menit.');redirect('?page=login');}
    $s=$conn->prepare('SELECT id,username,email,password,role,nama_lengkap,no_telp,profile_photo,dosen_pembimbing_id FROM users WHERE username=? OR email=? ORDER BY (email=?) DESC LIMIT 1');
    $s->bind_param('sss',$identity,$identity,$identity);$s->execute();$user=$s->get_result()->fetch_assoc();$s->close();
    if(!$user||!password_verify($password,$user['password'])){rl_hit($conn,'login',$rlId);rl_hit($conn,'login_ip',$rlIp);flash('danger','Username/email atau password salah.');redirect('?page=login');}
    unset($user['password']); $_SESSION['user']=$user; session_regenerate_id(true); send_user_email($conn,(int)$user['id'],'Login MyThesis berhasil','Login berhasil','Akun Anda baru saja digunakan untuk masuk ke MyThesis. Jika ini bukan Anda, segera ubah password dan hubungi administrator.','?page=profile','Buka Profil'); redirect('?page=dashboard');
}

if($action==='register'){
    verify_csrf(); $username=trim($_POST['username']??'');$email=trim($_POST['email']??'');$nama=trim($_POST['nama_lengkap']??'');$telp=trim($_POST['no_telp']??'');$telpDb=$telp!==''?$telp:null;$dosen=(int)($_POST['dosen_pembimbing_id']??0);$password=$_POST['password']??'';$confirm=$_POST['confirm_password']??'';
    if(strpos($username,'@')!==false){flash('danger','Username tidak boleh mengandung karakter @. Gunakan username biasa.');redirect('?page=register');}
    if($username===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$nama===''||$dosen<1||strlen($password)<8||$password!==$confirm){flash('danger','Data pendaftaran tidak valid. Password minimal 8 karakter.');redirect('?page=register');}
    if($telp!==''&&!preg_match('/^[0-9+\-\s()]{5,20}$/',$telp)){flash('danger','Nomor HP/telepon tidak valid.');redirect('?page=register');}
    $s=$conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen' LIMIT 1");$s->bind_param('i',$dosen);$s->execute();$validDosen=$s->get_result()->num_rows>0;$s->close();if(!$validDosen){flash('danger','Dosen pembimbing tidak valid. Silakan pilih dosen yang tersedia.');redirect('?page=register');}
    $s=$conn->prepare('SELECT id,email,no_telp FROM users WHERE email=? OR (no_telp IS NOT NULL AND no_telp<>? AND no_telp=?) LIMIT 1');$s->bind_param('sss',$email,$telpDb,$telpDb);$s->execute();$duplicate=$s->get_result()->fetch_assoc();$s->close();
    if($duplicate){$field=!empty($duplicate['email'])&&strcasecmp($duplicate['email'],$email)===0?'email':'nomor HP';flash('danger','Pendaftaran ditolak. '.$field.' tersebut sudah terdaftar pada akun lain.');redirect('?page=register');}
    $hash=password_hash($password,PASSWORD_DEFAULT);$role='mahasiswa';$s=$conn->prepare('INSERT INTO users(username,email,password,role,nama_lengkap,no_telp,dosen_pembimbing_id) VALUES(?,?,?,?,?,?,?)');$s->bind_param('ssssssi',$username,$email,$hash,$role,$nama,$telpDb,$dosen);
    if($s->execute()) flash('success','Registrasi berhasil. Silakan login.'); else flash('danger','Registrasi gagal. Username/email mungkin sudah digunakan.');$s->close();redirect('?page=login');
}

if($action==='update_profile'){
    require_login();verify_csrf();$uid=(int)$_SESSION['user']['id'];$nama=trim($_POST['nama_lengkap']??'');$email=trim($_POST['email']??'');$telp=trim($_POST['no_telp']??'');
    if($nama===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('danger','Nama lengkap dan email yang valid wajib diisi.');redirect('?page=profile');}
    if($telp!==''&&!preg_match('/^[0-9+\-\s()]{5,20}$/',$telp)){flash('danger','Nomor HP/telepon tidak valid.');redirect('?page=profile');}
    $s=$conn->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');$s->bind_param('si',$email,$uid);$s->execute();$exists=$s->get_result()->num_rows>0;$s->close();if($exists){flash('danger','Email sudah digunakan pengguna lain.');redirect('?page=profile');}
    $s=$conn->prepare('SELECT id FROM users WHERE no_telp=? AND id<>? AND no_telp IS NOT NULL AND no_telp<>? LIMIT 1');$s->bind_param('sis',$telp,$uid,$telp);$s->execute();$phoneExists=$s->get_result()->num_rows>0;$s->close();if($telp!==''&&$phoneExists){flash('danger','Nomor HP sudah digunakan pengguna lain.');redirect('?page=profile');}
    $telpDb=$telp!==''?$telp:null;
    $photoPath=null;$photo=$_FILES['profile_photo']??null;
    if($photo&&$photo['error']!==UPLOAD_ERR_NO_FILE){
        if($photo['error']!==UPLOAD_ERR_OK||$photo['size']>2*1024*1024){flash('danger','Foto profil maksimal 2 MB.');redirect('?page=profile');}
        $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($photo['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($allowed[$mime])){flash('danger','Format foto harus JPG, PNG, atau WEBP.');redirect('?page=profile');}
        $dir=__DIR__.'/uploads/profil';if(!is_dir($dir))mkdir($dir,0750,true);$photoPath='uploads/profil/'.bin2hex(random_bytes(16)).'.'.$allowed[$mime];
        if(!move_uploaded_file($photo['tmp_name'],__DIR__.'/'.$photoPath)){flash('danger','Foto profil gagal disimpan.');redirect('?page=profile');}
    }
    $oldPhoto=null;$s=$conn->prepare('SELECT profile_photo FROM users WHERE id=? LIMIT 1');$s->bind_param('i',$uid);$s->execute();$oldPhoto=$s->get_result()->fetch_assoc()['profile_photo']??null;$s->close();
    if($photoPath){$s=$conn->prepare('UPDATE users SET nama_lengkap=?,email=?,no_telp=?,profile_photo=? WHERE id=?');$s->bind_param('ssssi',$nama,$email,$telpDb,$photoPath,$uid);}else{$s=$conn->prepare('UPDATE users SET nama_lengkap=?,email=?,no_telp=? WHERE id=?');$s->bind_param('sssi',$nama,$email,$telpDb,$uid);}
    if($s->execute()){
        $_SESSION['user']['nama_lengkap']=$nama;$_SESSION['user']['email']=$email;$_SESSION['user']['no_telp']=$telp;if($photoPath)$_SESSION['user']['profile_photo']=$photoPath; send_user_email($conn,$uid,'Profil MyThesis diperbarui','Profil berhasil diperbarui','Informasi profil akun Anda telah diperbarui. Jika Anda tidak melakukan perubahan ini, segera hubungi administrator.','?page=profile','Buka Profil');
        if($photoPath&&$oldPhoto&&strpos($oldPhoto,'uploads/profil/')===0){$oldReal=realpath(__DIR__.'/'.$oldPhoto);$base=realpath(__DIR__.'/uploads/profil');if($oldReal&&$base&&strpos($oldReal,$base.DIRECTORY_SEPARATOR)===0)@unlink($oldReal);}
        flash('success','Profil berhasil diperbarui.');
    }else{if($photoPath)@unlink(__DIR__.'/'.$photoPath);flash('danger','Profil gagal diperbarui.');}
    $s->close();redirect('?page=profile');
}

if($action==='create_bimbingan'){
    require_role(['mahasiswa']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$dosen=(int)($_POST['dosen_id']??0);$judul=trim($_POST['judul_skripsi']??'');$desc=trim($_POST['deskripsi']??'');
    if($judul===''||$dosen<1){flash('danger','Judul dan dosen wajib dipilih.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT id FROM bimbingan WHERE mahasiswa_id=? AND status IN ('aktif','pengajuan_judul','revisi_judul') LIMIT 1");$s->bind_param('i',$uid);$s->execute();$has=$s->get_result()->num_rows>0;$s->close();
    if($has){flash('danger','Anda masih memiliki pengajuan judul atau bimbingan aktif.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen' LIMIT 1");$s->bind_param('i',$dosen);$s->execute();$valid=$s->get_result()->num_rows>0;$s->close();
    if(!$valid){flash('danger','Dosen tidak valid.');redirect('?page=dashboard');}
    require_once __DIR__.'/src/controllers/BimbinganController.php';$ctrl=new BimbinganController($conn);$r=$ctrl->create($uid,$dosen,$judul,$desc);flash($r['success']?'success':'danger',$r['success']?'Pengajuan judul berhasil dikirim dan menunggu persetujuan dosen.':'Pengajuan judul gagal dikirim.');redirect('?page=dashboard');
}

if($action==='revise_judul'){
    require_role(['dosen']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bid=(int)($_POST['bimbingan_id']??0);$catatan=trim($_POST['catatan_judul']??'');
    if($catatan===''){flash('danger','Catatan revisi judul wajib diisi.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT id,mahasiswa_id,judul_skripsi,deskripsi FROM bimbingan WHERE id=? AND dosen_id=? AND status='pengajuan_judul' LIMIT 1");$s->bind_param('ii',$bid,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
    if(!$r){flash('danger','Pengajuan judul tidak ditemukan atau sudah diproses.');redirect('?page=dashboard');}
    $conn->begin_transaction();try{
        $s=$conn->prepare("INSERT INTO judul_revisi (bimbingan_id,dosen_id,mahasiswa_id,judul_sebelum,deskripsi_sebelum,catatan_dosen,status) VALUES (?,?,?,?,?,?,'diminta')");$s->bind_param('iiisss',$bid,$uid,$r['mahasiswa_id'],$r['judul_skripsi'],$r['deskripsi'],$catatan);if(!$s->execute())throw new Exception();$s->close();
        $s=$conn->prepare("UPDATE bimbingan SET status='revisi_judul', judul_revision_catatan=?, judul_revision_at=NOW() WHERE id=?");$s->bind_param('si',$catatan,$bid);if(!$s->execute())throw new Exception();$s->close();
        $conn->commit();notify_user($conn,(int)$r['mahasiswa_id'],'judul_direvisi','Judul skripsi perlu diperbaiki. Catatan dosen: '.$catatan,'?page=bimbingan-detail&id='.$bid);flash('success','Permintaan revisi judul tersimpan dalam riwayat.');
    }catch(Throwable $e){$conn->rollback();flash('danger','Permintaan revisi judul gagal disimpan.');}
    redirect('?page=dashboard');
}

if($action==='submit_judul_revision'){
    require_role(['mahasiswa']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bid=(int)($_POST['bimbingan_id']??0);$judul=trim($_POST['judul_skripsi']??'');$desc=trim($_POST['deskripsi']??'');
    if($bid<1||$judul===''){flash('danger','Judul skripsi wajib diisi.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT id,dosen_id FROM bimbingan WHERE id=? AND mahasiswa_id=? AND status='revisi_judul' LIMIT 1");$s->bind_param('ii',$bid,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
    if(!$r){flash('danger','Pengajuan revisi judul tidak ditemukan atau sudah diproses.');redirect('?page=dashboard');}
    $conn->begin_transaction();try{
        $s=$conn->prepare("SELECT id FROM judul_revisi WHERE bimbingan_id=? AND status='diminta' ORDER BY id DESC LIMIT 1");$s->bind_param('i',$bid);$s->execute();$rev=$s->get_result()->fetch_assoc();$s->close();
        if(!$rev)throw new Exception();
        $s=$conn->prepare("UPDATE judul_revisi SET judul_sesudah=?,deskripsi_sesudah=?,status='diajukan_ulang',resubmitted_at=NOW() WHERE id=?");$s->bind_param('ssi',$judul,$desc,$rev['id']);if(!$s->execute())throw new Exception();$s->close();
        $s=$conn->prepare("UPDATE bimbingan SET judul_skripsi=?,deskripsi=?,status='pengajuan_judul',judul_revision_catatan=NULL WHERE id=?");$s->bind_param('ssi',$judul,$desc,$bid);if(!$s->execute())throw new Exception();$s->close();
        $conn->commit();notify_user($conn,(int)$r['dosen_id'],'judul_diajukan_ulang','Mahasiswa telah mengirim ulang judul skripsi setelah revisi.','?page=bimbingan-detail&id='.$bid);flash('success','Revisi judul tersimpan dan dikirim kembali untuk ditinjau dosen.');
    }catch(Throwable $e){$conn->rollback();flash('danger','Pengajuan ulang judul gagal disimpan.');}
    redirect('?page=dashboard');
}

if($action==='approve_judul'){
    require_role(['dosen']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bid=(int)($_POST['bimbingan_id']??0);
    $s=$conn->prepare("SELECT id,mahasiswa_id FROM bimbingan WHERE id=? AND dosen_id=? AND status='pengajuan_judul' LIMIT 1");$s->bind_param('ii',$bid,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
    if(!$r){flash('danger','Pengajuan judul tidak ditemukan atau sudah diproses.');redirect('?page=dashboard');}
    $conn->begin_transaction();try{
        $s=$conn->prepare("SELECT id FROM judul_revisi WHERE bimbingan_id=? AND status='diajukan_ulang' ORDER BY id DESC LIMIT 1");$s->bind_param('i',$bid);$s->execute();$rev=$s->get_result()->fetch_assoc();$s->close();
        if($rev){$s=$conn->prepare("UPDATE judul_revisi SET status='disetujui',approved_at=NOW() WHERE id=?");$s->bind_param('i',$rev['id']);if(!$s->execute())throw new Exception();$s->close();}
        $s=$conn->prepare("UPDATE bimbingan SET status='aktif' WHERE id=?");$s->bind_param('i',$bid);if(!$s->execute())throw new Exception();$s->close();
        $conn->commit();notify_user($conn,(int)$r['mahasiswa_id'],'judul_disetujui','Pengajuan judul skripsi telah disetujui. Anda dapat melanjutkan ke Bab 1.','?page=bimbingan-detail&id='.$bid);flash('success','Judul disetujui. Riwayat revisi judul tetap tersimpan.');
    }catch(Throwable $e){$conn->rollback();flash('danger','Persetujuan judul gagal disimpan.');}
    redirect('?page=dashboard');
}

if($action==='upload_bab'){
    require_role(['mahasiswa']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bid=(int)($_POST['bimbingan_id']??0);$name=trim($_POST['nama_bab']??'');$n=chapter_number($name);
    $s=$conn->prepare("SELECT id FROM bimbingan WHERE id=? AND mahasiswa_id=? AND status='aktif'");$s->bind_param('ii',$bid,$uid);$s->execute();$owned=$s->get_result()->num_rows>0;$s->close();
    if(!$owned||$n===0){flash('danger','Bimbingan atau bab tidak valid.');redirect('?page=dashboard');}
    if($n>1){$prev=latest_chapter($conn,$bid,'Bab '.($n-1));if(!$prev||$prev['status']!=='disetujui'){flash('danger','Bab sebelumnya harus mendapat ACC terlebih dahulu.');redirect('?page=dashboard');}}
    $latest=latest_chapter($conn,$bid,$name);if($latest&&in_array($latest['status'],['menunggu_review','disetujui'],true)){flash('danger','Bab ini masih menunggu review atau sudah ACC.');redirect('?page=dashboard');}
    $versi=$latest?(int)$latest['versi']+1:1;$file=$_FILES['file']??null;$upErr=$file?upload_error_message((int)$file['error'],'File'):'Pilih file skripsi terlebih dahulu.';
    if($upErr!==null){error_log('[upload_bab] uid='.$uid.' kode='.($file['error']??'kosong'));flash('danger',$upErr);redirect('?page=dashboard');}
    if($file['size']>10*1024*1024){flash('danger','Ukuran file '.format_bytes((int)$file['size']).' melebihi batas 10 MB.');redirect('?page=dashboard');}
    $docType=detect_document_type($file['tmp_name'],(string)($file['name']??''));
    if($docType===null){$fh=@fopen($file['tmp_name'],'rb');$hd=$fh?bin2hex((string)fread($fh,8)):'';if($fh)fclose($fh);error_log('[upload_bab] tipe tidak dikenali uid='.$uid.' ext='.strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION)).' size='.(int)$file['size'].' head='.$hd);flash('danger','File tidak dikenali sebagai PDF, DOC, atau DOCX yang valid. Pastikan file tidak rusak, lalu simpan ulang sebagai PDF atau DOCX dan coba lagi.');redirect('?page=dashboard');}
    $dir=__DIR__.'/uploads/bab';if(!is_dir($dir))mkdir($dir,0750,true);$stored='uploads/bab/'.bin2hex(random_bytes(16)).'.'.$docType;$dest=__DIR__.'/'.$stored;if(!move_uploaded_file($file['tmp_name'],$dest)){flash('danger','File gagal disimpan.');redirect('?page=dashboard');}
    $status='menunggu_review';$s=$conn->prepare('INSERT INTO bab_skripsi(bimbingan_id,nama_bab,file_path,versi,status) VALUES(?,?,?,?,?)');$s->bind_param('issis',$bid,$name,$stored,$versi,$status);$ok=$s->execute();$s->close();if(!$ok){@unlink($dest);flash('danger','Data bab gagal disimpan.');redirect('?page=dashboard');}
    $s=$conn->prepare('SELECT dosen_id FROM bimbingan WHERE id=?');$s->bind_param('i',$bid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if($r)notify_user($conn,(int)$r['dosen_id'],'bab_baru','Mahasiswa mengunggah '.$name.'.','?page=bimbingan-detail&id='.$bid);flash('success',$name.' berhasil diunggah dan menunggu review.');redirect('?page=dashboard');
}

if($action==='add_revision'){
    require_role(['dosen']);verify_csrf();
    $uid=(int)$_SESSION['user']['id'];$babId=(int)($_POST['bab_id']??0);
    $comment=trim($_POST['komentar']??'');$type=$_POST['tipe_revisi']??'minor';
    if($comment===''||!in_array($type,['minor','major','kritis'],true)){flash('danger','Komentar dan tipe revisi wajib diisi.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT bs.id,bs.nama_bab,b.id AS bimbingan_id,b.mahasiswa_id FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id WHERE bs.id=? AND b.dosen_id=? AND b.status='aktif' AND bs.status='menunggu_review' AND bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=bs.bimbingan_id AND x.nama_bab=bs.nama_bab ORDER BY x.versi DESC,x.id DESC LIMIT 1)");
    $s->bind_param('ii',$babId,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
    if(!$r){flash('danger','Bab tidak dapat direvisi.');redirect('?page=dashboard');}
    $filePath=null;$file=$_FILES['file_revisi']??null;
    if($file && $file['error']!==UPLOAD_ERR_NO_FILE){
        $upErr=upload_error_message((int)$file['error'],'File revisi');
        if($upErr!==null){error_log('[add_revision] uid='.$uid.' kode='.$file['error']);flash('danger',$upErr);redirect('?page=dashboard');}
        if($file['size']>10*1024*1024){flash('danger','Ukuran file revisi '.format_bytes((int)$file['size']).' melebihi batas 10 MB.');redirect('?page=dashboard');}
        $docType=detect_document_type($file['tmp_name'],(string)($file['name']??''));
        if($docType===null){flash('danger','File revisi tidak dikenali sebagai PDF, DOC, atau DOCX yang valid.');redirect('?page=dashboard');}
        $dir=__DIR__.'/uploads/revisi';if(!is_dir($dir))mkdir($dir,0750,true);
        $filePath='uploads/revisi/'.bin2hex(random_bytes(16)).'.'.$docType;
        if(!move_uploaded_file($file['tmp_name'],__DIR__.'/'.$filePath)){flash('danger','File revisi gagal disimpan.');redirect('?page=dashboard');}
    }
    $conn->begin_transaction();try{
        $s=$conn->prepare('INSERT INTO revisi(bab_id,dosen_id,komentar,tipe_revisi,file_path) VALUES(?,?,?,?,?)');
        $s->bind_param('iisss',$babId,$uid,$comment,$type,$filePath);if(!$s->execute())throw new Exception();$s->close();
        $s=$conn->prepare("UPDATE bab_skripsi SET status='direvisi' WHERE id=?");$s->bind_param('i',$babId);if(!$s->execute())throw new Exception();$s->close();
        $conn->commit();
        notify_user($conn,(int)$r['mahasiswa_id'],'revisi_bab','Ada revisi untuk '.$r['nama_bab'].'.','?page=bimbingan-detail&id='.(int)$r['bimbingan_id']);
        flash('success','Komentar revisi berhasil dikirim'.($filePath?' beserta file revisi.':'.'));
    }catch(Throwable $e){$conn->rollback();if($filePath)@unlink(__DIR__.'/'.$filePath);flash('danger','Revisi gagal disimpan.');}
    redirect('?page=dashboard');
}

if($action==='approve_bab'){
    require_role(['dosen']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$babId=(int)($_POST['bab_id']??0);
    $s=$conn->prepare("SELECT bs.id,bs.nama_bab,b.id AS bimbingan_id,b.mahasiswa_id,b.status AS bimbingan_status FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id WHERE bs.id=? AND b.dosen_id=? AND b.status='aktif' AND bs.status='menunggu_review' AND bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=bs.bimbingan_id AND x.nama_bab=bs.nama_bab ORDER BY x.versi DESC,x.id DESC LIMIT 1)");$s->bind_param('ii',$babId,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if(!$r){flash('danger','Bab tidak dapat di-ACC.');redirect('?page=dashboard');}
    $n=chapter_number($r['nama_bab']);if($n===0){flash('danger','Nama bab tidak valid.');redirect('?page=dashboard');}if($n>1){$prev=latest_chapter($conn,(int)$r['bimbingan_id'],'Bab '.($n-1));if(!$prev||$prev['status']!=='disetujui'){flash('danger','Bab sebelumnya harus sudah ACC.');redirect('?page=dashboard');}}
    $conn->begin_transaction();try{$s=$conn->prepare("UPDATE bab_skripsi SET status='disetujui' WHERE id=?");$s->bind_param('i',$babId);if(!$s->execute())throw new Exception();$s->close();if($n===5){$s=$conn->prepare("UPDATE bimbingan SET status='selesai' WHERE id=? AND status='aktif'");$s->bind_param('i',$r['bimbingan_id']);if(!$s->execute())throw new Exception();$s->close();}$conn->commit();notify_user($conn,(int)$r['mahasiswa_id'],'bab_disetujui',$r['nama_bab'].' telah mendapat ACC.'.($n===5?' Bimbingan selesai.':''),'?page=bimbingan-detail&id='.(int)$r['bimbingan_id']);flash('success',$r['nama_bab'].' berhasil di-ACC.'.($n===5?' Bimbingan selesai.':''));}catch(Throwable $e){$conn->rollback();flash('danger','ACC gagal disimpan.');}redirect('?page=dashboard');
}

if($action==='admin_assign_dosen'){
    require_role(['admin']);verify_csrf();$bid=(int)($_POST['bimbingan_id']??0);$did=(int)($_POST['dosen_id']??0);$s=$conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen'");$s->bind_param('i',$did);$s->execute();$ok=$s->get_result()->num_rows>0;$s->close();if($ok){$s=$conn->prepare('SELECT mahasiswa_id FROM bimbingan WHERE id=? LIMIT 1');$s->bind_param('i',$bid);$s->execute();$m=$s->get_result()->fetch_assoc();$s->close();$s=$conn->prepare('UPDATE bimbingan SET dosen_id=? WHERE id=?');$s->bind_param('ii',$did,$bid);$s->execute();$s->close();if($m)notify_user($conn,(int)$m['mahasiswa_id'],'dosen_diubah','Dosen pembimbing untuk bimbingan Anda telah diperbarui.','?page=bimbingan-detail&id='.$bid);flash('success','Dosen pembimbing diperbarui.');}else flash('danger','Dosen tidak valid.');redirect('?page=admin-dashboard');
}

if($action==='admin_bimbingan_status'){
    require_role(['admin']);verify_csrf();$bid=(int)($_POST['bimbingan_id']??0);$status=$_POST['status']??'';require_once __DIR__.'/src/controllers/BimbinganController.php';$ctrl=new BimbinganController($conn);$r=$ctrl->updateStatus($bid,$status);if($r['success']){$s=$conn->prepare('SELECT mahasiswa_id FROM bimbingan WHERE id=? LIMIT 1');$s->bind_param('i',$bid);$s->execute();$m=$s->get_result()->fetch_assoc();$s->close();if($m)notify_user($conn,(int)$m['mahasiswa_id'],'status_bimbingan','Status bimbingan Anda telah diperbarui menjadi: '.$status.'.','?page=bimbingan-detail&id='.$bid);}flash($r['success']?'success':'danger',$r['success']?'Status bimbingan diperbarui.':'Gagal memperbarui status.');redirect('?page=admin-dashboard');
}

if($action==='admin_create_dosen'){
    require_role(['admin']);verify_csrf();
    $username=trim($_POST['username']??'');$email=trim($_POST['email']??'');$nama=trim($_POST['nama_lengkap']??'');$telp=trim($_POST['no_telp']??'');$password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
    $_SESSION['old_dosen_form']=['username'=>$username,'email'=>$email,'nama_lengkap'=>$nama,'no_telp'=>$telp];
    $formError=null;
    if($nama===''||strlen($nama)>150) $formError='Nama lengkap wajib diisi (maksimal 150 karakter).';
    elseif(!preg_match('/^[A-Za-z0-9._-]{3,50}$/',$username)) $formError='Username 3–50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau strip.';
    elseif(strlen($email)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)) $formError='Alamat email tidak valid.';
    elseif($telp!==''&&!preg_match('/^[0-9+\-\s()]{5,15}$/',$telp)) $formError='No. telepon tidak valid.';
    elseif(strlen($password)<8||strlen($password)>72) $formError='Password harus 8–72 karakter.';
    elseif($password!==$confirm) $formError='Konfirmasi password tidak cocok.';
    if($formError!==null){flash('danger',$formError);redirect('?page=admin-dashboard#tambah-dosen');}
    require_once __DIR__.'/src/controllers/UserController.php';$ctrl=new UserController($conn);$r=$ctrl->create($username,$email,$nama,$password,'dosen',$telp);
    if(!empty($r['success'])){
        unset($_SESSION['old_dosen_form']);
        $mailed=send_user_email($conn,(int)$r['id'],'Akun dosen MyThesis dibuat','Akun dosen Anda telah dibuat','Administrator telah membuatkan akun dosen untuk Anda di MyThesis. Username Anda: '.$username.'. Password awal disampaikan terpisah oleh administrator. Setelah masuk, segera ubah password Anda melalui menu profil.','?page=login','Masuk ke MyThesis');
        flash('success','Akun dosen "'.$nama.'" berhasil dibuat.'.($mailed?' Email pemberitahuan telah dikirim.':' Email pemberitahuan tidak terkirim, sampaikan username dan password awal langsung kepada dosen.'));
    }else{
        flash('danger',($r['error']??'')==='Username atau email sudah terdaftar.'?'Username atau email sudah terdaftar.':'Akun dosen gagal dibuat. Silakan coba lagi.');
    }
    redirect('?page=admin-dashboard#tambah-dosen');
}

if($action==='admin_delete_user'){
    require_role(['admin']);verify_csrf();$target=(int)($_POST['user_id']??0);
    if($target<1){flash('danger','Akun yang akan dihapus tidak valid.');redirect('?page=admin-dashboard');}
    if($target===(int)$_SESSION['user']['id']){flash('danger','Akun admin yang sedang digunakan tidak dapat dihapus dari panel ini.');redirect('?page=admin-dashboard');}
    $s=$conn->prepare('SELECT id,role,nama_lengkap,profile_photo FROM users WHERE id=? LIMIT 1');$s->bind_param('i',$target);$s->execute();$account=$s->get_result()->fetch_assoc();$s->close();
    if(!$account){flash('danger','Akun tidak ditemukan.');redirect('?page=admin-dashboard');}
    $files=[];
    if(!empty($account['profile_photo']))$files[]=$account['profile_photo'];
    $s=$conn->prepare('SELECT file_path FROM bab_skripsi WHERE bimbingan_id IN (SELECT id FROM bimbingan WHERE mahasiswa_id=? OR dosen_id=?)');
    $s->bind_param('ii',$target,$target);$s->execute();$rs=$s->get_result();while($row=$rs->fetch_assoc()){if(!empty($row['file_path']))$files[]=$row['file_path'];}$s->close();
    $s=$conn->prepare('SELECT r.file_path FROM revisi r JOIN bab_skripsi bs ON bs.id=r.bab_id JOIN bimbingan b ON b.id=bs.bimbingan_id WHERE b.mahasiswa_id=? OR b.dosen_id=?');
    $s->bind_param('ii',$target,$target);$s->execute();$rs=$s->get_result();while($row=$rs->fetch_assoc()){if(!empty($row['file_path']))$files[]=$row['file_path'];}$s->close();
    $s=$conn->prepare('SELECT file_lampiran FROM konsultasi WHERE bimbingan_id IN (SELECT id FROM bimbingan WHERE mahasiswa_id=? OR dosen_id=?)');
    $s->bind_param('ii',$target,$target);$s->execute();$rs=$s->get_result();while($row=$rs->fetch_assoc()){if(!empty($row['file_lampiran']))$files[]=$row['file_lampiran'];}$s->close();
    $conn->begin_transaction();try{$s=$conn->prepare('DELETE FROM users WHERE id=?');$s->bind_param('i',$target);if(!$s->execute())throw new Exception();if($s->affected_rows!==1)throw new Exception();$s->close();$conn->commit();
        $base=realpath(__DIR__.'/uploads');if($base){foreach(array_unique($files) as $path){if(strpos($path,'uploads/')!==0)continue;$real=realpath(__DIR__.'/'.$path);if($real&&strpos($real,$base.DIRECTORY_SEPARATOR)===0)@unlink($real);}}
        flash('success','Akun '.$account['nama_lengkap'].' berhasil dihapus.');
    }catch(Throwable $e){$conn->rollback();flash('danger','Akun gagal dihapus. Data tidak diubah.');}
    redirect('?page=admin-dashboard');
}

if($action==='mark_all_notifications_read'){
    require_login();verify_csrf();$uid=(int)$_SESSION['user']['id'];$s=$conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE user_id=? AND dibaca=0');if($s){$s->bind_param('i',$uid);$s->execute();$s->close();}redirect('?page=notifications');
}

if($action==='notification_read'){
    require_login();verify_csrf();$nid=(int)($_POST['notification_id']??0);$uid=(int)$_SESSION['user']['id'];$s=$conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE id=? AND user_id=?');$s->bind_param('ii',$nid,$uid);$s->execute();$s->close();redirect('?page=notifications');
}


if($action==='forgot_password'){
    verify_csrf();
    $email=trim($_POST['email']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('danger','Masukkan alamat email yang valid.');redirect('?page=forgot-password');}
    $rlE=rl_key($email);$rlI=rl_key(client_ip());
    if(rl_count($conn,'forgot',$rlE,900)>=3||rl_count($conn,'forgot_ip',$rlI,900)>=10){flash('success','Jika email terdaftar, instruksi reset password telah dikirim. Periksa inbox dan folder spam.');redirect('?page=forgot-password');}
    rl_hit($conn,'forgot',$rlE);rl_hit($conn,'forgot_ip',$rlI);
    $s=$conn->prepare('SELECT id,nama_lengkap FROM users WHERE email=? LIMIT 1');$s->bind_param('s',$email);$s->execute();$u=$s->get_result()->fetch_assoc();$s->close();
    if($u){
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $s=$conn->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');$s->bind_param('i',$u['id']);$s->execute();$s->close();
        $s=$conn->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))');$s->bind_param('is',$u['id'],$hash);
        if($s->execute()){
            $resetLink='?page=reset-password&token='.rawurlencode($token);
            send_user_email($conn,(int)$u['id'],'Reset Password MyThesis','Permintaan reset password','Kami menerima permintaan untuk mengatur ulang password akun Anda. Tautan reset berlaku selama 1 jam. Jika Anda tidak meminta reset password, abaikan email ini.',$resetLink,'Reset Password');
        }
        $s->close();
    }
    flash('success','Jika email terdaftar, instruksi reset password telah dikirim. Periksa inbox dan folder spam.');
    redirect('?page=forgot-password');
}

if($action==='reset_password'){
    verify_csrf();
    $token=$_POST['token']??'';$new=$_POST['new_password']??'';$confirm=$_POST['new_password_confirm']??'';
    if(!preg_match('/^[a-f0-9]{64}$/',$token)||strlen($new)<8||$new!==$confirm){flash('danger','Token reset tidak valid atau password tidak memenuhi ketentuan.');redirect('?page=reset-password&token='.rawurlencode($token));}
    $hash=hash('sha256',$token);
    $s=$conn->prepare('SELECT id,user_id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1');$s->bind_param('s',$hash);$s->execute();$reset=$s->get_result()->fetch_assoc();$s->close();
    if(!$reset){flash('danger','Tautan reset password tidak valid atau sudah kedaluwarsa.');redirect('?page=forgot-password');}
    $newHash=password_hash($new,PASSWORD_DEFAULT);
    $conn->begin_transaction();
    try{
        $s=$conn->prepare('UPDATE users SET password=? WHERE id=?');$s->bind_param('si',$newHash,$reset['user_id']);if(!$s->execute())throw new Exception();$s->close();
        $s=$conn->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?');$s->bind_param('i',$reset['id']);if(!$s->execute())throw new Exception();$s->close();
        $conn->commit();
        send_user_email($conn,(int)$reset['user_id'],'Password MyThesis berhasil diubah','Password berhasil diubah','Password akun Anda telah berhasil diubah melalui proses reset. Jika Anda tidak melakukan perubahan ini, segera hubungi administrator.','?page=login','Masuk ke MyThesis');
        flash('success','Password berhasil diubah. Silakan login dengan password baru.');
    }catch(Throwable $e){$conn->rollback();flash('danger','Password gagal diubah. Silakan coba lagi.');}
    redirect('?page=login');
}

$routes=['home'=>'home.php','login'=>'login.php','register'=>'register.php','forgot-password'=>'forgot-password.php','reset-password'=>'reset-password.php','dashboard'=>'dashboard.php','profile'=>'profile.php','change-password'=>'change-password.php','bimbingan-detail'=>'bimbingan-detail.php','admin-dashboard'=>'admin-dashboard.php','notifications'=>'notifications.php','konsultasi'=>'konsultasi.php'];
if(!isset($routes[$page]))$page='home';

$viewFile=__DIR__.'/src/views/'.$routes[$page];
$viewTitle='MyThesis';
$pageTitles=['home'=>'Beranda','login'=>'Masuk','register'=>'Pendaftaran','forgot-password'=>'Lupa Password','reset-password'=>'Reset Password','dashboard'=>'Dashboard','profile'=>'Profil','change-password'=>'Ubah Password','bimbingan-detail'=>'Detail Bimbingan','admin-dashboard'=>'Administrasi Bimbingan','notifications'=>'Notifikasi','konsultasi'=>'Konsultasi'];
if(isset($pageTitles[$page])) $viewTitle=$pageTitles[$page];
$user=$_SESSION['user']??null;
$unreadNotificationCount=0;
if($user){
    $uidNav=(int)$user['id'];
    $sNav=$conn->prepare('SELECT COUNT(*) total FROM notifikasi WHERE user_id=? AND dibaca=0');
    if($sNav){$sNav->bind_param('i',$uidNav);$sNav->execute();$unreadNotificationCount=(int)($sNav->get_result()->fetch_assoc()['total']??0);$sNav->close();}
}
$assetVersion='20260918';
$flash=take_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="MyThesis - Platform bimbingan skripsi mahasiswa dan dosen.">
    <title><?=e($viewTitle)?> | MyThesis</title>
    <link rel="icon" type="image/png" href="logo.png?v=<?=$assetVersion?>">
    <link rel="apple-touch-icon" href="logo.png?v=<?=$assetVersion?>">
    <link rel="stylesheet" href="public/css/style.css?v=<?=$assetVersion?>">
</head>
<body>
<header class="navbar">
    <div class="container nav-inner">
        <a class="brand" href="?page=home">MyThesis</a>
        <nav aria-label="Navigasi utama">
            <ul class="nav-menu">
                <li><a href="?page=home">Beranda</a></li>
                <?php if($user): ?>
                    <li><a href="?page=dashboard">Dashboard</a></li>
                    <?php if(($user['role']??'')==='mahasiswa'): ?>
                        <li><a href="?page=konsultasi">Konsultasi</a></li>
                    <?php endif; ?>
                    <?php if(($user['role']??'')==='admin'): ?>
                        <li><a href="?page=admin-dashboard">Administrasi</a></li>
                    <?php endif; ?>
                    <li><a class="notification-nav" href="?page=notifications">Notifikasi<?php if($unreadNotificationCount>0): ?><span class="notification-count"><?=e($unreadNotificationCount>99?'99+':$unreadNotificationCount)?></span><?php endif; ?></a></li>
                    <li><a href="?page=profile">Profil</a></li>
                    <li>
                        <form class="inline-form" method="post" action="?page=<?=e($page)?>">
                            <input type="hidden" name="action" value="logout">
                            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                            <button class="nav-link" type="submit">Keluar</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="?page=login">Masuk</a></li>
                    <li><a href="?page=register">Daftar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<?php if(!empty($_SESSION['impersonator'])): $imp=$_SESSION['impersonator']; $impMinutes=max(1,(int)ceil(((int)($imp['expires']??0)-time())/60)); ?>
<div class="impersonation-banner" role="status">
    <div class="container">
        <span>Anda sedang login sebagai <strong><?=e($user['nama_lengkap']??'')?></strong> (mahasiswa) &middot; mode <strong><?=e(($imp['mode']??'view')==='act'?'login penuh':'lihat saja')?></strong> &middot; akun asli: <?=e($imp['nama']??'')?> &middot; berakhir dalam <?=e($impMinutes)?> menit. Semua akses tercatat.</span>
        <form class="inline-form" method="post" action="?page=<?=e($page)?>">
            <input type="hidden" name="action" value="impersonate_stop">
            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
            <button class="btn btn-secondary" type="submit">Kembali ke akun saya</button>
        </form>
    </div>
</div>
<?php endif; ?>

<main class="container">
    <?php if($flash): ?>
        <div class="alert alert-<?=e($flash['type'])?>" role="alert"><?=e($flash['message'])?></div>
    <?php endif; ?>
    <?php include $viewFile; ?>
</main>

<footer class="footer">
    <small>MyThesis &mdash; Platform Bimbingan Skripsi</small>
</footer>
</body>
</html>
