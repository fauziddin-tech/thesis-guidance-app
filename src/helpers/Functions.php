<?php

/**
 * Helper functions untuk aplikasi
 */

/**
 * Redirect dengan pesan
 */
function redirect($url, $message = '', $type = 'success') {
    if ($message) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
    header('Location: ' . $url);
    exit();
}

/**
 * Flash message
 */
function flash($key = null) {
    if ($key) {
        if (isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
            unset($_SESSION[$key]);
            return $value;
        }
    }
    return null;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Format tanggal
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Format tanggal dan waktu
 */
function formatDateTime($datetime) {
    return date('d M Y H:i', strtotime($datetime));
}

/**
 * Get status badge
 */
function getStatusBadge($status) {
    $badges = [
        'aktif' => '<span class="badge badge-success">Aktif</span>',
        'selesai' => '<span class="badge badge-primary">Selesai</span>',
        'ditangguhkan' => '<span class="badge badge-warning">Ditangguhkan</span>',
        'draft' => '<span class="badge badge-secondary">Draft</span>',
        'menunggu_review' => '<span class="badge badge-info">Menunggu Review</span>',
        'direvisi' => '<span class="badge badge-warning">Direvisi</span>',
        'disetujui' => '<span class="badge badge-success">Disetujui</span>',
        'pending' => '<span class="badge badge-secondary">Pending</span>',
        'diterima' => '<span class="badge badge-success">Diterima</span>',
        'ditolak' => '<span class="badge badge-danger">Ditolak</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user']);
}

/**
 * Check user role
 */
function hasRole($role) {
    if (!isAuthenticated()) return false;
    return $_SESSION['user']['role'] === $role;
}

/**
 * Get current user
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Get file size in human readable format
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
