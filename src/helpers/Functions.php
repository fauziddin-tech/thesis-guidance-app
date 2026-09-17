<?php

if (!function_exists('e')) { function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
function sanitize($input): string { return trim((string)$input); }
function isValidEmail($email): bool { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
function generateToken(int $length=32): string { return bin2hex(random_bytes(max(1,(int)($length/2)))); }
function formatDate($date, string $format='d M Y'): string { return $date ? date($format, strtotime($date)) : '-'; }
function formatDateTime($datetime): string { return formatDate($datetime, 'd M Y H:i'); }
function getFileExtension($filename): string { return strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION)); }
function formatFileSize($bytes): string {
    $units=['B','KB','MB','GB']; $bytes=max(0,(int)$bytes); $pow=$bytes?min((int)floor(log($bytes,1024)),count($units)-1):0; return round($bytes/(1024**$pow),2).' '.$units[$pow];
}
function getStatusBadge(string $status): string {
    $map=['aktif'=>'success','selesai'=>'primary','ditangguhkan'=>'warning','draft'=>'secondary','menunggu_review'=>'info','direvisi'=>'warning','disetujui'=>'success','pending'=>'secondary','diterima'=>'success','ditolak'=>'danger'];
    $labels=['menunggu_review'=>'Menunggu Review','disetujui'=>'Disetujui','ditangguhkan'=>'Ditangguhkan'];
    $class=$map[$status]??'secondary'; $label=$labels[$status]??ucfirst(str_replace('_',' ',$status)); return '<span class="badge badge-'.$class.'">'.e($label).'</span>';
}
function isAuthenticated(): bool { return isset($_SESSION['user']); }
function hasRole(string $role): bool { return isAuthenticated() && ($_SESSION['user']['role']??'')===$role; }
function getCurrentUser(): ?array { return $_SESSION['user']??null; }
