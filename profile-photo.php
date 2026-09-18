<?php
session_start();
require_once __DIR__.'/config/database.php';

if (empty($_SESSION['user']['id'])) {
    http_response_code(403);
    exit;
}

$uid=(int)$_SESSION['user']['id'];
$s=$conn->prepare('SELECT profile_photo FROM users WHERE id=? LIMIT 1');
$s->bind_param('i',$uid);
$s->execute();
$row=$s->get_result()->fetch_assoc();
$s->close();

$relative=$row['profile_photo']??'';
if ($relative==='' || strpos($relative,'uploads/profil/')!==0) {
    http_response_code(404);
    exit;
}

$base=realpath(__DIR__.'/uploads/profil');
$file=realpath(__DIR__.'/'.$relative);
if (!$base || !$file || strpos($file,$base.DIRECTORY_SEPARATOR)!==0 || !is_file($file)) {
    http_response_code(404);
    exit;
}

$finfo=new finfo(FILEINFO_MIME_TYPE);
$mime=$finfo->file($file);
$allowed=['image/jpeg','image/png','image/webp'];
if (!in_array($mime,$allowed,true)) {
    http_response_code(403);
    exit;
}

header('Content-Type: '.$mime);
header('Content-Length: '.filesize($file));
header('Cache-Control: private, max-age=3600');
readfile($file);
