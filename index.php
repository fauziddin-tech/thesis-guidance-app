<?php
session_start();
require_once __DIR__ . '/config/database.php';
$page = $_GET['page'] ?? 'home';
$action = $_POST['action'] ?? null;
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Permintaan tidak valid. Silakan muat ulang halaman.'); } }
function require_login(): void { if (empty($_SESSION['user'])) redirect('?page=login'); }
function require_role(array $roles): void { require_login(); if (!in_array($_SESSION['user']['role'], $roles, true)) { http_response_code(403); exit('Akses ditolak.'); } }
function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function notify_user(mysqli $conn,int $userId,string $type,string $message,?string $link=null): void {
    $stmt=$conn->prepare('INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,?,?,?)');
    $stmt->bind_param('isss',$userId,$type,$message,$link); $stmt->execute(); $stmt->close();
}

if ($action==='logout') { verify_csrf(); $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy(); redirect('?page=home'); }

if ($action==='login') {
 verify_csrf(); $identity=trim($_POST['identity']??''); $password=$_POST['password']??'';
 if($identity===''||$password===''){flash('danger','Username/email dan password wajib diisi.');redirect('?page=login');}
 $stmt=$conn->prepare('SELECT id,username,email,password,role,nama_lengkap,no_telp FROM users WHERE username=? OR email=? LIMIT 1'); $stmt->bind_param('ss',$identity,$identity); $stmt->execute(); $user=$stmt->get_result()->fetch_assoc(); $stmt->close();
 if(!$user||!password_verify($password,$user['password'])){flash('danger','Username/email atau password salah.');redirect('?page=login');}
 session_regenerate_id(true); unset($user['password']); $_SESSION['user']=$user; csrf_token(); redirect('?page=dashboard');
}

if ($action==='register') {
 verify_csrf(); $username=trim($_POST['username']??''); $email=trim($_POST['email']??''); $nama=trim($_POST['nama_lengkap']??''); $password=$_POST['password']??''; $confirm=$_POST['confirm_password']??''; $telp=trim($_POST['no_telp']??'');
 if($username===''||$email===''||$nama===''||$password===''){flash('danger','Semua field wajib diisi.');redirect('?page=register');}
 if(!preg_match('/^[A-Za-z0-9_]{3,100}$/',$username)){flash('danger','Username minimal 3 karakter dan hanya boleh huruf, angka, underscore.');redirect('?page=register');}
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('danger','Format email tidak valid.');redirect('?page=register');}
 if(strlen($password)<8){flash('danger','Password minimal 8 karakter.');redirect('?page=register');}
 if($password!==$confirm){flash('danger','Konfirmasi password tidak cocok.');redirect('?page=register');}
 $stmt=$conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');$stmt->bind_param('ss',$username,$email);$stmt->execute();$exists=$stmt->get_result()->num_rows;$stmt->close();
 if($exists){flash('danger','Username atau email sudah terdaftar.');redirect('?page=register');}
 $hash=password_hash($password,PASSWORD_DEFAULT);$role='mahasiswa';$stmt=$conn->prepare('INSERT INTO users(username,email,password,role,nama_lengkap,no_telp) VALUES(?,?,?,?,?,?)');$stmt->bind_param('ssssss',$username,$email,$hash,$role,$nama,$telp);$ok=$stmt->execute();$stmt->close();flash($ok?'success':'danger',$ok?'Pendaftaran berhasil. Silakan login.':'Pendaftaran gagal.');redirect('?page=login');
}

if($action==='create_bimbingan'){
 require_role(['mahasiswa']);verify_csrf();$dosen_id=(int)($_POST['dosen_id']??0);$judul=trim($_POST['judul_skripsi']??'');$deskripsi=trim($_POST['deskripsi']??'');$uid=(int)$_SESSION['user']['id'];
 if(!$dosen_id||$judul===''){flash('danger','Dosen dan judul skripsi wajib diisi.');redirect('?page=dashboard');}
 $stmt=$conn->prepare("SELECT id FROM users WHERE id=? AND role='dosen' LIMIT 1");$stmt->bind_param('i',$dosen_id);$stmt->execute();$valid=$stmt->get_result()->num_rows;$stmt->close();if(!$valid){flash('danger','Dosen tidak valid.');redirect('?page=dashboard');}
 $stmt=$conn->prepare('INSERT INTO bimbingan(mahasiswa_id,dosen_id,judul_skripsi,deskripsi) VALUES(?,?,?,?)');$stmt->bind_param('iiss',$uid,$dosen_id,$judul,$deskripsi);$ok=$stmt->execute();$bid=$conn->insert_id;$stmt->close();
 if($ok) notify_user($conn,$dosen_id,'bimbingan_baru','Ada pengajuan bimbingan baru dari '.($_SESSION['user']['nama_lengkap']??'mahasiswa').'.','?page=bimbingan-detail&id='.$bid);
 flash($ok?'success':'danger',$ok?'Bimbingan berhasil dibuat.':'Bimbingan gagal dibuat.');redirect('?page=dashboard');
}

if($action==='upload_bab'){
 require_role(['mahasiswa']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bid=(int)($_POST['bimbingan_id']??0);$nama=trim($_POST['nama_bab']??'');$file=$_FILES['file_bab']??null;
 if(!$bid||$nama===''||!$file||$file['error']!==UPLOAD_ERR_OK){flash('danger','Data upload belum lengkap.');redirect('?page=dashboard');}
 $stmt=$conn->prepare("SELECT b.id,b.dosen_id FROM bimbingan b WHERE b.id=? AND b.mahasiswa_id=? AND b.status='aktif'");$stmt->bind_param('ii',$bid,$uid);$stmt->execute();$bim=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$bim){flash('danger','Bimbingan tidak valid.');redirect('?page=dashboard');}
 if($file['size']>10*1024*1024){flash('danger','Ukuran file maksimal 10 MB.');redirect('?page=dashboard');}
 $finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$file['tmp_name']);finfo_close($finfo);$allowed=['application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'];if(!isset($allowed[$mime])){flash('danger','Format file harus PDF, DOC, atau DOCX.');redirect('?page=dashboard');}
 $dir=__DIR__.'/uploads/bab';if(!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)){flash('danger','Folder upload tidak tersedia.');redirect('?page=dashboard');}$name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];$path=$dir.'/'.$name;if(!move_uploaded_file($file['tmp_name'],$path)){flash('danger','File gagal disimpan.');redirect('?page=dashboard');}
 $stmt=$conn->prepare('SELECT COALESCE(MAX(versi),0)+1 v FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=?');$stmt->bind_param('is',$bid,$nama);$stmt->execute();$versi=(int)$stmt->get_result()->fetch_assoc()['v'];$stmt->close();$relative='uploads/bab/'.$name;$status='menunggu_review';$stmt=$conn->prepare('INSERT INTO bab_skripsi(bimbingan_id,nama_bab,file_path,versi,status) VALUES(?,?,?,?,?)');$stmt->bind_param('issis',$bid,$nama,$relative,$versi,$status);$ok=$stmt->execute();$babId=$conn->insert_id;$stmt->close();if(!$ok)@unlink($path);
 if($ok) notify_user($conn,(int)$bim['dosen_id'],'bab_baru','Mahasiswa mengunggah '.$nama.' versi '.$versi.' untuk direview.','?page=bimbingan-detail&id='.$bid);
 flash($ok?'success':'danger',$ok?'Bab berhasil diunggah dan menunggu review dosen.':'Data file gagal disimpan.');redirect('?page=dashboard');
}

if($action==='add_revision'){
 require_role(['dosen']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bab=(int)($_POST['bab_id']??0);$komentar=trim($_POST['komentar']??'');$tipe=$_POST['tipe_revisi']??'minor';if(!$bab||$komentar===''){flash('danger','Bab dan komentar wajib diisi.');redirect('?page=dashboard');}if(!in_array($tipe,['minor','major','kritis'],true))$tipe='minor';
 $stmt=$conn->prepare('SELECT bs.id,bs.bimbingan_id,b.mahasiswa_id FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id WHERE bs.id=? AND b.dosen_id=?');$stmt->bind_param('ii',$bab,$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$row){flash('danger','Bab tidak berada dalam bimbingan Anda.');redirect('?page=dashboard');}
 $stmt=$conn->prepare('INSERT INTO revisi(bab_id,dosen_id,komentar,tipe_revisi) VALUES(?,?,?,?)');$stmt->bind_param('iiss',$bab,$uid,$komentar,$tipe);$ok=$stmt->execute();$stmt->close();if($ok){$stmt=$conn->prepare("UPDATE bab_skripsi SET status='direvisi' WHERE id=?");$stmt->bind_param('i',$bab);$stmt->execute();$stmt->close();notify_user($conn,(int)$row['mahasiswa_id'],'revisi_baru','Dosen memberikan revisi '.$tipe.' pada bab skripsi Anda.','?page=bimbingan-detail&id='.(int)$row['bimbingan_id']);}flash($ok?'success':'danger',$ok?'Revisi berhasil dikirim.':'Revisi gagal dikirim.');redirect('?page=dashboard');
}

if($action==='approve_bab'){
 require_role(['dosen']);verify_csrf();$uid=(int)$_SESSION['user']['id'];$bab=(int)($_POST['bab_id']??0);
 $stmt=$conn->prepare('SELECT bs.bimbingan_id,b.mahasiswa_id,bs.nama_bab,bs.versi FROM bab_skripsi bs JOIN bimbingan b ON b.id=bs.bimbingan_id WHERE bs.id=? AND b.dosen_id=?');$stmt->bind_param('ii',$bab,$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
 if(!$row){flash('danger','Bab tidak berada dalam bimbingan Anda.');redirect('?page=dashboard');}
 $stmt=$conn->prepare("UPDATE bab_skripsi SET status='disetujui' WHERE id=?");$stmt->bind_param('i',$bab);$ok=$stmt->execute();$stmt->close();
 if($ok) notify_user($conn,(int)$row['mahasiswa_id'],'bab_disetujui',$row['nama_bab'].' versi '.$row['versi'].' telah disetujui dosen.','?page=bimbingan-detail&id='.(int)$row['bimbingan_id']);
 flash($ok?'success':'danger',$ok?'Bab berhasil disetujui.':'Bab gagal disetujui.');redirect('?page=bimbingan-detail&id='.(int)$row['bimbingan_id']);
}

if($action==='admin_assign_dosen'){
 require_role(['admin']);verify_csrf();require_once __DIR__.'/src/controllers/AdminController.php';$bid=(int)($_POST['bimbingan_id']??0);$did=(int)($_POST['dosen_id']??0);$ctrl=new AdminController($conn);
 $stmt=$conn->prepare('SELECT mahasiswa_id FROM bimbingan WHERE id=?');$stmt->bind_param('i',$bid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();$ok=$row && $ctrl->assignDosen($bid,$did);
 if($ok) notify_user($conn,(int)$row['mahasiswa_id'],'pembimbing_ditetapkan','Dosen pembimbing bimbingan Anda telah diperbarui.','?page=bimbingan-detail&id='.$bid);
 flash($ok?'success':'danger',$ok?'Dosen pembimbing berhasil diperbarui.':'Gagal memperbarui dosen pembimbing.');redirect('?page=admin-dashboard');
}

if($action==='admin_bimbingan_status'){
 require_role(['admin']);verify_csrf();require_once __DIR__.'/src/controllers/AdminController.php';$bid=(int)($_POST['bimbingan_id']??0);$status=$_POST['status']??'';$ctrl=new AdminController($conn);$ok=$ctrl->updateBimbinganStatus($bid,$status);flash($ok?'success':'danger',$ok?'Status bimbingan diperbarui.':'Status tidak valid.');redirect('?page=admin-dashboard');
}

if($action==='mark_notification_read'){
 require_login();verify_csrf();$id=(int)($_POST['notification_id']??0);$uid=(int)$_SESSION['user']['id'];$stmt=$conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE id=? AND user_id=?');$stmt->bind_param('ii',$id,$uid);$stmt->execute();$stmt->close();redirect('?page=notifications');
}

if($action==='mark_all_notifications_read'){
 require_login();verify_csrf();$uid=(int)$_SESSION['user']['id'];$stmt=$conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE user_id=?');$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();redirect('?page=notifications');
}

$flash=take_flash();
if(in_array($page,['dashboard','profile','change-password','bimbingan-detail','admin-dashboard','notifications'],true))require_login();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MyThesis - Bimbingan Skripsi</title><link rel="stylesheet" href="public/css/style.css"></head><body>
<nav class="navbar"><div class="container nav-inner"><a class="brand" href="?page=home">MyThesis</a><ul class="nav-menu"><li><a href="?page=home">Beranda</a></li><?php if(isset($_SESSION['user'])): ?><li><a href="?page=dashboard">Dashboard</a></li><li><a href="?page=notifications">Notifikasi<?php $uid=(int)$_SESSION['user']['id'];$stmtN=$conn->prepare('SELECT COUNT(*) n FROM notifikasi WHERE user_id=? AND dibaca=0');$stmtN->bind_param('i',$uid);$stmtN->execute();$unread=(int)$stmtN->get_result()->fetch_assoc()['n'];$stmtN->close();if($unread): ?> <span class="badge badge-danger"><?=$unread?></span><?php endif;?></a></li><li><a href="?page=profile">Profil</a></li><li><form method="post" class="inline-form"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button class="nav-link" type="submit">Logout</button></form></li><?php else: ?><li><a href="?page=login">Login</a></li><li><a href="?page=register">Daftar</a></li><?php endif;?></ul></div></nav>
<main class="container"><?php if($flash):?><div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif;?><?php switch($page){case 'login':include __DIR__.'/src/views/login.php';break;case 'register':include __DIR__.'/src/views/register.php';break;case 'dashboard':if(($_SESSION['user']['role']??'')==='admin'){include __DIR__.'/src/views/admin-dashboard.php';}else{include __DIR__.'/src/views/dashboard.php';}break;case 'admin-dashboard':require_role(['admin']);include __DIR__.'/src/views/admin-dashboard.php';break;case 'profile':include __DIR__.'/src/views/profile.php';break;case 'change-password':include __DIR__.'/src/views/change-password.php';break;case 'bimbingan-detail':include __DIR__.'/src/views/bimbingan-detail.php';break;case 'notifications':include __DIR__.'/src/views/notifications.php';break;default:include __DIR__.'/src/views/home.php';}?></main><footer><p>&copy; <?=date('Y')?> MyThesis</p></footer><script src="public/js/script.js"></script></body></html>
