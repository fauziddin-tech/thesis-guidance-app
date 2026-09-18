<?php
session_start();
require_once __DIR__.'/config/database.php';

if (empty($_SESSION['user'])) {
    http_response_code(401);
    exit('Silakan login terlebih dahulu.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    exit('File tidak valid.');
}

$user = $_SESSION['user'];
$uid = (int)$user['id'];
$role = $user['role'] ?? '';

$revisionId=(int)($_GET['revisi']??0);
if($revisionId>0){
    $sql="SELECT r.file_path,r.bab_id,bs.nama_bab,bs.versi,b.mahasiswa_id,b.dosen_id
          FROM revisi r JOIN bab_skripsi bs ON bs.id=r.bab_id JOIN bimbingan b ON b.id=bs.bimbingan_id
          WHERE r.id=? LIMIT 1";
    $stmt=$conn->prepare($sql);if(!$stmt){http_response_code(500);exit('Gagal memproses permintaan.');}
    $stmt->bind_param('i',$revisionId);$stmt->execute();$file=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$file||empty($file['file_path'])){http_response_code(404);exit('File revisi tidak ditemukan.');}
    $allowed=$role==='admin'||($role==='mahasiswa'&&$uid===(int)$file['mahasiswa_id'])||($role==='dosen'&&$uid===(int)$file['dosen_id']);
    if(!$allowed){http_response_code(403);exit('Akses ditolak.');}
    $relative=ltrim((string)$file['file_path'],'/');$baseDir=realpath(__DIR__.'/uploads');$absolute=realpath(__DIR__.'/'.$relative);
    if(!$baseDir||!$absolute||!is_file($absolute)){http_response_code(404);exit('File tidak tersedia.');}
    $basePrefix=rtrim($baseDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if(strncmp($absolute,$basePrefix,strlen($basePrefix))!==0){http_response_code(400);exit('Lokasi file tidak valid.');}
    $mime='application/octet-stream';$finfo=finfo_open(FILEINFO_MIME_TYPE);if($finfo){$detected=finfo_file($finfo,$absolute);if($detected)$mime=$detected;finfo_close($finfo);}
    $extension=strtolower(pathinfo($absolute,PATHINFO_EXTENSION));$mimeMap=['pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if(!isset($mimeMap[$extension])||$mime!==$mimeMap[$extension]){http_response_code(415);exit('Tipe file tidak didukung.');}
    $downloadName='Revisi_'.$file['nama_bab'].'_v'.(int)$file['versi'].'.'.$extension;
    header('Content-Type: '.$mime);header('Content-Length: '.filesize($absolute));header('Content-Disposition: inline; filename="'.str_replace('"','',$downloadName).'"');header('X-Content-Type-Options: nosniff');readfile($absolute);exit;
}

$sql = "SELECT bs.file_path, bs.nama_bab, bs.versi, b.mahasiswa_id, b.dosen_id
        FROM bab_skripsi bs
        JOIN bimbingan b ON b.id = bs.bimbingan_id
        WHERE bs.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit('Gagal memproses permintaan.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$allowed = $role === 'admin'
    || ($role === 'mahasiswa' && $uid === (int)$file['mahasiswa_id'])
    || ($role === 'dosen' && $uid === (int)$file['dosen_id']);
if (!$allowed) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$relative = ltrim((string)$file['file_path'], '/');
$baseDir = realpath(__DIR__.'/uploads');
$absolute = realpath(__DIR__.'/'.$relative);
if (!$baseDir || !$absolute || !is_file($absolute)) {
    http_response_code(404);
    exit('File tidak tersedia.');
}

$basePrefix = rtrim($baseDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
if (strncmp($absolute, $basePrefix, strlen($basePrefix)) !== 0) {
    http_response_code(400);
    exit('Lokasi file tidak valid.');
}

$mime = 'application/octet-stream';
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo) {
    $detected = finfo_file($finfo, $absolute);
    if ($detected) $mime = $detected;
    finfo_close($finfo);
}

$extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
if (!isset($mimeMap[$extension]) || $mime !== $mimeMap[$extension]) {
    http_response_code(415);
    exit('Tipe file tidak didukung.');
}

$downloadName = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$file['nama_bab']).'_v'.(int)$file['versi'].'.'.$extension;
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($absolute));
header('Content-Disposition: inline; filename="'.str_replace('"', '', $downloadName).'"');
header('X-Content-Type-Options: nosniff');
readfile($absolute);
exit;
