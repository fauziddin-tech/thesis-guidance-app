<?php
/**
 * Contoh dokumen PDF (tabel contoh_dokumen, migrasi 20261002_contoh_dokumen.sql).
 * Dosen dan admin mengunggah contoh (mis. proposal lengkap) sebagai pedoman tata tulis dan struktur.
 * Mahasiswa melihat daftar contoh di dashboard dan membukanya dalam pratinjau.
 * - visibilitas 'semua'     : terlihat oleh semua mahasiswa.
 * - visibilitas 'bimbingan' : hanya mahasiswa bimbingan pengunggah (Pembimbing 1 atau 2).
 * File disimpan di storage/examples (tidak dapat dibuka langsung) dan dilayani lewat ?action=contoh.
 */
const EXAMPLE_CATEGORIES = [
    'proposal' => 'Contoh proposal',
    'skripsi' => 'Contoh skripsi',
    'instrumen' => 'Contoh instrumen penelitian',
    'pedoman' => 'Pedoman penulisan',
    'lainnya' => 'Lainnya',
];
const EXAMPLE_MAX_BYTES = 20 * 1024 * 1024;

function examples_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready === null) {
        $result = $db->query("SHOW TABLES LIKE 'contoh_dokumen'");
        $ready = $result && $result->num_rows > 0;
    }
    return $ready;
}

function examples_dir(): string
{
    return dirname(__DIR__, 2) . '/storage/examples';
}

/** Contoh yang boleh dilihat pengguna ini, terbaru lebih dulu. */
function examples_visible_for(mysqli $db, array $user): array
{
    if (!examples_ready($db)) return [];
    $base = 'SELECT e.*,u.nama_lengkap AS pengunggah_nama,u.role AS pengunggah_role FROM contoh_dokumen e JOIN users u ON u.id=e.pengunggah_id';
    if ($user['role'] !== 'mahasiswa') {
        $result = $db->query($base . ' ORDER BY e.created_at DESC,e.id DESC');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    $lecturers = examples_student_lecturers($db, (int)$user['id']);
    $filter = "e.visibilitas='semua'" . ($lecturers ? " OR (e.visibilitas='bimbingan' AND e.pengunggah_id IN (" . implode(',', $lecturers) . '))' : '');
    $result = $db->query($base . ' WHERE ' . $filter . ' ORDER BY e.created_at DESC,e.id DESC');
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/** Id dosen pembimbing (1 dan 2) seorang mahasiswa. */
function examples_student_lecturers(mysqli $db, int $studentId): array
{
    $ids = [];
    $result = $db->query("SELECT id,dosen_id FROM bimbingan WHERE mahasiswa_id=$studentId");
    while ($result && ($row = $result->fetch_assoc())) {
        $ids[] = (int)$row['dosen_id'];
        if (p2_ready($db) && ($second = p2_second_id($db, (int)$row['id']))) $ids[] = $second;
    }
    return array_values(array_unique(array_filter($ids)));
}

function examples_can_view(mysqli $db, array $user, array $example): bool
{
    if ($user['role'] !== 'mahasiswa') return true;
    if ($example['visibilitas'] === 'semua') return true;
    return in_array((int)$example['pengunggah_id'], examples_student_lecturers($db, (int)$user['id']), true);
}

function examples_find(mysqli $db, int $id): ?array
{
    if (!examples_ready($db)) return null;
    $stmt = $db->prepare('SELECT * FROM contoh_dokumen WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);$stmt->execute();$row = $stmt->get_result()->fetch_assoc();$stmt->close();
    return $row ?: null;
}

/** Menyimpan contoh baru. $file = elemen $_FILES. Melempar RuntimeException berisi pesan untuk pengguna. */
function examples_upload(mysqli $db, array $user, string $title, string $category, string $note, string $visibility, ?array $file): void
{
    if (!examples_ready($db)) throw new RuntimeException('Fitur contoh dokumen belum diaktifkan. Hubungi administrator.');
    if (!in_array($user['role'], ['dosen', 'admin'], true)) throw new RuntimeException('Tindakan tidak diizinkan.');
    if (mb_strlen($title) < 5 || mb_strlen($title) > 200) throw new RuntimeException('Judul contoh wajib diisi, 5–200 karakter.');
    if (!isset(EXAMPLE_CATEGORIES[$category])) throw new RuntimeException('Pilih jenis contoh.');
    if (mb_strlen($note) > 500) throw new RuntimeException('Keterangan maksimal 500 karakter.');
    if ($user['role'] === 'admin') $visibility = 'semua';
    if (!in_array($visibility, ['semua', 'bimbingan'], true)) throw new RuntimeException('Pilih siapa yang dapat melihat contoh ini.');
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('File gagal diterima. Pilih kembali file PDF.');
    if (($file['size'] ?? 0) < 100 || $file['size'] > EXAMPLE_MAX_BYTES) throw new RuntimeException('Ukuran file PDF harus di bawah 20 MB.');
    $handle = fopen((string)$file['tmp_name'], 'rb');$header = $handle ? (string)fread($handle, 5) : '';if ($handle) fclose($handle);
    if ($header !== '%PDF-' || strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'pdf') throw new RuntimeException('Contoh dokumen harus berupa file PDF.');
    $dir = examples_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) throw new RuntimeException('Folder penyimpanan contoh tidak dapat dibuat.');
    $stored = 'contoh-' . bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $stored)) throw new RuntimeException('File belum dapat disimpan. Periksa izin folder storage/examples.');
    $relative = 'storage/examples/' . $stored;$size = (int)$file['size'];$uploader = (int)$user['id'];$noteValue = $note === '' ? null : $note;
    $stmt = $db->prepare('INSERT INTO contoh_dokumen(pengunggah_id,judul,kategori,keterangan,visibilitas,file_path,ukuran) VALUES(?,?,?,?,?,?,?)');
    $stmt->bind_param('isssssi', $uploader, $title, $category, $noteValue, $visibility, $relative, $size);
    $ok = $stmt->execute();$stmt->close();
    if (!$ok) {@unlink($dir . '/' . $stored);throw new RuntimeException('Data contoh belum dapat disimpan.');}
}

/** Menghapus contoh: pengunggahnya sendiri atau admin. */
function examples_delete(mysqli $db, array $user, int $id): void
{
    $example = examples_find($db, $id);
    if (!$example) throw new RuntimeException('Contoh dokumen tidak ditemukan.');
    if ($user['role'] !== 'admin' && (int)$example['pengunggah_id'] !== (int)$user['id']) throw new RuntimeException('Anda hanya dapat menghapus contoh yang Anda unggah.');
    $stmt = $db->prepare('DELETE FROM contoh_dokumen WHERE id=?');$stmt->bind_param('i', $id);$stmt->execute();$stmt->close();
    $path = dirname(__DIR__, 2) . '/' . $example['file_path'];
    if (strpos(realpath($path) ?: '', realpath(examples_dir()) ?: '/nonexistent') === 0) @unlink($path);
}

function examples_size_label(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : number_format(max(1, $bytes) / 1024, 0, ',', '.') . ' KB';
}
