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
    $labels=['pengajuan_judul'=>'Pengajuan Judul','revisi_judul'=>'Revisi Judul','diminta'=>'Menunggu Revisi Judul','diajukan_ulang'=>'Diajukan Ulang','menunggu_review'=>'Menunggu Review','disetujui'=>'Disetujui','ditangguhkan'=>'Ditangguhkan'];
    $class=$map[$status]??'secondary'; $label=$labels[$status]??ucfirst(str_replace('_',' ',$status)); return '<span class="badge badge-'.$class.'">'.e($label).'</span>';
}
function isAuthenticated(): bool { return isset($_SESSION['user']); }
function hasRole(string $role): bool { return isAuthenticated() && ($_SESSION['user']['role']??'')===$role; }
function getCurrentUser(): ?array { return $_SESSION['user']??null; }

if (!function_exists('impersonate_form')) {
    function impersonate_form(int $studentId, string $studentName): string {
        $confirmText = e(json_encode('Buka akun ' . $studentName . '? Akses ini dicatat dan mahasiswa akan mendapat pemberitahuan.', JSON_UNESCAPED_UNICODE));
        return '<form method="post" class="inline-form impersonate-form" onsubmit="return confirm(' . $confirmText . ')">'
            . '<input type="hidden" name="action" value="impersonate_start">'
            . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
            . '<input type="hidden" name="user_id" value="' . (int)$studentId . '">'
            . '<button class="btn btn-secondary" type="submit" name="mode" value="view" title="Melihat tampilan mahasiswa tanpa mengubah data">Lihat sebagai</button> '
            . '<button class="btn btn-warning" type="submit" name="mode" value="act" title="Bisa menguji unggah file. Tindakan tercatat atas nama mahasiswa">Login penuh</button>'
            . '</form>';
    }
}

if (!function_exists('ini_bytes')) {
    function ini_bytes(string $v): int {
        $v = trim($v); if ($v === '') return 0;
        $n = (float)$v; $u = strtolower(substr($v, -1));
        if ($u === 'g') $n *= 1024 * 1024 * 1024; elseif ($u === 'm') $n *= 1024 * 1024; elseif ($u === 'k') $n *= 1024;
        return (int)$n;
    }
}
if (!function_exists('effective_upload_limit')) {
    function effective_upload_limit(): int {
        $limit = 10 * 1024 * 1024;
        foreach (['upload_max_filesize', 'post_max_size'] as $k) { $b = ini_bytes((string)ini_get($k)); if ($b > 0) $limit = min($limit, $b); }
        return $limit;
    }
}
if (!function_exists('format_bytes')) {
    function format_bytes(int $b): string {
        if ($b >= 1048576) return rtrim(rtrim(number_format($b / 1048576, 1, ',', '.'), '0'), ',') . ' MB';
        if ($b >= 1024) return round($b / 1024) . ' KB';
        return $b . ' B';
    }
}
if (!function_exists('upload_error_message')) {
    function upload_error_message(int $code, string $label = 'File'): ?string {
        switch ($code) {
            case UPLOAD_ERR_OK: return null;
            case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE:
                return $label . ' terlalu besar untuk server (batas server ' . ini_get('upload_max_filesize') . '). Kecilkan ukuran file atau hubungi administrator.';
            case UPLOAD_ERR_PARTIAL: return 'Unggahan terputus sebelum selesai. Periksa koneksi internet lalu coba lagi.';
            case UPLOAD_ERR_NO_FILE: return 'Pilih file terlebih dahulu.';
            case UPLOAD_ERR_NO_TMP_DIR: case UPLOAD_ERR_CANT_WRITE: case UPLOAD_ERR_EXTENSION:
                return 'Server tidak dapat memproses unggahan. Hubungi administrator.';
            default: return $label . ' gagal diunggah. Silakan coba lagi.';
        }
    }
}
if (!function_exists('detect_document_type')) {
    /** Deteksi pdf/doc/docx dari isi file (bukan dari tebakan MIME atau ekstensi). Mengembalikan null bila tidak dikenali. */
    function detect_document_type(string $path, string $originalName): ?string {
        $fh = @fopen($path, 'rb'); if (!$fh) return null;
        $head = (string)fread($fh, 1024); fclose($fh);
        if (strpos($head, '%PDF-') !== false) return 'pdf';
        if (strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0) {
            return strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'doc' ? 'doc' : null;
        }
        if (strncmp($head, "PK\x03\x04", 4) === 0) {
            if (class_exists('ZipArchive')) {
                $z = new ZipArchive();
                if ($z->open($path) !== true) return null;
                $ok = $z->locateName('word/document.xml') !== false; $z->close();
                return $ok ? 'docx' : null;
            }
            return strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'docx' ? 'docx' : null;
        }
        return null;
    }
}
