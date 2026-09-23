<?php
$user = $_SESSION['user'];
$userId = (int)$user['id'];
$role = (string)$user['role'];
$revisionFileSupported = false;
$revisionColumnResult = $conn->query("SHOW COLUMNS FROM revisi LIKE 'file_path'");
if ($revisionColumnResult) {$revisionFileSupported = $revisionColumnResult->num_rows > 0;$revisionColumnResult->free();}

// Periode akademik dan program studi (aktif setelah migrasi 20260923_add_academic_periods.sql).
$academicReady = academic_ready($conn);
$periods = [];$activePeriod = null;$prodiList = [];
if ($academicReady) {
    $periodResult = $conn->query('SELECT id,tahun_ajaran,semester,is_aktif FROM periode_akademik ORDER BY tahun_ajaran DESC,semester DESC');
    $periods = $periodResult ? $periodResult->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($periods as $period) if ((int)$period['is_aktif'] === 1) {$activePeriod = $period;break;}
    $prodiResult = $conn->query('SELECT id,kode,nama,jenjang FROM program_studi ORDER BY jenjang,nama');
    $prodiList = $prodiResult ? $prodiResult->fetch_all(MYSQLI_ASSOC) : [];
}
$periodIds = array_map('intval', array_column($periods, 'id'));
$prodiIds = array_map('intval', array_column($prodiList, 'id'));
$periodById = [];
foreach ($periods as $period) $periodById[(int)$period['id']] = $period;

$statusBadge = function (string $status): string {
    $success = ['aktif', 'selesai', 'disetujui', 'diterima'];
    $warning = ['direvisi', 'pending', 'menunggu_review', 'terjadwal'];
    $class = in_array($status, $success, true) ? 'success' : (in_array($status, $warning, true) ? 'warning' : 'secondary');
    $label = ['menunggu_review' => 'Menunggu review', 'direvisi' => 'Perlu revisi'][$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge badge-' . $class . '">' . h($label) . '</span>';
};

$redirectDashboard = function (string $anchor = ''): void {
    header('Location: ?page=dashboard' . ($anchor !== '' ? '#' . $anchor : ''));
    exit;
};

// Mengenali format Word dari isi file: 'doc' (Word 97-2003), 'docx' (Word 2007+), atau '' jika bukan dokumen Word.
// PDF, RTF, gambar, atau file lain yang diganti ekstensinya akan menghasilkan ''.
$detectWordFormat = function (string $path): string {
    $handle = @fopen($path, 'rb');
    if (!$handle) return '';
    $header = (string)fread($handle, 8);
    fclose($handle);
    if ($header === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") return 'doc';
    if (strncmp($header, "PK\x03\x04", 4) === 0) {
        if (!class_exists('ZipArchive')) return 'docx';
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return '';
        $isWord = $zip->locateName('word/document.xml') !== false;
        $zip->close();
        return $isWord ? 'docx' : '';
    }
    return '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Sesi formulir telah berakhir. Muat ulang halaman dan coba kembali.');
        $redirectDashboard();
    }

    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    if ($action === 'update_avatar') {
        $file = $_FILES['foto_profil'] ?? null;
        $mimeToExtension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = '';
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {$mime = (string)finfo_file($finfo, $file['tmp_name']);finfo_close($finfo);}
        }
        $imageInfo = $file && is_uploaded_file($file['tmp_name'] ?? '') ? @getimagesize($file['tmp_name']) : false;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) set_flash('danger', 'Foto gagal diterima. Pilih kembali file foto.');
        elseif (($file['size'] ?? 0) < 1 || $file['size'] > 2 * 1024 * 1024) set_flash('danger', 'Ukuran foto maksimal 2 MB.');
        elseif (!$imageInfo || !isset($mimeToExtension[$mime])) set_flash('danger', 'Format foto tidak valid. Gunakan JPG, PNG, atau WebP.');
        else {
            $avatarDirectory = __DIR__ . '/../../storage/avatars';
            if (!is_dir($avatarDirectory)) mkdir($avatarDirectory, 0750, true);
            $extension = $mimeToExtension[$mime];
            $destination = $avatarDirectory . '/user-' . $userId . '.' . $extension;
            if (!move_uploaded_file($file['tmp_name'], $destination)) set_flash('danger', 'Foto belum dapat disimpan. Periksa izin folder penyimpanan.');
            else {
                foreach (['jpg', 'png', 'webp'] as $oldExtension) {
                    $oldPath = $avatarDirectory . '/user-' . $userId . '.' . $oldExtension;
                    if ($oldPath !== $destination && is_file($oldPath)) unlink($oldPath);
                }
                set_flash('success', 'Foto profil berhasil diperbarui.');
            }
        }
        $redirectDashboard();
    }

    if ($action === 'update_profile' && $role === 'dosen') {
        $name = trim((string)($_POST['nama_lengkap'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $stmt = $conn->prepare('SELECT password FROM users WHERE id=? AND role=\'dosen\' LIMIT 1');
        $stmt->bind_param('i', $userId);$stmt->execute();$account = $stmt->get_result()->fetch_assoc();$stmt->close();

        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) set_flash('danger', 'Nama lengkap harus terdiri dari 2–150 karakter.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) set_flash('danger', 'Alamat email tidak valid.');
        elseif (!$account || !password_verify($currentPassword, $account['password'])) set_flash('danger', 'Sandi saat ini tidak sesuai.');
        elseif ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 72)) set_flash('danger', 'Sandi baru harus terdiri dari 8–72 karakter.');
        elseif ($newPassword !== $confirmPassword) set_flash('danger', 'Konfirmasi sandi baru tidak sama.');
        else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
            $stmt->bind_param('si', $email, $userId);$stmt->execute();$emailExists = (bool)$stmt->get_result()->fetch_assoc();$stmt->close();
            if ($emailExists) set_flash('danger', 'Alamat email sudah digunakan oleh akun lain.');
            else {
                if ($newPassword !== '') {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET nama_lengkap=?,email=?,password=? WHERE id=?');
                    $stmt->bind_param('sssi', $name, $email, $passwordHash, $userId);
                } else {
                    $stmt = $conn->prepare('UPDATE users SET nama_lengkap=?,email=? WHERE id=?');
                    $stmt->bind_param('ssi', $name, $email, $userId);
                }
                $saved = $stmt->execute();$stmt->close();
                if ($saved) {
                    $_SESSION['user']['nama_lengkap'] = $name;
                    $_SESSION['user']['email'] = $email;
                    set_flash('success', $newPassword !== '' ? 'Profil dan sandi berhasil diperbarui.' : 'Profil berhasil diperbarui.');
                } else set_flash('danger', 'Profil belum dapat diperbarui. Silakan coba kembali.');
            }
        }
        $redirectDashboard();
    }

    if ($action === 'upload_chapter' && $role === 'mahasiswa') {
        $guidanceId = filter_input(INPUT_POST, 'bimbingan_id', FILTER_VALIDATE_INT);
        $chapterName = trim((string)($_POST['nama_bab'] ?? ''));
        $file = $_FILES['file_bab'] ?? null;
        $stmt = $conn->prepare("SELECT id,dosen_id FROM bimbingan WHERE id=? AND mahasiswa_id=? AND status='aktif' LIMIT 1");
        $stmt->bind_param('ii', $guidanceId, $userId);$stmt->execute();$guidance = $stmt->get_result()->fetch_assoc();$stmt->close();

        $allowedExtensions = ['doc', 'docx'];
        $extension = $file ? strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) : '';
        $wordFormat = ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ? $detectWordFormat((string)$file['tmp_name']) : '';

        if (!$guidance) set_flash('danger', 'Bimbingan aktif tidak ditemukan.');
        elseif ($chapterName === '' || mb_strlen($chapterName) > 100) set_flash('danger', 'Nama bab wajib diisi dan maksimal 100 karakter.');
        elseif (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) set_flash('danger', 'File gagal diterima. Pilih kembali dokumen Anda.');
        elseif (($file['size'] ?? 0) < 1 || $file['size'] > 10 * 1024 * 1024) set_flash('danger', 'Ukuran file harus antara 1 byte dan 10 MB.');
        elseif ($extension === 'pdf') set_flash('danger', 'File PDF tidak diterima. Unggah naskah dalam format Word (DOC atau DOCX) agar dosen dapat memberi komentar langsung di dalam file.');
        elseif (!in_array($extension, $allowedExtensions, true) || $wordFormat === '') set_flash('danger', 'Format file tidak valid. Unggah dokumen Microsoft Word (DOC atau DOCX). Jika file berasal dari aplikasi lain, simpan ulang melalui File > Save As > Word Document.');
        else {
            $uploadDirectory = __DIR__ . '/../../storage/uploads';
            if (!is_dir($uploadDirectory)) mkdir($uploadDirectory, 0750, true);
            $storedName = bin2hex(random_bytes(20)) . '.' . $wordFormat;
            $absolutePath = $uploadDirectory . '/' . $storedName;
            $relativePath = 'storage/uploads/' . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) set_flash('danger', 'Dokumen belum dapat disimpan. Periksa izin folder penyimpanan.');
            else {
                $stmt = $conn->prepare('SELECT COALESCE(MAX(versi),0)+1 AS next_version FROM bab_skripsi WHERE bimbingan_id=? AND nama_bab=?');
                $stmt->bind_param('is', $guidanceId, $chapterName);$stmt->execute();$version = (int)$stmt->get_result()->fetch_assoc()['next_version'];$stmt->close();
                $stmt = $conn->prepare("INSERT INTO bab_skripsi(bimbingan_id,nama_bab,file_path,versi,status) VALUES(?,?,?,?,'menunggu_review')");
                $stmt->bind_param('issi', $guidanceId, $chapterName, $relativePath, $version);$saved = $stmt->execute();$stmt->close();
                if (!$saved) {unlink($absolutePath);set_flash('danger', 'Data dokumen belum dapat disimpan. Silakan coba kembali.');}
                else {
                    $message = $user['nama_lengkap'] . ' mengunggah ' . $chapterName . ' versi ' . $version . '.';$link = '?page=dashboard#mahasiswa-bimbingan';
                    $recipientId = (int)$guidance['dosen_id'];
                    $stmt = $conn->prepare("INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,'unggah_bab',?,?)");
                    $stmt->bind_param('iss', $recipientId, $message, $link);$stmt->execute();$stmt->close();
                    set_flash('success', 'Dokumen berhasil diunggah dan dosen pembimbing telah diberi notifikasi.');
                }
            }
        }
        $redirectDashboard('dokumen-saya');
    }

    if (($action === 'add_revision' || $action === 'approve_document') && $role === 'dosen') {
        $chapterId = filter_input(INPUT_POST, 'bab_id', FILTER_VALIDATE_INT);
        $stmt = $conn->prepare('SELECT bs.id,bs.nama_bab,b.mahasiswa_id FROM bab_skripsi bs JOIN bimbingan b ON bs.bimbingan_id=b.id WHERE bs.id=? AND b.dosen_id=? LIMIT 1');
        $stmt->bind_param('ii', $chapterId, $userId);$stmt->execute();$chapter = $stmt->get_result()->fetch_assoc();$stmt->close();
        if (!$chapter) set_flash('danger', 'Dokumen tidak ditemukan atau bukan bagian dari bimbingan Anda.');
        elseif ($action === 'approve_document') {
            $stmt = $conn->prepare(column_exists($conn, 'bab_skripsi', 'disetujui_at') ? "UPDATE bab_skripsi SET status='disetujui',disetujui_at=NOW() WHERE id=?" : "UPDATE bab_skripsi SET status='disetujui' WHERE id=?");$stmt->bind_param('i', $chapterId);$ok = $stmt->execute();$stmt->close();
            if ($ok) {
                $message = $chapter['nama_bab'] . ' telah disetujui oleh dosen pembimbing.';$link = '?page=dashboard#dokumen-saya';
                $recipientId = (int)$chapter['mahasiswa_id'];
                $stmt = $conn->prepare("INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,'persetujuan',?,?)");$stmt->bind_param('iss', $recipientId, $message, $link);$stmt->execute();$stmt->close();
                set_flash('success', 'Dokumen berhasil disetujui.');
            } else set_flash('danger', 'Status dokumen belum dapat diperbarui.');
        } else {
            $comment = trim((string)($_POST['komentar'] ?? ''));$revisionType = (string)($_POST['tipe_revisi'] ?? 'minor');
            $revisionFile = $_FILES['file_revisi'] ?? null;
            $revisionFileProvided = $revisionFile && ($revisionFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $revisionExtension = $revisionFileProvided ? strtolower(pathinfo((string)$revisionFile['name'], PATHINFO_EXTENSION)) : '';
            $revisionFormat = ($revisionFileProvided && ($revisionFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ? $detectWordFormat((string)$revisionFile['tmp_name']) : '';
            if ($comment === '') $comment = 'Komentar revisi dituliskan langsung di dalam file terlampir.';
            if (mb_strlen($comment) > 5000) set_flash('danger', 'Catatan ringkas maksimal 5.000 karakter.');
            elseif (!in_array($revisionType, ['minor', 'major', 'kritis'], true)) set_flash('danger', 'Tingkat revisi tidak valid.');
            elseif (!$revisionFileSupported) set_flash('danger', 'Fitur file revisi belum diaktifkan pada basis data. Jalankan file migrasi terlebih dahulu.');
            elseif (!$revisionFileProvided) set_flash('danger', 'File revisi wajib diunggah. Tuliskan komentar revisi langsung di dalam file Word, lalu unggah file tersebut.');
            elseif (($revisionFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) set_flash('danger', 'File revisi gagal diterima. Pilih kembali file.');
            elseif (($revisionFile['size'] ?? 0) < 1 || $revisionFile['size'] > 10 * 1024 * 1024) set_flash('danger', 'Ukuran file revisi maksimal 10 MB.');
            elseif ($revisionExtension === 'pdf') set_flash('danger', 'File PDF tidak diterima. Unggah file Word (DOC atau DOCX) yang berisi komentar revisi.');
            elseif (!in_array($revisionExtension, ['doc', 'docx'], true) || $revisionFormat === '') set_flash('danger', 'File revisi harus berupa dokumen Word (DOC atau DOCX) yang valid.');
            else {
                $revisionRelativePath = null;$revisionAbsolutePath = null;
                if ($revisionFileProvided) {
                    $uploadDirectory = __DIR__ . '/../../storage/uploads';
                    if (!is_dir($uploadDirectory)) mkdir($uploadDirectory, 0750, true);
                    $storedName = 'revision-' . bin2hex(random_bytes(20)) . '.' . $revisionFormat;
                    $revisionAbsolutePath = $uploadDirectory . '/' . $storedName;
                    $revisionRelativePath = 'storage/uploads/' . $storedName;
                    if (!move_uploaded_file($revisionFile['tmp_name'], $revisionAbsolutePath)) {
                        set_flash('danger', 'Lampiran revisi belum dapat disimpan. Periksa izin folder penyimpanan.');
                        $redirectDashboard('mahasiswa-bimbingan');
                    }
                }
                $conn->begin_transaction();
                if ($revisionFileSupported) {
                    $stmt = $conn->prepare('INSERT INTO revisi(bab_id,dosen_id,komentar,tipe_revisi,file_path) VALUES(?,?,?,?,?)');$stmt->bind_param('iisss', $chapterId, $userId, $comment, $revisionType, $revisionRelativePath);
                } else {
                    $stmt = $conn->prepare('INSERT INTO revisi(bab_id,dosen_id,komentar,tipe_revisi) VALUES(?,?,?,?)');$stmt->bind_param('iiss', $chapterId, $userId, $comment, $revisionType);
                }
                $ok = $stmt->execute();$stmt->close();
                if ($ok) {$stmt = $conn->prepare("UPDATE bab_skripsi SET status='direvisi' WHERE id=?");$stmt->bind_param('i', $chapterId);$ok = $stmt->execute();$stmt->close();}
                if ($ok) $conn->commit(); else $conn->rollback();
                if ($ok) {
                    $message = 'Catatan revisi baru diberikan untuk ' . $chapter['nama_bab'] . '.';$link = '?page=dashboard#dokumen-saya';
                    $recipientId = (int)$chapter['mahasiswa_id'];
                    $stmt = $conn->prepare("INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,'revisi',?,?)");$stmt->bind_param('iss', $recipientId, $message, $link);$stmt->execute();$stmt->close();
                    set_flash('success', 'File revisi berisi komentar berhasil dikirim kepada mahasiswa.');
                } else {if ($revisionAbsolutePath && is_file($revisionAbsolutePath)) unlink($revisionAbsolutePath);set_flash('danger', 'Catatan revisi belum dapat disimpan.');}
            }
        }
        $redirectDashboard('mahasiswa-bimbingan');
    }

    if ($action === 'update_academic_profile' && $role === 'mahasiswa' && $academicReady) {
        $nim = strtoupper(trim((string)($_POST['nim'] ?? '')));
        $prodiId = (int)($_POST['prodi_id'] ?? 0);
        $angkatan = (int)($_POST['angkatan'] ?? 0);
        $stmt = $conn->prepare('SELECT nim,prodi_id,angkatan FROM users WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $userId);$stmt->execute();$current = $stmt->get_result()->fetch_assoc();$stmt->close();
        $nimTaken = false;
        if (valid_nim($nim)) {$stmt = $conn->prepare('SELECT id FROM users WHERE nim=? AND id<>? LIMIT 1');$stmt->bind_param('si', $nim, $userId);$stmt->execute();$nimTaken = $stmt->get_result()->num_rows > 0;$stmt->close();}
        if ($current && $current['nim'] && $current['prodi_id'] && $current['angkatan']) set_flash('danger', 'Data akademik sudah lengkap. Hubungi administrator jika ada kesalahan data.');
        elseif (!valid_nim($nim)) set_flash('danger', 'NIM wajib diisi, 5–30 karakter, dan hanya berisi huruf, angka, titik, atau tanda hubung.');
        elseif ($nimTaken) set_flash('danger', 'NIM sudah digunakan akun lain. Hubungi administrator.');
        elseif (!in_array($prodiId, $prodiIds, true)) set_flash('danger', 'Pilih program studi Anda.');
        elseif (!in_array($angkatan, angkatan_options(), true)) set_flash('danger', 'Pilih tahun angkatan Anda.');
        else {
            $stmt = $conn->prepare('UPDATE users SET nim=?,prodi_id=?,angkatan=? WHERE id=?');$stmt->bind_param('siii', $nim, $prodiId, $angkatan, $userId);$ok = $stmt->execute();$stmt->close();
            set_flash($ok ? 'success' : 'danger', $ok ? 'Data akademik berhasil disimpan.' : 'Data akademik belum dapat disimpan.');
        }
        $redirectDashboard();
    }

    if ($action === 'create_period' && $role === 'admin' && $academicReady) {
        $academicYear = trim((string)($_POST['tahun_ajaran'] ?? ''));
        $semester = (string)($_POST['semester'] ?? '');
        $makeActive = !empty($_POST['jadikan_aktif']);
        $validYear = preg_match('/^(\d{4})\/(\d{4})$/', $academicYear, $yearParts) === 1 && (int)$yearParts[2] === (int)$yearParts[1] + 1 && (int)$yearParts[1] >= 2000 && (int)$yearParts[1] <= 2100;
        if (!$validYear || !in_array($semester, ['ganjil', 'genap'], true)) set_flash('danger', 'Tahun ajaran atau semester tidak valid. Gunakan format seperti 2025/2026.');
        else {
            $stmt = $conn->prepare('SELECT id FROM periode_akademik WHERE tahun_ajaran=? AND semester=? LIMIT 1');$stmt->bind_param('ss', $academicYear, $semester);$stmt->execute();$exists = $stmt->get_result()->num_rows > 0;$stmt->close();
            if ($exists) set_flash('danger', 'Periode tersebut sudah ada.');
            else {
                $conn->begin_transaction();
                $ok = $makeActive ? (bool)$conn->query('UPDATE periode_akademik SET is_aktif=0') : true;
                $activeValue = $makeActive ? 1 : 0;
                if ($ok) {$stmt = $conn->prepare('INSERT INTO periode_akademik(tahun_ajaran,semester,is_aktif) VALUES(?,?,?)');$stmt->bind_param('ssi', $academicYear, $semester, $activeValue);$ok = $stmt->execute();$stmt->close();}
                if ($ok) $conn->commit(); else $conn->rollback();
                $label = period_label(['tahun_ajaran' => $academicYear, 'semester' => $semester]);
                set_flash($ok ? 'success' : 'danger', $ok ? 'Periode ' . $label . ' berhasil ditambahkan' . ($makeActive ? ' dan dijadikan periode aktif.' : '.') : 'Periode belum dapat disimpan.');
            }
        }
        $redirectDashboard('periode-akademik');
    }

    if ($action === 'activate_period' && $role === 'admin' && $academicReady) {
        $periodId = (int)($_POST['periode_id'] ?? 0);
        if (!in_array($periodId, $periodIds, true)) set_flash('danger', 'Periode tidak ditemukan.');
        else {
            $conn->begin_transaction();
            $ok = (bool)$conn->query('UPDATE periode_akademik SET is_aktif=0');
            if ($ok) {$stmt = $conn->prepare('UPDATE periode_akademik SET is_aktif=1 WHERE id=?');$stmt->bind_param('i', $periodId);$ok = $stmt->execute();$stmt->close();}
            if ($ok) $conn->commit(); else $conn->rollback();
            set_flash($ok ? 'success' : 'danger', $ok ? 'Periode ' . period_label($periodById[$periodId]) . ' kini menjadi periode aktif.' : 'Periode aktif belum dapat diubah.');
        }
        $redirectDashboard('periode-akademik');
    }

    if ($action === 'create_prodi' && $role === 'admin' && $academicReady) {
        $prodiName = trim((string)($_POST['nama_prodi'] ?? ''));
        $prodiCode = strtoupper(trim((string)($_POST['kode_prodi'] ?? '')));
        $prodiLevel = (string)($_POST['jenjang'] ?? 'S1');
        if (mb_strlen($prodiName) < 3 || mb_strlen($prodiName) > 150 || mb_strlen($prodiCode) > 20 || !in_array($prodiLevel, ['D3', 'D4', 'S1', 'S2', 'S3'], true)) set_flash('danger', 'Data program studi belum valid.');
        else {
            $stmt = $conn->prepare('SELECT id FROM program_studi WHERE nama=? AND jenjang=? LIMIT 1');$stmt->bind_param('ss', $prodiName, $prodiLevel);$stmt->execute();$exists = $stmt->get_result()->num_rows > 0;$stmt->close();
            if ($exists) set_flash('danger', 'Program studi tersebut sudah ada.');
            else {
                $codeValue = $prodiCode !== '' ? $prodiCode : null;
                $stmt = $conn->prepare('INSERT INTO program_studi(kode,nama,jenjang) VALUES(?,?,?)');$stmt->bind_param('sss', $codeValue, $prodiName, $prodiLevel);$ok = $stmt->execute();$stmt->close();
                set_flash($ok ? 'success' : 'danger', $ok ? 'Program studi berhasil ditambahkan.' : 'Program studi belum dapat disimpan.');
            }
        }
        $redirectDashboard('program-studi');
    }

    if ($action === 'update_student_academic' && $role === 'admin' && $academicReady) {
        $studentId = (int)($_POST['mahasiswa_id'] ?? 0);
        $nim = strtoupper(trim((string)($_POST['nim'] ?? '')));
        $prodiId = (int)($_POST['prodi_id'] ?? 0);
        $angkatan = (int)($_POST['angkatan'] ?? 0);
        $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND role='mahasiswa' LIMIT 1");$stmt->bind_param('i', $studentId);$stmt->execute();$studentExists = $stmt->get_result()->num_rows > 0;$stmt->close();
        $nimTaken = false;
        if (valid_nim($nim)) {$stmt = $conn->prepare('SELECT id FROM users WHERE nim=? AND id<>? LIMIT 1');$stmt->bind_param('si', $nim, $studentId);$stmt->execute();$nimTaken = $stmt->get_result()->num_rows > 0;$stmt->close();}
        if (!$studentExists) set_flash('danger', 'Mahasiswa tidak ditemukan.');
        elseif (!valid_nim($nim)) set_flash('danger', 'NIM tidak valid.');
        elseif ($nimTaken) set_flash('danger', 'NIM sudah digunakan akun lain.');
        elseif (!in_array($prodiId, $prodiIds, true)) set_flash('danger', 'Pilih program studi.');
        elseif (!in_array($angkatan, angkatan_options(), true)) set_flash('danger', 'Pilih tahun angkatan.');
        else {
            $stmt = $conn->prepare('UPDATE users SET nim=?,prodi_id=?,angkatan=? WHERE id=?');$stmt->bind_param('siii', $nim, $prodiId, $angkatan, $studentId);$ok = $stmt->execute();$stmt->close();
            set_flash($ok ? 'success' : 'danger', $ok ? 'Data akademik mahasiswa berhasil diperbarui.' : 'Data akademik belum dapat diperbarui.');
        }
        $redirectDashboard('data-mahasiswa');
    }

    if ($action === 'move_guidance_period' && $role === 'admin' && $academicReady) {
        $guidanceId = (int)($_POST['bimbingan_id'] ?? 0);
        $periodId = (int)($_POST['periode_id'] ?? 0);
        if ($guidanceId < 1 || !in_array($periodId, $periodIds, true)) set_flash('danger', 'Bimbingan atau periode tidak valid.');
        else {
            $stmt = $conn->prepare('UPDATE bimbingan SET periode_id=? WHERE id=?');$stmt->bind_param('ii', $periodId, $guidanceId);$ok = $stmt->execute();$stmt->close();
            set_flash($ok ? 'success' : 'danger', $ok ? 'Periode bimbingan berhasil diperbarui.' : 'Periode bimbingan belum dapat diperbarui.');
        }
        $returnQuery = ['page' => 'dashboard'];
        $returnPeriod = (string)($_POST['filter_periode'] ?? '');
        if ($returnPeriod !== '') $returnQuery['periode'] = $returnPeriod === 'semua' ? 'semua' : (string)(int)$returnPeriod;
        $returnProdi = (int)($_POST['filter_prodi'] ?? 0);
        if ($returnProdi > 0) $returnQuery['prodi'] = (string)$returnProdi;
        header('Location: ?' . http_build_query($returnQuery) . '#daftar-bimbingan');
        exit;
    }

    if ($action === 'create_lecturer' && $role === 'admin') {
        $name = trim((string)($_POST['nama_lengkap'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($name === '' || !preg_match('/^[A-Za-z0-9._-]{4,100}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            set_flash('danger', 'Data dosen belum lengkap atau belum memenuhi ketentuan.');
        } else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');$stmt->bind_param('ss', $username, $email);$stmt->execute();$exists = $stmt->get_result()->num_rows > 0;$stmt->close();
            if ($exists) set_flash('danger', 'Username atau email dosen sudah digunakan.');
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);$lecturerRole = 'dosen';$phone = '';
                $stmt = $conn->prepare('INSERT INTO users(username,email,password,role,nama_lengkap,no_telp) VALUES(?,?,?,?,?,?)');$stmt->bind_param('ssssss', $username, $email, $hash, $lecturerRole, $name, $phone);$ok = $stmt->execute();$stmt->close();
                set_flash($ok ? 'success' : 'danger', $ok ? 'Akun dosen berhasil dibuat.' : 'Akun dosen belum dapat dibuat.');
            }
        }
        $redirectDashboard('kelola-dosen');
    }

    if ($action === 'create_guidance' && $role === 'admin') {
        $studentId = filter_input(INPUT_POST, 'mahasiswa_id', FILTER_VALIDATE_INT);
        $lecturerId = filter_input(INPUT_POST, 'dosen_id', FILTER_VALIDATE_INT);
        $title = trim((string)($_POST['judul_skripsi'] ?? ''));
        $description = trim((string)($_POST['deskripsi'] ?? ''));
        $guidancePeriodId = (int)($_POST['periode_id'] ?? 0);
        $stmt = $conn->prepare("SELECT SUM(role='mahasiswa' AND id=?) AS student_ok,SUM(role='dosen' AND id=?) AS lecturer_ok FROM users WHERE id IN (?,?)");
        $stmt->bind_param('iiii', $studentId, $lecturerId, $studentId, $lecturerId);$stmt->execute();$validUsers = $stmt->get_result()->fetch_assoc();$stmt->close();
        if (!$validUsers || !(int)$validUsers['student_ok'] || !(int)$validUsers['lecturer_ok'] || $title === '' || mb_strlen($title) > 255) {
            set_flash('danger', 'Mahasiswa, dosen, atau judul skripsi tidak valid.');
        } elseif ($academicReady && !in_array($guidancePeriodId, $periodIds, true)) {
            set_flash('danger', 'Pilih periode akademik untuk bimbingan ini.');
        } else {
            if ($academicReady) {
                $stmt = $conn->prepare("INSERT INTO bimbingan(mahasiswa_id,dosen_id,periode_id,judul_skripsi,deskripsi,status) VALUES(?,?,?,?,?,'aktif')");$stmt->bind_param('iiiss', $studentId, $lecturerId, $guidancePeriodId, $title, $description);
            } else {
                $stmt = $conn->prepare("INSERT INTO bimbingan(mahasiswa_id,dosen_id,judul_skripsi,deskripsi,status) VALUES(?,?,?,?,'aktif')");$stmt->bind_param('iiss', $studentId, $lecturerId, $title, $description);
            }
            $ok = $stmt->execute();$stmt->close();
            if ($ok) {
                $message = 'Bimbingan baru untuk judul: ' . $title . '.';$link = '?page=dashboard#bimbingan-saya';
                $stmt = $conn->prepare("INSERT INTO notifikasi(user_id,tipe,pesan,link) VALUES(?,'bimbingan_baru',?,?)");$stmt->bind_param('iss', $studentId, $message, $link);$stmt->execute();$stmt->close();
                set_flash('success', 'Relasi bimbingan berhasil dibuat.');
            } else set_flash('danger', 'Relasi bimbingan belum dapat dibuat.');
        }
        $redirectDashboard('kelola-bimbingan');
    }
    set_flash('danger', 'Tindakan tidak dikenali atau tidak diizinkan.');$redirectDashboard();
}

$flash = pull_flash();$guidances = [];$documents = [];$students = [];$lecturers = [];
$periodJoin = $academicReady ? ' LEFT JOIN periode_akademik p ON b.periode_id=p.id' : '';
$periodSelect = $academicReady ? ',p.tahun_ajaran,p.semester' : '';
$studentAcademic = null;
if ($role === 'mahasiswa' && $academicReady) {
    $stmt = $conn->prepare('SELECT u.nim,u.angkatan,u.prodi_id,ps.nama,ps.jenjang FROM users u LEFT JOIN program_studi ps ON u.prodi_id=ps.id WHERE u.id=? LIMIT 1');
    $stmt->bind_param('i', $userId);$stmt->execute();$studentAcademic = $stmt->get_result()->fetch_assoc();$stmt->close();
}
$academicIncomplete = $role === 'mahasiswa' && $academicReady && (!$studentAcademic || !$studentAcademic['nim'] || !$studentAcademic['prodi_id'] || !$studentAcademic['angkatan']);

// Filter periode (dosen & admin) dan program studi (admin). Bawaan: semua periode.
$periodFilter = 0;$prodiFilter = 0;
if ($academicReady && ($role === 'dosen' || $role === 'admin')) {
    $requestedPeriod = (int)($_GET['periode'] ?? 0);
    if (in_array($requestedPeriod, $periodIds, true)) $periodFilter = $requestedPeriod;
    $requestedProdi = (int)($_GET['prodi'] ?? 0);
    if ($role === 'admin' && in_array($requestedProdi, $prodiIds, true)) $prodiFilter = $requestedProdi;
}

if ($role === 'mahasiswa') {
    $stmt = $conn->prepare('SELECT b.*,u.nama_lengkap AS dosen_nama' . $periodSelect . ' FROM bimbingan b JOIN users u ON b.dosen_id=u.id' . $periodJoin . ' WHERE b.mahasiswa_id=? ORDER BY b.created_at DESC');$stmt->bind_param('i', $userId);$stmt->execute();$guidances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    $revisionFileSelect = $revisionFileSupported ? ',(SELECT r.file_path FROM revisi r WHERE r.bab_id=bs.id ORDER BY r.created_at DESC LIMIT 1) AS file_revisi_terakhir,(SELECT r.id FROM revisi r WHERE r.bab_id=bs.id ORDER BY r.created_at DESC LIMIT 1) AS revisi_id_terakhir' : ',NULL AS file_revisi_terakhir,NULL AS revisi_id_terakhir';
    $stmt = $conn->prepare('SELECT bs.*,b.judul_skripsi,(SELECT r.komentar FROM revisi r WHERE r.bab_id=bs.id ORDER BY r.created_at DESC LIMIT 1) AS komentar_terakhir,(SELECT r.tipe_revisi FROM revisi r WHERE r.bab_id=bs.id ORDER BY r.created_at DESC LIMIT 1) AS tipe_terakhir'.$revisionFileSelect.' FROM bab_skripsi bs JOIN bimbingan b ON bs.bimbingan_id=b.id WHERE b.mahasiswa_id=? ORDER BY bs.uploaded_at DESC');$stmt->bind_param('i', $userId);$stmt->execute();$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
} elseif ($role === 'dosen') {
    $studentSelect = $academicReady ? ',u.nim,u.angkatan,ps.nama AS prodi_nama,ps.jenjang AS prodi_jenjang' : '';
    $studentJoin = $academicReady ? ' LEFT JOIN program_studi ps ON u.prodi_id=ps.id' : '';
    $stmt = $conn->prepare('SELECT b.*,u.nama_lengkap,u.email' . $studentSelect . $periodSelect . ' FROM bimbingan b JOIN users u ON b.mahasiswa_id=u.id' . $studentJoin . $periodJoin . ' WHERE b.dosen_id=? ORDER BY b.created_at DESC');$stmt->bind_param('i', $userId);$stmt->execute();$guidances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    $stmt = $conn->prepare("SELECT bs.*,b.judul_skripsi,b.mahasiswa_id," . ($academicReady ? 'b.periode_id AS bimbingan_periode_id,' : '') . "u.nama_lengkap,u.email FROM bab_skripsi bs JOIN bimbingan b ON bs.bimbingan_id=b.id JOIN users u ON b.mahasiswa_id=u.id WHERE b.dosen_id=? ORDER BY FIELD(bs.status,'menunggu_review','direvisi','draft','disetujui'),bs.uploaded_at DESC");$stmt->bind_param('i', $userId);$stmt->execute();$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
} elseif ($role === 'admin') {
    $students = $conn->query($academicReady
        ? "SELECT u.id,u.nama_lengkap,u.email,u.nim,u.angkatan,u.prodi_id,ps.nama AS prodi_nama,ps.jenjang AS prodi_jenjang FROM users u LEFT JOIN program_studi ps ON u.prodi_id=ps.id WHERE u.role='mahasiswa' ORDER BY u.nama_lengkap"
        : "SELECT id,nama_lengkap,email FROM users WHERE role='mahasiswa' ORDER BY nama_lengkap")->fetch_all(MYSQLI_ASSOC);
    $lecturers = $conn->query("SELECT id,nama_lengkap,email FROM users WHERE role='dosen' ORDER BY nama_lengkap")->fetch_all(MYSQLI_ASSOC);
    $guidances = $conn->query('SELECT b.*,m.nama_lengkap AS mahasiswa_nama,d.nama_lengkap AS dosen_nama'
        . ($academicReady ? ',m.nim,m.angkatan,m.prodi_id,ps.nama AS prodi_nama,ps.jenjang AS prodi_jenjang' : '') . $periodSelect
        . ' FROM bimbingan b JOIN users m ON b.mahasiswa_id=m.id JOIN users d ON b.dosen_id=d.id'
        . ($academicReady ? ' LEFT JOIN program_studi ps ON m.prodi_id=ps.id' : '') . $periodJoin
        . ' ORDER BY b.created_at DESC')->fetch_all(MYSQLI_ASSOC);
}
$allGuidanceCount = count($guidances);
$pendingAllPeriods = count(array_filter($documents, function ($item) use ($role) {
    return $role === 'dosen' && $item['status'] === 'menunggu_review';
}));
if ($periodFilter) {
    $guidances = array_values(array_filter($guidances, function ($item) use ($periodFilter) {return (int)($item['periode_id'] ?? 0) === $periodFilter;}));
    if ($role === 'dosen') $documents = array_values(array_filter($documents, function ($item) use ($periodFilter) {return (int)($item['bimbingan_periode_id'] ?? 0) === $periodFilter;}));
}
if ($prodiFilter) $guidances = array_values(array_filter($guidances, function ($item) use ($prodiFilter) {return (int)($item['prodi_id'] ?? 0) === $prodiFilter;}));
$studentAcademicLine = function (array $row): string {
    $parts = [];
    if (!empty($row['nim'])) $parts[] = 'NIM ' . $row['nim'];
    $prodi = prodi_label($row, 'prodi_nama', 'prodi_jenjang');
    if ($prodi !== '') $parts[] = $prodi;
    if (!empty($row['angkatan'])) $parts[] = 'Angkatan ' . $row['angkatan'];
    return implode(' · ', $parts);
};
$renderFilter = function (string $anchor, bool $withProdi) use ($periods, $periodFilter, $prodiList, $prodiFilter): string {
    $html = '<form method="get" class="period-filter" action="#' . h($anchor) . '"><input type="hidden" name="page" value="dashboard">';
    $html .= '<label>Periode <select name="periode" data-auto-submit><option value="semua">Semua periode</option>';
    foreach ($periods as $period) $html .= '<option value="' . (int)$period['id'] . '"' . ($periodFilter === (int)$period['id'] ? ' selected' : '') . '>' . h(period_label($period)) . ((int)$period['is_aktif'] ? ' (aktif)' : '') . '</option>';
    $html .= '</select></label>';
    if ($withProdi) {
        $html .= '<label>Prodi <select name="prodi" data-auto-submit><option value="0">Semua prodi</option>';
        foreach ($prodiList as $prodi) $html .= '<option value="' . (int)$prodi['id'] . '"' . ($prodiFilter === (int)$prodi['id'] ? ' selected' : '') . '>' . h(prodi_label($prodi)) . '</option>';
        $html .= '</select></label>';
    }
    return $html . '<button class="btn btn-secondary btn-small" type="submit">Terapkan</button></form>';
};
$pendingCount = count(array_filter($documents, function ($item) use ($role) {
    return $role === 'mahasiswa' ? $item['status'] === 'direvisi' : $item['status'] === 'menunggu_review';
}));
$documentsByStudent = [];
$pendingByStudent = [];
if ($role === 'dosen') {
    foreach ($documents as $document) {
        $studentId = (int)$document['mahasiswa_id'];
        $documentsByStudent[$studentId][] = $document;
        if ($document['status'] === 'menunggu_review') $pendingByStudent[$studentId] = ($pendingByStudent[$studentId] ?? 0) + 1;
    }
    usort($guidances, function (array $first, array $second) use ($pendingByStudent): int {
        $firstPending = $pendingByStudent[(int)$first['mahasiswa_id']] ?? 0;
        $secondPending = $pendingByStudent[(int)$second['mahasiswa_id']] ?? 0;
        return $secondPending <=> $firstPending ?: strnatcasecmp($first['nama_lengkap'], $second['nama_lengkap']);
    });
}
$getAvatar = function (int $targetUserId): array {
    foreach (['jpg', 'png', 'webp'] as $extension) {
        $candidate = __DIR__ . '/../../storage/avatars/user-' . $targetUserId . '.' . $extension;
        if (is_file($candidate)) return ['url' => '?action=avatar&id=' . $targetUserId . '&v=' . filemtime($candidate), 'exists' => true];
    }
    return ['url' => '', 'exists' => false];
};
$getInitials = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $value = '';
    foreach (array_slice(array_filter($parts), 0, 2) as $part) $value .= strtoupper(substr($part, 0, 1));
    return $value !== '' ? $value : 'U';
};
$avatarPath = '';
$avatarVersion = '';
foreach (['jpg', 'png', 'webp'] as $extension) {
    $candidate = __DIR__ . '/../../storage/avatars/user-' . $userId . '.' . $extension;
    if (is_file($candidate)) {$avatarPath = '?action=avatar';$avatarVersion = (string)filemtime($candidate);break;}
}
$nameParts = preg_split('/\s+/', trim((string)$user['nama_lengkap']));
$initials = '';
foreach (array_slice(array_filter($nameParts), 0, 2) as $part) $initials .= strtoupper(substr($part, 0, 1));
if ($initials === '') $initials = 'U';
?>
<div class="page-head"><details class="profile-menu"><summary><span class="profile-avatar"><?php if($avatarPath): ?><img src="<?=h($avatarPath)?>&amp;v=<?=h($avatarVersion)?>" alt="Foto profil <?=h($user['nama_lengkap'])?>"><?php else: ?><span aria-hidden="true"><?=h($initials)?></span><?php endif; ?></span><span class="profile-summary-copy"><strong><?=h(ucfirst($role))?></strong><small>Kelola profil</small></span></summary><div class="profile-popover"><strong>Foto profil</strong><p>Gunakan foto JPG, PNG, atau WebP maksimal 2 MB.</p><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="update_avatar"><label class="sr-only" for="foto_profil">Pilih foto profil</label><input id="foto_profil" name="foto_profil" type="file" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-primary btn-small btn-block" type="submit">Simpan Foto</button></form><?php if($role==='dosen'): ?><div class="profile-popover-divider"></div><button class="btn btn-secondary btn-small btn-block" type="button" data-profile-open>Edit Profil dan Sandi</button><?php endif; ?></div></details><div class="page-head-copy"><span class="eyebrow">RUANG KERJA <?=h(strtoupper($role))?></span><h1><span>Selamat datang</span><strong><?=h($user['nama_lengkap'])?></strong></h1><p><?=h($role==='mahasiswa'?'Pantau bimbingan dan kelola dokumen skripsi Anda.':($role==='dosen'?'Tinjau dokumen dan berikan arahan secara teratur.':'Kelola akun dosen dan relasi bimbingan dari satu halaman.'))?></p><?php if($academicReady): ?><div class="context-chips"><span class="context-chip">Periode aktif <strong><?=h($activePeriod?period_label($activePeriod):'Belum diatur')?></strong></span><?php if($role==='mahasiswa'&&$studentAcademic&&!$academicIncomplete): ?><span class="context-chip">NIM <strong><?=h($studentAcademic['nim'])?></strong></span><span class="context-chip"><?=h(prodi_label($studentAcademic))?></span><span class="context-chip">Angkatan <strong><?=(int)$studentAcademic['angkatan']?></strong></span><?php endif; ?></div><?php endif; ?></div></div>
<?php if($flash): ?><div class="alert alert-<?=h($flash['type'])?>" role="status" tabindex="-1" data-flash><?=h($flash['message'])?></div><?php endif; ?>
<?php if($role==='mahasiswa'||$role==='dosen'): ?><section class="stats-grid" aria-label="Ringkasan dashboard"><article><span>Bimbingan</span><strong><?=count($guidances)?></strong><small>Total data bimbingan</small></article><article><span>Dokumen</span><strong><?=count($documents)?></strong><small>Seluruh versi dokumen</small></article><article><span><?=$role==='mahasiswa'?'Perlu ditindaklanjuti':'Antrean review'?></span><strong><?=$pendingCount?></strong><small>Status terbaru dokumen</small></article></section><?php endif; ?>

<?php if($role==='mahasiswa'): ?>
<?php if($academicIncomplete): ?><section class="card academic-card" id="data-akademik"><div class="section-heading"><span class="eyebrow">LENGKAPI DATA</span><h2>Data Akademik</h2><p>Isi NIM, program studi, dan tahun angkatan Anda. Data ini hanya dapat diisi sekali; perubahan selanjutnya melalui administrator.</p></div><?php if($prodiList): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="update_academic_profile"><div class="form-grid"><div class="form-group"><label for="academic_nim">NIM</label><input id="academic_nim" name="nim" value="<?=h($studentAcademic['nim']??'')?>" required minlength="5" maxlength="30" autocomplete="off"></div><div class="form-group"><label for="academic_angkatan">Tahun Angkatan</label><select id="academic_angkatan" name="angkatan" required><option value="">Pilih angkatan</option><?php foreach(angkatan_options() as $year): ?><option value="<?=(int)$year?>"<?=(int)($studentAcademic['angkatan']??0)===$year?' selected':''?>><?=(int)$year?></option><?php endforeach; ?></select></div></div><div class="form-group"><label for="academic_prodi">Program Studi</label><select id="academic_prodi" name="prodi_id" required><option value="">Pilih program studi</option><?php foreach($prodiList as $prodi): ?><option value="<?=(int)$prodi['id']?>"<?=(int)($studentAcademic['prodi_id']??0)===(int)$prodi['id']?' selected':''?>><?=h(prodi_label($prodi))?></option><?php endforeach; ?></select></div><button class="btn btn-primary" type="submit">Simpan Data Akademik</button></form><?php else: ?><div class="empty-state"><strong>Program studi belum tersedia.</strong><span>Administrator perlu menambahkan program studi terlebih dahulu.</span></div><?php endif; ?></section><?php endif; ?>
<nav class="quick-actions" aria-label="Akses cepat dashboard"><a href="#unggah-bab"><span>01</span><div><strong>Unggah naskah</strong><small>Kirim versi dokumen terbaru</small></div></a><a href="#dokumen-saya"><span>02</span><div><strong>Lihat catatan dosen</strong><small>Tindak lanjuti revisi terbaru</small></div></a><a href="#bimbingan-saya"><span>03</span><div><strong>Kartu bimbingan</strong><small>Unduh bukti bimbingan per dosen</small></div></a></nav>
<section class="card" id="bimbingan-saya"><div class="section-heading"><span class="eyebrow">RINGKASAN</span><h2>Bimbingan Saya</h2><p>Daftar judul, dosen pembimbing, dan status bimbingan.</p></div><?php if($guidances): ?><div class="table-wrap"><table><thead><tr><th>Judul Skripsi</th><th>Dosen Pembimbing</th><?php if($academicReady): ?><th>Periode</th><?php endif; ?><th>Status</th><th>Kartu</th></tr></thead><tbody><?php foreach($guidances as $item): ?><tr><td><strong><?=h($item['judul_skripsi'])?></strong></td><td><?=h($item['dosen_nama'])?></td><?php if($academicReady): ?><td><?=h(period_label($item))?></td><?php endif; ?><td><?=$statusBadge($item['status'])?></td><td><a class="btn btn-secondary btn-small" href="?action=kartu&amp;id=<?=(int)$item['id']?>" target="_blank" rel="noopener">Kartu Bimbingan</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><strong>Belum ada bimbingan.</strong><span>Hubungi administrator untuk menghubungkan akun Anda dengan dosen pembimbing.</span></div><?php endif; ?></section>
<section class="card" id="unggah-bab"><div class="section-heading"><span class="eyebrow">DOKUMEN BARU</span><h2>Unggah Bab Skripsi</h2><p>Versi baru dengan nama bab yang sama akan diberi nomor versi secara otomatis.</p></div><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="upload_chapter"><div class="form-grid"><div class="form-group"><label for="bimbingan_id">Bimbingan</label><select id="bimbingan_id" name="bimbingan_id" required><option value="">Pilih bimbingan aktif</option><?php foreach($guidances as $item): if($item['status']!=='aktif')continue; ?><option value="<?=(int)$item['id']?>"><?=h($item['judul_skripsi'])?></option><?php endforeach; ?></select></div><div class="form-group"><label for="nama_bab">Nama Bab</label><input type="text" id="nama_bab" name="nama_bab" maxlength="100" placeholder="Contoh: Bab I - Pendahuluan" required></div></div><div class="form-group"><label for="file_bab">File Skripsi</label><input type="file" id="file_bab" name="file_bab" accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required><small class="field-help">Hanya file Word (DOC atau DOCX), maksimal 10 MB. File PDF tidak diterima karena dosen memberi komentar revisi langsung di dalam file.</small></div><button type="submit" class="btn btn-primary" <?=!array_filter($guidances,function($item){return $item['status']==='aktif';})?'disabled':''?>>Unggah dan Kirim untuk Review</button></form></section>
<section class="card" id="dokumen-saya"><div class="section-heading"><span class="eyebrow">RIWAYAT</span><h2>Dokumen dan Catatan Revisi</h2><p>Semua versi naskah ditampilkan dari unggahan terbaru.</p></div><?php if($documents): ?><div class="document-list"><?php foreach($documents as $document): ?><article class="document-item"><div class="document-main"><div><span class="document-meta"><?=h($document['judul_skripsi'])?></span><h3><?=h($document['nama_bab'])?> <small>v<?=(int)$document['versi']?></small></h3><span class="document-date">Diunggah <?=date('d/m/Y H:i',strtotime($document['uploaded_at']))?></span></div><?=$statusBadge($document['status'])?></div><?php if($document['komentar_terakhir']): ?><div class="revision-note"><strong>Catatan <?=h($document['tipe_terakhir'])?></strong><p><?=nl2br(h($document['komentar_terakhir']))?></p><?php if($document['file_revisi_terakhir']): ?><a class="revision-attachment" href="?action=download_revision&amp;id=<?=(int)$document['revisi_id_terakhir']?>">Unduh file revisi (berisi komentar dosen)</a><?php endif; ?></div><?php endif; ?><div class="document-actions"><a class="btn btn-secondary btn-small" href="?action=download&amp;id=<?=(int)$document['id']?>">Unduh dokumen</a><?php if($document['status']==='direvisi'): ?><a class="btn btn-primary btn-small" href="#unggah-bab">Unggah perbaikan</a><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><strong>Belum ada dokumen.</strong><span>Unggah bab pertama untuk memulai proses review.</span></div><?php endif; ?></section>

<?php elseif($role==='dosen'): ?>
<nav class="quick-actions" aria-label="Akses cepat dashboard"><a href="#mahasiswa-bimbingan"><span>01</span><div><strong>Lihat mahasiswa</strong><small><?=count($guidances)?> mahasiswa bimbingan</small></div></a><a href="#mahasiswa-bimbingan"><span>02</span><div><strong>Antrean review</strong><small><?=$pendingCount?> dokumen perlu ditinjau</small></div></a></nav>
<section class="card lecturer-student-card" id="mahasiswa-bimbingan"><div class="section-heading"><span class="eyebrow">MAHASISWA</span><h2>Mahasiswa Bimbingan Saya</h2><p>Mahasiswa yang dokumennya perlu ditinjau ditempatkan paling atas.</p></div><?php if($academicReady&&$periods): ?><?=$renderFilter('mahasiswa-bimbingan',false)?><?php if($periodFilter&&$pendingAllPeriods>$pendingCount): ?><div class="alert alert-warning" role="status">Ada <?=$pendingAllPeriods-$pendingCount?> dokumen menunggu review di periode lain. <a href="?page=dashboard&amp;periode=semua#mahasiswa-bimbingan">Tampilkan semua periode</a></div><?php endif; ?><?php endif; ?><?php if($guidances): ?><div class="student-list-tools"><div class="student-search"><label class="sr-only" for="student-search">Cari mahasiswa</label><input id="student-search" type="search" placeholder="Cari nama, email, atau judul skripsi…" data-student-search></div><label class="student-page-size">Tampilkan <select data-student-page-size aria-label="Jumlah mahasiswa per halaman"><option value="10" selected>10</option><option value="20">20</option><option value="50">50</option></select> per halaman</label></div><div class="table-wrap"><table class="student-table" data-student-list><thead><tr><th>Mahasiswa</th><th>Judul Skripsi</th><th>Status</th><th>View</th></tr></thead><tbody><?php foreach($guidances as $item): $studentId=(int)$item['mahasiswa_id'];$studentAvatar=$getAvatar($studentId);$studentInitials=$getInitials($item['nama_lengkap']);$studentDocuments=$documentsByStudent[$studentId]??[];$studentPending=$pendingByStudent[$studentId]??0; ?><tr data-student-row><td><div class="student-identity"><button class="student-avatar-button" type="button" data-photo-open data-photo-src="<?=h($studentAvatar['url'])?>" data-photo-name="<?=h($item['nama_lengkap'])?>" data-photo-initials="<?=h($studentInitials)?>" aria-label="Lihat foto <?=h($item['nama_lengkap'])?>"><?php if($studentAvatar['exists']): ?><img src="<?=h($studentAvatar['url'])?>" alt=""><?php else: ?><span aria-hidden="true"><?=h($studentInitials)?></span><?php endif; ?></button><div><strong><?=h($item['nama_lengkap'])?></strong><small class="table-subtext"><?=h($item['email'])?></small><?php if($academicReady&&$studentAcademicLine($item)!==''): ?><small class="table-subtext"><?=h($studentAcademicLine($item))?></small><?php endif; ?></div></div></td><td><?=h($item['judul_skripsi'])?><?php if($academicReady): ?><small class="table-subtext">Periode <?=h(period_label($item))?></small><?php endif; ?></td><td><?=$statusBadge($item['status'])?><?php if($studentPending): ?><small class="attention-text"><?=$studentPending?> perlu ditinjau</small><?php endif; ?></td><td><button class="btn btn-secondary btn-small" type="button" data-documents-open="<?=$studentId?>" data-student-name="<?=h($item['nama_lengkap'])?>">View <span class="button-count"><?=count($studentDocuments)+1?></span></button> <a class="btn btn-secondary btn-small" href="?action=kartu&amp;id=<?=(int)$item['id']?>" target="_blank" rel="noopener">Kartu</a></td></tr><?php endforeach; ?></tbody></table></div><div class="student-list-empty" data-student-empty hidden>Tidak ada mahasiswa yang sesuai dengan pencarian.</div><div class="student-pagination"><span data-student-page-info></span><nav aria-label="Navigasi halaman mahasiswa" data-student-pagination></nav></div><?php else: ?><div class="empty-state"><strong>Belum ada mahasiswa bimbingan.</strong><span>Data akan tampil setelah mahasiswa ditugaskan kepada Anda.</span></div><?php endif; ?></section>
<?php $renderedStudentTemplates=[];foreach($guidances as $item): $studentId=(int)$item['mahasiswa_id'];if(isset($renderedStudentTemplates[$studentId]))continue;$renderedStudentTemplates[$studentId]=true;$studentDocuments=$documentsByStudent[$studentId]??[]; ?><template id="student-documents-<?=$studentId?>"><div class="table-wrap dialog-table-wrap"><table><thead><tr><th>Dokumen</th><th>Pengajuan/unggah</th><th>Status</th><th>Tindakan</th></tr></thead><tbody><tr class="title-submission-row"><td><strong>Pengajuan Judul</strong><small class="table-subtext"><?=h($item['judul_skripsi'])?></small></td><td><?=date('d/m/Y H:i',strtotime($item['created_at']))?></td><td><?=$statusBadge($item['status'])?></td><td><span class="table-muted">Judul skripsi</span></td></tr><?php foreach($studentDocuments as $document): $documentLabel=$document['nama_bab'].' v'.$document['versi'].' — '.$document['nama_lengkap']; ?><tr><td><a class="document-download-link" href="?action=download&amp;id=<?=(int)$document['id']?>" title="Unduh <?=h($document['nama_bab'])?>"><strong><?=h($document['nama_bab'])?></strong></a><small class="table-subtext">Versi <?=(int)$document['versi']?></small></td><td><?=date('d/m/Y H:i',strtotime($document['uploaded_at']))?></td><td><?=$statusBadge($document['status'])?></td><td><div class="table-actions"><button class="btn btn-secondary btn-small" type="button" data-revision-open data-document-id="<?=(int)$document['id']?>" data-document-label="<?=h($documentLabel)?>">Revisi</button><?php if($document['status']!=='disetujui'): ?><form method="post" data-confirm="Setujui dokumen ini?"><?=csrf_field()?><input type="hidden" name="action" value="approve_document"><input type="hidden" name="bab_id" value="<?=(int)$document['id']?>"><button class="btn btn-success btn-small" type="submit">Setujui</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div><?php if(!$studentDocuments): ?><p class="dialog-empty-note">Belum ada dokumen bab yang diunggah mahasiswa.</p><?php endif; ?></template><?php endforeach; ?>
<dialog class="dashboard-dialog documents-dialog" id="documents-dialog" aria-labelledby="documents-dialog-title"><div class="dialog-head"><div><span class="eyebrow">DOKUMEN MAHASISWA</span><h2 id="documents-dialog-title">Dokumen</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup popup">&times;</button></div><div data-documents-content></div></dialog>
<dialog class="dashboard-dialog revision-dialog" id="revision-dialog" aria-labelledby="revision-dialog-title"><div class="dialog-head"><div><span class="eyebrow">TINDAK LANJUT</span><h2 id="revision-dialog-title">Berikan Revisi</h2><p data-revision-document></p></div><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup popup">&times;</button></div><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="add_revision"><input type="hidden" id="revision_bab_id" name="bab_id" value="" required><div class="form-group"><label for="file_revisi">File Revisi Berisi Komentar</label><input id="file_revisi" name="file_revisi" type="file" accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required><small class="field-help">Unduh naskah mahasiswa, tuliskan komentar di dalam file (Microsoft Word: Review &gt; New Comment), lalu unggah kembali. Format DOC atau DOCX, maksimal 10 MB.</small></div><div class="form-group"><label for="komentar">Catatan Ringkas <span class="optional-label">Opsional</span></label><textarea id="komentar" name="komentar" maxlength="5000" placeholder="Contoh: Komentar utama ada pada Bab I halaman 3–5. Perhatikan rumusan masalah."></textarea><small class="field-help"><span data-character-count>0</span>/5.000 karakter</small></div><div class="form-group"><label for="tipe_revisi">Tingkat Revisi</label><select id="tipe_revisi" name="tipe_revisi" required><option value="minor">Minor — perbaikan kecil</option><option value="major">Major — perubahan signifikan</option><option value="kritis">Kritis — perubahan mendesak</option></select></div><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Batal</button><button type="submit" class="btn btn-primary">Kirim Catatan Revisi</button></div></form></dialog>
<dialog class="dashboard-dialog photo-dialog" id="photo-dialog" aria-labelledby="photo-dialog-title"><button class="dialog-close photo-dialog-close" type="button" data-dialog-close aria-label="Tutup popup">&times;</button><div class="photo-dialog-content"><div class="student-photo-large" data-photo-frame><img data-photo-image alt="" hidden><span data-photo-initials aria-hidden="true"></span></div><h2 id="photo-dialog-title" data-photo-title>Foto mahasiswa</h2></div></dialog>
<?php if($role==='dosen'): ?><dialog class="dashboard-dialog profile-edit-dialog" id="profile-edit-dialog" aria-labelledby="profile-edit-title"><div class="dialog-head"><div><span class="eyebrow">AKUN DOSEN</span><h2 id="profile-edit-title">Edit Profil</h2><p>Perbarui nama, email, atau sandi akun Anda.</p></div><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup popup">&times;</button></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="update_profile"><div class="form-grid"><div class="form-group"><label for="profile_name">Nama Lengkap</label><input id="profile_name" name="nama_lengkap" autocomplete="name" minlength="2" maxlength="150" value="<?=h($user['nama_lengkap'])?>" required></div><div class="form-group"><label for="profile_email">Email</label><input id="profile_email" name="email" type="email" autocomplete="email" maxlength="100" value="<?=h($user['email'])?>" required></div></div><fieldset class="profile-security"><legend>Keamanan akun</legend><div class="form-group"><label for="profile_current_password">Sandi Saat Ini</label><div class="password-field"><input id="profile_current_password" name="current_password" type="password" autocomplete="current-password" required><button type="button" data-password-toggle aria-controls="profile_current_password">Lihat</button></div><small class="field-help">Wajib diisi untuk menyimpan perubahan.</small></div><div class="form-grid"><div class="form-group"><label for="profile_new_password">Sandi Baru</label><div class="password-field"><input id="profile_new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="72"><button type="button" data-password-toggle aria-controls="profile_new_password">Lihat</button></div><small class="field-help">Kosongkan jika sandi tidak diubah.</small></div><div class="form-group"><label for="profile_confirm_password">Konfirmasi Sandi Baru</label><div class="password-field"><input id="profile_confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72"><button type="button" data-password-toggle aria-controls="profile_confirm_password">Lihat</button></div></div></div></fieldset><div class="dialog-actions"><button type="button" class="btn btn-secondary" data-dialog-close>Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div></form></dialog><?php endif; ?>
<?php else: ?>
<section class="stats-grid" aria-label="Ringkasan administrator"><article><span>Mahasiswa</span><strong><?=count($students)?></strong><small>Akun mahasiswa</small></article><article><span>Dosen</span><strong><?=count($lecturers)?></strong><small>Akun dosen</small></article><article><span>Bimbingan</span><strong><?=count($guidances)?></strong><small><?=($periodFilter||$prodiFilter)?'Sesuai filter ('.$allGuidanceCount.' total)':'Seluruh relasi'?></small></article></section>
<?php if(!$academicReady): ?><div class="alert alert-warning" role="status">Fitur periode akademik, program studi, dan NIM belum aktif. Jalankan file <code>migrations/20260923_add_academic_periods.sql</code> melalui phpMyAdmin.</div><?php elseif(!$activePeriod||!$prodiList): ?><div class="alert alert-warning" role="status">Lengkapi pengaturan awal: <?=!$activePeriod?'tentukan periode aktif':''?><?=(!$activePeriod&&!$prodiList)?' dan ':''?><?=!$prodiList?'tambahkan program studi (pendaftaran mahasiswa ditutup sampai prodi tersedia)':''?>.</div><?php endif; ?>
<nav class="quick-actions" aria-label="Akses cepat administrator"><a href="#kelola-dosen"><span>01</span><div><strong>Tambah dosen</strong><small>Buat akun dosen pembimbing</small></div></a><a href="#kelola-bimbingan"><span>02</span><div><strong>Buat bimbingan</strong><small>Hubungkan mahasiswa dan dosen</small></div></a><?php if($academicReady): ?><a href="#periode-akademik"><span>03</span><div><strong>Periode &amp; prodi</strong><small>Tahun ajaran dan program studi</small></div></a><?php endif; ?></nav>
<div class="admin-grid"><section class="card" id="kelola-dosen"><div class="section-heading"><span class="eyebrow">AKUN DOSEN</span><h2>Tambah Dosen</h2><p>Akun dosen hanya dapat dibuat oleh administrator.</p></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create_lecturer"><div class="form-group"><label for="nama_dosen">Nama Lengkap</label><input id="nama_dosen" name="nama_lengkap" maxlength="150" required></div><div class="form-grid"><div class="form-group"><label for="username_dosen">Username</label><input id="username_dosen" name="username" pattern="[A-Za-z0-9._-]{4,100}" required></div><div class="form-group"><label for="email_dosen">Email</label><input id="email_dosen" name="email" type="email" required></div></div><div class="form-group"><label for="password_dosen">Password Awal</label><div class="password-field"><input id="password_dosen" name="password" type="password" minlength="8" maxlength="72" required><button type="button" data-password-toggle aria-controls="password_dosen">Lihat</button></div><small class="field-help">Minimal 8 karakter. Sampaikan password melalui saluran pribadi.</small></div><button class="btn btn-primary" type="submit">Buat Akun Dosen</button></form></section>
<section class="card" id="kelola-bimbingan"><div class="section-heading"><span class="eyebrow">PENUGASAN</span><h2>Buat Bimbingan</h2><p>Hubungkan mahasiswa dengan dosen dan judul skripsinya.</p></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create_guidance"><div class="form-grid"><div class="form-group"><label for="mahasiswa_id">Mahasiswa</label><select id="mahasiswa_id" name="mahasiswa_id" required><option value="">Pilih mahasiswa</option><?php foreach($students as $student): ?><option value="<?=(int)$student['id']?>"><?=h($student['nama_lengkap'].' — '.(!empty($student['nim'])?$student['nim']:$student['email']))?></option><?php endforeach; ?></select></div><div class="form-group"><label for="dosen_id">Dosen Pembimbing</label><select id="dosen_id" name="dosen_id" required><option value="">Pilih dosen</option><?php foreach($lecturers as $lecturer): ?><option value="<?=(int)$lecturer['id']?>"><?=h($lecturer['nama_lengkap'].' — '.$lecturer['email'])?></option><?php endforeach; ?></select></div></div><?php if($academicReady): ?><div class="form-group"><label for="periode_bimbingan">Periode Akademik</label><select id="periode_bimbingan" name="periode_id" required><option value="">Pilih periode</option><?php foreach($periods as $period): ?><option value="<?=(int)$period['id']?>"<?=((int)$period['is_aktif'])?' selected':''?>><?=h(period_label($period))?><?=((int)$period['is_aktif'])?' (aktif)':''?></option><?php endforeach; ?></select></div><?php endif; ?><div class="form-group"><label for="judul_skripsi">Judul Skripsi</label><input id="judul_skripsi" name="judul_skripsi" maxlength="255" required></div><div class="form-group"><label for="deskripsi">Deskripsi Singkat</label><textarea id="deskripsi" name="deskripsi" maxlength="2000" placeholder="Fokus atau ruang lingkup penelitian (opsional)."></textarea></div><button class="btn btn-primary" type="submit" <?=(!$students||!$lecturers)?'disabled':''?>>Simpan Bimbingan</button></form></section></div>
<?php if($academicReady): [$suggestedYear,$suggestedSemester]=suggested_period();$currentYear=(int)date('Y'); ?><div class="admin-grid"><section class="card" id="periode-akademik"><div class="section-heading"><span class="eyebrow">PERIODISASI</span><h2>Periode Akademik</h2><p>Periode aktif menjadi pilihan bawaan saat membuat bimbingan baru.</p></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create_period"><div class="form-grid"><div class="form-group"><label for="tahun_ajaran">Tahun Ajaran</label><select id="tahun_ajaran" name="tahun_ajaran" required><?php for($year=$currentYear+1;$year>=$currentYear-5;$year--): $option=$year.'/'.($year+1); ?><option value="<?=h($option)?>"<?=$option===$suggestedYear?' selected':''?>><?=h($option)?></option><?php endfor; ?></select></div><div class="form-group"><label for="semester">Semester</label><select id="semester" name="semester" required><option value="ganjil"<?=$suggestedSemester==='ganjil'?' selected':''?>>Ganjil</option><option value="genap"<?=$suggestedSemester==='genap'?' selected':''?>>Genap</option></select></div></div><label class="checkbox-line"><input type="checkbox" name="jadikan_aktif" value="1" checked> Jadikan periode aktif</label><button class="btn btn-primary" type="submit">Tambah Periode</button></form><?php if($periods): ?><ul class="setting-list"><?php foreach($periods as $period): ?><li><span><?=h(period_label($period))?></span><?php if((int)$period['is_aktif']): ?><span class="badge badge-success">Aktif</span><?php else: ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="activate_period"><input type="hidden" name="periode_id" value="<?=(int)$period['id']?>"><button class="btn btn-secondary btn-small" type="submit">Jadikan aktif</button></form><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></section>
<section class="card" id="program-studi"><div class="section-heading"><span class="eyebrow">MASTER DATA</span><h2>Program Studi</h2><p>Daftar ini muncul sebagai pilihan saat mahasiswa mendaftar.</p></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create_prodi"><div class="form-group"><label for="nama_prodi">Nama Program Studi</label><input id="nama_prodi" name="nama_prodi" minlength="3" maxlength="150" placeholder="Contoh: Pendidikan Guru PAUD" required></div><div class="form-grid"><div class="form-group"><label for="jenjang">Jenjang</label><select id="jenjang" name="jenjang" required><?php foreach(['S1','S2','S3','D3','D4'] as $level): ?><option value="<?=$level?>"><?=$level?></option><?php endforeach; ?></select></div><div class="form-group"><label for="kode_prodi">Kode <span class="optional-label">Opsional</span></label><input id="kode_prodi" name="kode_prodi" maxlength="20" placeholder="Contoh: PGPAUD"></div></div><button class="btn btn-primary" type="submit">Tambah Program Studi</button></form><?php if($prodiList): ?><ul class="setting-list"><?php foreach($prodiList as $prodi): ?><li><span><?=h(prodi_label($prodi))?></span><?php if($prodi['kode']): ?><small class="table-muted"><?=h($prodi['kode'])?></small><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></section></div>
<section class="card" id="data-mahasiswa"><div class="section-heading"><span class="eyebrow">DATA MAHASISWA</span><h2>Perbarui Data Akademik Mahasiswa</h2><p>Gunakan untuk memperbaiki NIM, program studi, atau angkatan mahasiswa.<?php $missingAcademic=count(array_filter($students,function($student){return empty($student['nim'])||empty($student['prodi_id'])||empty($student['angkatan']);})); if($missingAcademic): ?> <strong><?=$missingAcademic?> mahasiswa</strong> belum melengkapi data akademik.<?php endif; ?></p></div><?php if($students&&$prodiList): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="update_student_academic"><div class="form-group"><label for="data_mahasiswa_id">Mahasiswa</label><select id="data_mahasiswa_id" name="mahasiswa_id" required><option value="">Pilih mahasiswa</option><?php foreach($students as $student): ?><option value="<?=(int)$student['id']?>" data-nim="<?=h($student['nim']??'')?>" data-prodi="<?=(int)($student['prodi_id']??0)?>" data-angkatan="<?=(int)($student['angkatan']??0)?>"><?=h($student['nama_lengkap'].' — '.$student['email'].(empty($student['nim'])?' (belum lengkap)':''))?></option><?php endforeach; ?></select></div><div class="form-grid"><div class="form-group"><label for="data_nim">NIM</label><input id="data_nim" name="nim" minlength="5" maxlength="30" required autocomplete="off"></div><div class="form-group"><label for="data_angkatan">Tahun Angkatan</label><select id="data_angkatan" name="angkatan" required><option value="">Pilih angkatan</option><?php foreach(angkatan_options() as $year): ?><option value="<?=(int)$year?>"><?=(int)$year?></option><?php endforeach; ?></select></div></div><div class="form-group"><label for="data_prodi">Program Studi</label><select id="data_prodi" name="prodi_id" required><option value="">Pilih program studi</option><?php foreach($prodiList as $prodi): ?><option value="<?=(int)$prodi['id']?>"><?=h(prodi_label($prodi))?></option><?php endforeach; ?></select></div><button class="btn btn-primary" type="submit">Simpan Data Akademik</button></form><?php else: ?><div class="empty-state"><strong>Belum dapat diperbarui.</strong><span>Pastikan sudah ada akun mahasiswa dan program studi.</span></div><?php endif; ?></section>
<?php endif; ?>
<section class="card" id="daftar-bimbingan"><div class="section-heading"><span class="eyebrow">DATA AKTIF</span><h2>Daftar Bimbingan</h2><p>Relasi mahasiswa, dosen, judul, periode, dan status terkini.</p></div><?php if($academicReady&&$periods): ?><?=$renderFilter('daftar-bimbingan',true)?><?php endif; ?><?php if($guidances): ?><div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Dosen Pembimbing</th><th>Judul Skripsi</th><?php if($academicReady): ?><th>Periode</th><?php endif; ?><th>Status</th></tr></thead><tbody><?php foreach($guidances as $item): ?><tr><td><?=h($item['mahasiswa_nama'])?><?php if($academicReady&&$studentAcademicLine($item)!==''): ?><small class="table-subtext"><?=h($studentAcademicLine($item))?></small><?php endif; ?></td><td><?=h($item['dosen_nama'])?></td><td><strong><?=h($item['judul_skripsi'])?></strong><a class="table-link" href="?action=kartu&amp;id=<?=(int)$item['id']?>" target="_blank" rel="noopener">Kartu bimbingan</a></td><?php if($academicReady): ?><td><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="move_guidance_period"><input type="hidden" name="bimbingan_id" value="<?=(int)$item['id']?>"><input type="hidden" name="filter_periode" value="<?=$periodFilter?(int)$periodFilter:''?>"><input type="hidden" name="filter_prodi" value="<?=(int)$prodiFilter?>"><label class="sr-only" for="pindah_periode_<?=(int)$item['id']?>">Periode bimbingan</label><select id="pindah_periode_<?=(int)$item['id']?>" name="periode_id"><?php if(empty($item['periode_id'])): ?><option value="">Belum ditentukan</option><?php endif; ?><?php foreach($periods as $period): ?><option value="<?=(int)$period['id']?>"<?=(int)($item['periode_id']??0)===(int)$period['id']?' selected':''?>><?=h(period_label($period))?></option><?php endforeach; ?></select><button class="btn btn-secondary btn-small" type="submit">Simpan</button></form></td><?php endif; ?><td><?=$statusBadge($item['status'])?></td></tr><?php endforeach; ?></tbody></table></div><?php elseif($allGuidanceCount): ?><div class="empty-state"><strong>Tidak ada bimbingan pada filter ini.</strong><span>Ubah periode atau program studi untuk melihat data lain.</span></div><?php else: ?><div class="empty-state"><strong>Belum ada bimbingan.</strong><span>Tambahkan akun dosen, lalu buat relasi bimbingan pertama.</span></div><?php endif; ?></section>
<?php endif; ?>
