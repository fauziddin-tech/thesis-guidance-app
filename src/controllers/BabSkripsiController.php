<?php

class BabSkripsiController {
    private mysqli $conn;
    private string $uploadDir;

    public function __construct(mysqli $connection) {
        $this->conn = $connection;
        $this->uploadDir = dirname(__DIR__, 2) . '/uploads/bab/';
    }

    public function upload(int $bimbinganId, string $namaBab, array $file): array {
        if ($namaBab === '' || mb_strlen($namaBab) > 100) {
            return ['success' => false, 'error' => 'Nama bab tidak valid.'];
        }
        $validation = $this->validateFile($file);
        if (!$validation['success']) return $validation;

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0750, true) && !is_dir($this->uploadDir)) {
            return ['success' => false, 'error' => 'Folder upload tidak dapat dibuat.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $absolute = $this->uploadDir . $filename;
        $relative = 'uploads/bab/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absolute)) {
            return ['success' => false, 'error' => 'Gagal menyimpan file upload.'];
        }

        $stmt = $this->conn->prepare("SELECT COALESCE(MAX(versi), 0) + 1 AS versi FROM bab_skripsi WHERE bimbingan_id = ? AND nama_bab = ?");
        $stmt->bind_param('is', $bimbinganId, $namaBab);
        $stmt->execute();
        $versi = (int)($stmt->get_result()->fetch_assoc()['versi'] ?? 1);
        $stmt->close();

        $stmt = $this->conn->prepare("INSERT INTO bab_skripsi (bimbingan_id, nama_bab, file_path, versi, status) VALUES (?, ?, ?, ?, 'menunggu_review')");
        $stmt->bind_param('issi', $bimbinganId, $namaBab, $relative, $versi);
        if (!$stmt->execute()) {
            @unlink($absolute);
            $stmt->close();
            return ['success' => false, 'error' => 'Data file gagal disimpan.'];
        }
        $id = $this->conn->insert_id;
        $stmt->close();
        return ['success' => true, 'id' => $id, 'file' => $filename];
    }

    private function validateFile(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File tidak tersedia atau gagal diupload.'];
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Ukuran file maksimal 10 MB.'];
        }
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Upload file tidak valid.'];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];
        if (!isset($allowed[$ext])) {
            return ['success' => false, 'error' => 'Format file harus PDF, DOC, atau DOCX.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        if (!$mime || !in_array($mime, $allowed[$ext], true)) {
            return ['success' => false, 'error' => 'Tipe file tidak sesuai dengan ekstensi.'];
        }
        return ['success' => true];
    }

    public function getRevisi(int $babId): array {
        $stmt = $this->conn->prepare("SELECT r.*, u.nama_lengkap AS dosen_nama FROM revisi r JOIN users u ON u.id = r.dosen_id WHERE r.bab_id = ? ORDER BY r.created_at DESC");
        $stmt->bind_param('i', $babId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function updateStatus(int $babId, string $status): array {
        $allowed = ['draft', 'menunggu_review', 'direvisi', 'disetujui'];
        if (!in_array($status, $allowed, true)) return ['success' => false, 'error' => 'Status tidak valid.'];
        $stmt = $this->conn->prepare('UPDATE bab_skripsi SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $babId);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok];
    }
}
