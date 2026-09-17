<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/src/helpers/Functions.php';

function redirect(string $url): void { header('Location: '.$url); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']??'')) { http_response_code(419); exit('Permintaan tidak valid. Silakan muat ulang halaman.'); } }
function require_login(): void { if (empty($_SESSION['user'])) redirect('?page=login'); }
function require_role(array $roles): void { require_login(); if (!in_array($_SESSION['user']['role']??'', $roles, true)) { http_response_code(403); exit('Akses ditolak.'); } }
function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function notify_user(mysqli $conn,int $userId,string $type,string $message,?string $link=null): void { $s=$conn->prepare('INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,?,?,?)'); if($s){$s->bind_param('isss',$userId,$type,$message,$link);$s->execute();$s->close();} }
function chapter_number(string $name): int { return preg_match('/^Bab ([1-5])$/', $name, $m) ? (int)$m[1] : 0; }
function latest_chapter(mysqli $conn,int $bid,string $name): ?array { $s=$conn->prepare('SELECT id,nama_bab,versi,status,file_path FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=? ORDER BY versi DESC,id DESC LIMIT 1'); $s->bind_param('is',$bid,$name); $s->execute(); $row=$s->get_result()->fetch_assoc(); $s->close(); return $row?:null; }

$page=$_GET['page']??'home';
$action=$_POST['action']??null;

if($action==='logout'){
    verify_csrf(); $_SESSION=[];
    if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
    session_destroy(); redirect('?page=home');
}

if($action==='login'){
    verify_csrf(); $identity=trim($_POST['identity']??''); $password=$_POST['password']??'';
    if($identity===''||$password===''){flash('danger','Username/email dan password wajib diisi.');redirect('?page=login');}
    $s=$conn->prepare('SELECT id,username,email,password,role,nama_lengkap,no_telp FROM users WHERE username=? OR email=? LIMIT 1');
    $s->bind_param('ss',$identity,$identity);$s->execute();$user=$s->get_result()->fetch_assoc();$s->close();
    if(!$user||!password_verify($password,$user['password'])){flash('danger','Username/email atau password salah.');redirect('?page=login');}
    unset($user['password']); $_SESSION['user']=$user; session_regenerate_id(true); redirect('?page=dashboard');
}

if($action==='register'){
    verify_csrf(); $username=trim($_POST['username']??'');$email=trim($_POST['email']??'');$nama=trim($_POST['nama_lengkap']??'');$password=$_POST['password']??'';
    if($username===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$nama===''||strlen($password)<8){flash('danger','Data pendaftaran tidak valid. Password minimal 8 karakter.');redirect('?page=register');}
    $hash=password_hash($password,PASSWORD_DEFAULT);$role='mahasiswa';$s=$conn->prepare('INSERT INTO users(username,email,password,role,nama_lengkap) VALUES(?,?,?,?,?)');$s->bind_param('sssss',$username,$email,$hash,$role,$nama);
    if($s->execute()) flash('success','Registrasi berhasil. Silakan login.'); else flash('danger','Registrasi gagal. Username/email mungkin sudah digunakan.');$s->close();redirect('?page=login');
}

if($action==='create_bimbingan'){
    require_role(['mahasiswa']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$dosen=(int)($_POST['dosen_id']??0);$judul=trim($_POST['judul_skripsi']??'');$desc=trim($_POST['deskripsi']??'');
    if($judul===''||$dosen<1){flash('danger','Judul dan dosen wajib dipilih.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT id FROM bimbingan WHERE mahasiswa_id=? AND status='aktif' LIMIT 1");$s->bind_param('i',$uid);$s->execute();$has=$s->get_result()->num_rows>0;$s->close();
    if($has){flash('danger','Anda masih memiliki bimbingan aktif.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen' LIMIT 1");$s->bind_param('i',$dosen);$s->execute();$valid=$s->get_result()->num_rows>0;$s->close();
    if(!$valid){flash('danger','Dosen tidak valid.');redirect('?page=dashboard');}
    require_once __DIR__.'/src/controllers/BimbinganController.php';$ctrl=new BimbinganController($conn);$r=$ctrl->create($uid,$dosen,$judul,$desc);flash($r['success']?'success':'danger',$r['success']?'Bimbingan berhasil dibuat.':'Bimbingan gagal dibuat.');redirect('?page=dashboard');
}

if($action==='upload_bab'){
    require_role(['mahasiswa']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bid=(int)($_POST['bimbingan_id']??0);$name=trim($_POST['nama_bab']??'');$n=chapter_number($name);
    $s=$conn->prepare("SELECT id FROM bimbingan WHERE id=? AND mahasiswa_id=? AND status='aktif'");$s->bind_param('ii',$bid,$uid);$s->execute();$owned=$s->get_result()->num_rows>0;$s->close();
    if(!$owned||$n===0){flash('danger','Bimbingan atau bab tidak valid.');redirect('?page=dashboard');}
    if($n>1){$prev=latest_chapter($conn,$bid,'Bab '.($n-1));if(!$prev||$prev['status']!=='disetujui'){flash('danger','Bab sebelumnya harus mendapat ACC terlebih dahulu.');redirect('?page=dashboard');}}
    $latest=latest_chapter($conn,$bid,$name);if($latest&&in_array($latest['status'],['menunggu_review','disetujui'],true)){flash('danger','Bab ini masih menunggu review atau sudah ACC.');redirect('?page=dashboard');}
    $versi=$latest?(int)$latest['versi']+1:1;$file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']>10*1024*1024){flash('danger','File wajib diunggah dan maksimal 10 MB.');redirect('?page=dashboard');}
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);$allowed=['application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'];if(!isset($allowed[$mime])){flash('danger','Format file harus PDF, DOC, atau DOCX.');redirect('?page=dashboard');}
    $dir=__DIR__.'/uploads/bab';if(!is_dir($dir))mkdir($dir,0750,true);$stored='uploads/bab/'.bin2hex(random_bytes(16)).'.'.$allowed[$mime];$dest=__DIR__.'/'.$stored;if(!move_uploaded_file($file['tmp_name'],$dest)){flash('danger','File gagal disimpan.');redirect('?page=dashboard');}
    $status='menunggu_review';$s=$conn->prepare('INSERT INTO bab_skripsi(bimbingan_id,nama_bab,file_path,versi,status) VALUES(?,?,?,?,?)');$s->bind_param('issis',$bid,$name,$stored,$versi,$status);$ok=$s->execute();$s->close();if(!$ok){@unlink($dest);flash('danger','Data bab gagal disimpan.');redirect('?page=dashboard');}
    $s=$conn->prepare('SELECT dosen_id FROM bimbingan WHERE id=?');$s->bind_param('i',$bid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if($r)notify_user($conn,(int)$r['dosen_id'],'bab_baru','Mahasiswa mengunggah '.$name.'.','?page=bimbingan-detail&id='.$bid);flash('success',$name.' berhasil diunggah dan menunggu review.');redirect('?page=dashboard');
}

if($action==='add_revision'){
    require_role(['dosen']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$babId=(int)($_POST['bab_id']??0);$comment=trim($_POST['komentar']??'');$type=$_POST['tipe_revisi']??'minor';if($comment===''||!in_array($type,['minor','major','kritis'],true)){flash('danger','Komentar dan tipe revisi wajib diisi.');redirect('?page=dashboard');}
    $s=$conn->prepare("SELECT bs.id,bs.nama_bab,b.mahasiswa_id FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id WHERE bs.id=? AND b.dosen_id=? AND b.status='aktif' AND bs.status='menunggu_review' AND bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=bs.bimbingan_id AND x.nama_bab=bs.nama_bab ORDER BY x.versi DESC,x.id DESC LIMIT 1)");$s->bind_param('ii',$babId,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if(!$r){flash('danger','Bab tidak dapat direvisi.');redirect('?page=dashboard');}
    $conn->begin_transaction();try{$s=$conn->prepare('INSERT INTO revisi(bab_id,dosen_id,komentar,tipe_revisi) VALUES(?,?,?,?)');$s->bind_param('iiss',$babId,$uid,$comment,$type);if(!$s->execute())throw new Exception();$s->close();$s=$conn->prepare("UPDATE bab_skripsi SET status='direvisi' WHERE id=?");$s->bind_param('i',$babId);if(!$s->execute())throw new Exception();$s->close();$conn->commit();notify_user($conn,(int)$r['mahasiswa_id'],'revisi_bab','Ada revisi untuk '.$r['nama_bab'].'.','?page=bimbingan-detail&id='.(int)$_POST['bimbingan_id']);flash('success','Revisi berhasil dikirim.');}catch(Throwable $e){$conn->rollback();flash('danger','Revisi gagal disimpan.');}redirect('?page=dashboard');
}

if($action==='approve_bab'){
    require_role(['dosen']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$babId=(int)($_POST['bab_id']??0);
    $s=$conn->prepare("SELECT bs.id,bs.nama_bab,b.id AS bimbingan_id,b.mahasiswa_id,b.status AS bimbingan_status FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id WHERE bs.id=? AND b.dosen_id=? AND b.status='aktif' AND bs.status='menunggu_review' AND bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=bs.bimbingan_id AND x.nama_bab=bs.nama_bab ORDER BY x.versi DESC,x.id DESC LIMIT 1)");$s->bind_param('ii',$babId,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if(!$r){flash('danger','Bab tidak dapat di-ACC.');redirect('?page=dashboard');}
    $n=chapter_number($r['nama_bab']);if($n===0){flash('danger','Nama bab tidak valid.');redirect('?page=dashboard');}if($n>1){$prev=latest_chapter($conn,(int)$r['bimbingan_id'],'Bab '.($n-1));if(!$prev||$prev['status']!=='disetujui'){flash('danger','Bab sebelumnya harus sudah ACC.');redirect('?page=dashboard');}}
    $conn->begin_transaction();try{$s=$conn->prepare("UPDATE bab_skripsi SET status='disetujui' WHERE id=?");$s->bind_param('i',$babId);if(!$s->execute())throw new Exception();$s->close();if($n===5){$s=$conn->prepare("UPDATE bimbingan SET status='selesai' WHERE id=? AND status='aktif'");$s->bind_param('i',$r['bimbingan_id']);if(!$s->execute())throw new Exception();$s->close();}$conn->commit();notify_user($conn,(int)$r['mahasiswa_id'],'bab_disetujui',$r['nama_bab'].' telah mendapat ACC.'.($n===5?' Bimbingan selesai.':''),'?page=bimbingan-detail&id='.(int)$r['bimbingan_id']);flash('success',$r['nama_bab'].' berhasil di-ACC.'.($n===5?' Bimbingan selesai.':''));}catch(Throwable $e){$conn->rollback();flash('danger','ACC gagal disimpan.');}redirect('?page=dashboard');
}

if($action==='admin_assign_dosen'){
    require_role(['admin']);verify_csrf();$bid=(int)($_POST['bimbingan_id']??0);$did=(int)($_POST['dosen_id']??0);$s=$conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen'");$s->bind_param('i',$did);$s->execute();$ok=$s->get_result()->num_rows>0;$s->close();if($ok){$s=$conn->prepare('UPDATE bimbingan SET dosen_id=? WHERE id=?');$s->bind_param('ii',$did,$bid);$s->execute();$s->close();flash('success','Dosen pembimbing diperbarui.');}else flash('danger','Dosen tidak valid.');redirect('?page=admin-dashboard');
}

if($action==='admin_bimbingan_status'){
    require_role(['admin']);verify_csrf();$bid=(int)($_POST['bimbingan_id']??0);$status=$_POST['status']??'';require_once __DIR__.'/src/controllers/BimbinganController.php';$ctrl=new BimbinganController($conn);$r=$ctrl->updateStatus($bid,$status);flash($r['success']?'success':'danger',$r['success']?'Status bimbingan diperbarui.':'Gagal memperbarui status.');redirect('?page=admin-dashboard');
}

if($action==='notification_read'){
    require_login();verify_csrf();$nid=(int)($_POST['notification_id']??0);$uid=(int)$_SESSION['user']['id'];$s=$conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE id=? AND user_id=?');$s->bind_param('ii',$nid,$uid);$s->execute();$s->close();redirect('?page=notifications');
}

$routes=['home'=>'home.php','login'=>'login.php','register'=>'register.php','dashboard'=>'dashboard.php','profile'=>'profile.php','change-password'=>'change-password.php','bimbingan-detail'=>'bimbingan-detail.php','admin-dashboard'=>'admin-dashboard.php','notifications'=>'notifications.php','konsultasi'=>'konsultasi.php'];
if(!isset($routes[$page]))$page='home';
include __DIR__.'/src/views/'.$routes[$page];
