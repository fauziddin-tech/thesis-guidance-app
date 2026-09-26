<?php
/**
 * Tahap proposal dan seminar proposal (kolom bimbingan.seminar_proposal_at, migrasi 20260930_proposal_seminar.sql).
 * Alur: Bab 1–3 disetujui Pembimbing 1
 *   → (bila ada Pembimbing 2) naskah Bab 1–3 diperiksa format penulisannya oleh Pembimbing 2
 *   → mahasiswa mengunggah "Proposal Penelitian" (Bab 1–3 lengkap + instrumen penelitian), direview Pembimbing 1
 *   → proposal disetujui = siap seminar proposal
 *   → Pembimbing 1 menandai seminar proposal selesai → Bab 4 terbuka (hanya dosen, bukan admin).
 * Bimbingan lama yang sudah mengunggah Bab 4 sebelum aturan ini tetap berjalan seperti biasa.
 */
const PROPOSAL_DOC = 'Proposal Penelitian';
const PROPOSAL_TARGET = 91; // kode target unggah untuk proposal penelitian

function proposal_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready === null) $ready = column_exists($db, 'bimbingan', 'seminar_proposal_at');
    return $ready;
}

function proposal_is_doc(string $name): bool
{
    return stripos(trim($name), PROPOSAL_DOC) === 0;
}

function proposal_seminar_date(mysqli $db, int $guidanceId): ?string
{
    if (!proposal_ready($db)) return null;
    if (!array_key_exists($guidanceId, $GLOBALS['proposalSeminarCache'] ?? [])) {
        $row = $db->query('SELECT seminar_proposal_at FROM bimbingan WHERE id=' . $guidanceId)->fetch_assoc();
        $GLOBALS['proposalSeminarCache'][$guidanceId] = $row['seminar_proposal_at'] ?? null;
    }
    return $GLOBALS['proposalSeminarCache'][$guidanceId] ?: null;
}

/**
 * Tahap proposal: terkunci | format (sedang di Pembimbing 2) | terbuka | review | revisi | siap_seminar | selesai | lama.
 * 'lama' = bimbingan yang sudah masuk Bab 4 sebelum tahap proposal diberlakukan.
 */
function proposal_stage(mysqli $db, int $guidanceId): array
{
    $latest = p2_latest_documents($db, $guidanceId);
    $seminar = proposal_seminar_date($db, $guidanceId);
    $proposal = $latest['proposal'];
    if ($seminar) return ['tahap' => 'selesai', 'keterangan' => 'Seminar ' . tanggal_id($seminar), 'versi' => $proposal ? (int)$proposal['versi'] : 0];
    if (!$proposal && ($latest['bab'][4] || $latest['bab'][5])) return ['tahap' => 'lama', 'keterangan' => 'Tahap lama', 'versi' => 0];
    if (!proposal_ready($db) || p2_proposal_chapters_done($db, $guidanceId) < 3) return ['tahap' => 'terkunci', 'keterangan' => 'Belum terbuka', 'versi' => 0];
    if (!$proposal && p2_second_id($db, $guidanceId)) {
        $naskah = $latest['naskah'];
        if (!$naskah) return ['tahap' => 'format', 'keterangan' => 'Unggah naskah untuk P2', 'versi' => 0];
        if ($naskah['status'] === 'menunggu_review') return ['tahap' => 'format', 'keterangan' => 'Diperiksa Pembimbing 2', 'versi' => (int)$naskah['versi']];
        if ($naskah['status'] === 'direvisi') return ['tahap' => 'format', 'keterangan' => 'Revisi format (P2)', 'versi' => (int)$naskah['versi']];
    }
    if (!$proposal) return ['tahap' => 'terbuka', 'keterangan' => 'Siap diunggah', 'versi' => 0];
    $map = ['menunggu_review' => ['review', 'Menunggu review'], 'direvisi' => ['revisi', 'Perlu revisi'], 'disetujui' => ['siap_seminar', 'Siap seminar']];
    [$stage, $label] = $map[$proposal['status']] ?? ['review', 'Menunggu review'];
    return ['tahap' => $stage, 'keterangan' => $label, 'versi' => (int)$proposal['versi']];
}

/** Aturan unggah proposal penelitian, dengan bentuk yang sama seperti chapter_upload_state(). */
function proposal_upload_state(mysqli $db, int $guidanceId): array
{
    $locked = function (string $reason) {return ['bab' => 0, 'nama' => PROPOSAL_DOC, 'versi' => 0, 'alasan' => $reason];};
    if (!proposal_ready($db)) return $locked('Tahap proposal belum diaktifkan administrator.');
    $stage = proposal_stage($db, $guidanceId);
    switch ($stage['tahap']) {
        case 'terkunci': return $locked('Proposal penelitian dibuka setelah Bab 1–3 disetujui Pembimbing 1.');
        case 'format': return $locked('Proposal penelitian dibuka setelah naskah Bab 1–3 disetujui Pembimbing 2 (pemeriksaan format penulisan).');
        case 'terbuka': return ['bab' => PROPOSAL_TARGET, 'nama' => PROPOSAL_DOC, 'versi' => 1, 'alasan' => 'Unggah proposal lengkap: Bab 1–3 beserta instrumen penelitian dalam satu file Word.'];
        case 'revisi': return ['bab' => PROPOSAL_TARGET, 'nama' => PROPOSAL_DOC, 'versi' => $stage['versi'] + 1, 'alasan' => 'Unggah perbaikan proposal penelitian sesuai catatan Pembimbing 1.'];
        case 'review': return $locked('Proposal penelitian versi ' . $stage['versi'] . ' masih menunggu review Pembimbing 1.');
        case 'siap_seminar': return $locked('Proposal penelitian sudah disetujui. Silakan ikuti seminar proposal; Bab 4 terbuka setelah seminar ditandai selesai.');
        default: return $locked('Tahap proposal sudah selesai.');
    }
}

/** Bab 4 hanya terbuka setelah seminar proposal selesai (kecuali bimbingan lama yang sudah memulai Bab 4). */
function proposal_bab4_blocker(mysqli $db, int $guidanceId): string
{
    if (!proposal_ready($db)) return '';
    $stage = proposal_stage($db, $guidanceId)['tahap'];
    if (in_array($stage, ['selesai', 'lama'], true)) return '';
    if ($stage === 'siap_seminar') return 'Bab 4 terbuka setelah seminar proposal ditandai selesai oleh Pembimbing 1.';
    return 'Bab 4 terbuka setelah proposal penelitian disetujui dan seminar proposal selesai.';
}

function proposal_badge(mysqli $db, int $guidanceId): string
{
    if (!proposal_ready($db)) return '';
    $stage = proposal_stage($db, $guidanceId);
    if ($stage['tahap'] === 'siap_seminar') return '<span class="tag-ready">Siap seminar proposal</span>';
    if ($stage['tahap'] === 'selesai') return '<span class="tag-ready is-done">Seminar proposal selesai</span>';
    return '';
}

/** Hanya Pembimbing 1 yang dapat menandai seminar proposal selesai. */
function proposal_mark_seminar(mysqli $db, array $actor, int $guidanceId, string $date): void
{
    if (!proposal_ready($db)) throw new RuntimeException('Tahap proposal belum diaktifkan.');
    $stmt = $db->prepare('SELECT b.id,b.dosen_id,b.mahasiswa_id,b.seminar_proposal_at FROM bimbingan b WHERE b.id=? LIMIT 1');
    $stmt->bind_param('i', $guidanceId);$stmt->execute();$guidance = $stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$guidance) throw new RuntimeException('Bimbingan tidak ditemukan.');
    if ($actor['role'] !== 'dosen' || (int)$guidance['dosen_id'] !== (int)$actor['id']) throw new RuntimeException('Hanya Pembimbing 1 yang dapat menandai seminar proposal.');
    if ($guidance['seminar_proposal_at']) throw new RuntimeException('Seminar proposal sudah ditandai selesai.');
    if (proposal_stage($db, $guidanceId)['tahap'] !== 'siap_seminar') throw new RuntimeException('Seminar proposal dapat ditandai setelah proposal penelitian disetujui Pembimbing 1.');
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $parsed > new DateTime('today')) throw new RuntimeException('Tanggal seminar tidak valid atau melewati hari ini.');
    $stmt = $db->prepare('UPDATE bimbingan SET seminar_proposal_at=? WHERE id=? AND seminar_proposal_at IS NULL');
    $stmt->bind_param('si', $date, $guidanceId);$stmt->execute();$stmt->close();
    unset($GLOBALS['proposalSeminarCache'][$guidanceId]);
    notify_user($db, (int)$guidance['mahasiswa_id'], 'persetujuan', 'Seminar proposal Anda tanggal ' . tanggal_id($date) . ' telah ditandai selesai. Bab 4 kini dapat diunggah.',
        '?page=dashboard#unggah-bab', 'Seminar proposal selesai, Bab 4 terbuka', 'Seminar proposal selesai', 'Unggah Bab 4');
}

/** Pembimbing 1 membatalkan tanda seminar (salah input), selama Bab 4 belum diunggah. */
function proposal_unmark_seminar(mysqli $db, int $lecturerId, int $guidanceId): void
{
    if (!proposal_ready($db)) throw new RuntimeException('Tahap proposal belum diaktifkan.');
    $row = $db->query('SELECT dosen_id FROM bimbingan WHERE id=' . $guidanceId)->fetch_assoc();
    if (!$row || (int)$row['dosen_id'] !== $lecturerId) throw new RuntimeException('Hanya Pembimbing 1 yang dapat membatalkan tanda seminar proposal.');
    if (p2_latest_documents($db, $guidanceId)['bab'][4]) throw new RuntimeException('Bab 4 sudah diunggah, sehingga tanda seminar tidak dapat dibatalkan.');
    $db->query('UPDATE bimbingan SET seminar_proposal_at=NULL WHERE id=' . $guidanceId);
    unset($GLOBALS['proposalSeminarCache'][$guidanceId]);
}

/* ---------------------------------------------------------------------------------------------
 * Kelengkapan dan tata tulis proposal (kolom bab_skripsi.kelengkapan, migrasi 20261001_proposal_checklist.sql).
 * - Mahasiswa mencentang setiap komponen sebelum mengunggah proposal.
 * - Sistem memindai file DOCX dan mencatat judul bagian yang ditemukan (hanya sebagai bantuan).
 * - Pembimbing 1 menilai setiap komponen (Sesuai / Perlu perbaikan) sebelum menyetujui; proposal
 *   hanya dapat disetujui bila semua komponen Sesuai. Komponen yang perlu diperbaiki ikut tertulis
 *   di catatan revisi.
 * ------------------------------------------------------------------------------------------- */
const PROPOSAL_COMPONENTS = [
    'cover' => 'Cover (halaman judul)',
    'kata_pengantar' => 'Kata Pengantar',
    'daftar_isi' => 'Daftar Isi',
    'daftar_tabel' => 'Daftar Tabel',
    'daftar_gambar' => 'Daftar Gambar',
    'bab1' => 'Bab I Pendahuluan',
    'bab2' => 'Bab II Kajian Pustaka',
    'bab3' => 'Bab III Metode Penelitian',
    'daftar_pustaka' => 'Daftar Pustaka',
    'lampiran' => 'Lampiran (instrumen penelitian)',
];
// Bantuan untuk mahasiswa: keterangan singkat tiap komponen di formulir unggah.
const PROPOSAL_COMPONENT_HINTS = [
    'daftar_tabel' => 'Centang juga bila proposal memang tidak memuat tabel.',
    'daftar_gambar' => 'Centang juga bila proposal memang tidak memuat gambar.',
    'lampiran' => 'Minimal kisi-kisi dan instrumen penelitian.',
];

function proposal_checklist_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready === null) $ready = column_exists($db, 'bab_skripsi', 'kelengkapan');
    return $ready;
}

/** Memindai judul bagian di file DOCX. Hasil: [komponen => true|false|null]; null = tidak dapat diperiksa otomatis. */
function proposal_scan_docx(string $path): array
{
    $result = array_fill_keys(array_keys(PROPOSAL_COMPONENTS), null);
    if (!class_exists('ZipArchive')) return $result;
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return $result;
    $xml = (string)$zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === '') return $result;
    $text = preg_replace(['/<\/w:p>/', '/<w:tab\/>/', '/<[^>]+>/'], ["\n", ' ', ''], $xml);
    $text = mb_strtoupper(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
    $patterns = [
        'kata_pengantar' => '/KATA\s+PENGANTAR/u',
        'daftar_isi' => '/DAFTAR\s+ISI/u',
        'daftar_tabel' => '/DAFTAR\s+TABEL/u',
        'daftar_gambar' => '/DAFTAR\s+GAMBAR/u',
        'bab1' => '/\bBAB\s+(I|1)\b/u',
        'bab2' => '/\bBAB\s+(II|2)\b/u',
        'bab3' => '/\bBAB\s+(III|3)\b/u',
        'daftar_pustaka' => '/DAFTAR\s+PUSTAKA|DAFTAR\s+REFERENSI|DAFTAR\s+RUJUKAN|BIBLIOGRAFI/u',
        'lampiran' => '/\bLAMPIRAN\b/u',
    ];
    foreach ($patterns as $key => $pattern) $result[$key] = preg_match($pattern, $text) === 1;
    return $result; // cover tidak dapat dikenali otomatis (tetap null)
}

function proposal_checklist_get(mysqli $db, int $documentId): array
{
    if (!proposal_checklist_ready($db)) return [];
    $row = $db->query('SELECT kelengkapan FROM bab_skripsi WHERE id=' . $documentId)->fetch_assoc();
    $data = json_decode((string)($row['kelengkapan'] ?? ''), true);
    return is_array($data) ? $data : [];
}

function proposal_checklist_decode($raw): array
{
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function proposal_checklist_put(mysqli $db, int $documentId, array $data): void
{
    if (!proposal_checklist_ready($db)) return;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare('UPDATE bab_skripsi SET kelengkapan=? WHERE id=?');
    $stmt->bind_param('si', $json, $documentId);$stmt->execute();$stmt->close();
}

/** Komponen yang belum dicentang mahasiswa. */
function proposal_missing_student_ticks(array $ticked): array
{
    return array_values(array_diff(array_keys(PROPOSAL_COMPONENTS), $ticked));
}

/** Label komponen dengan hasil pemeriksaan dosen tertentu. */
function proposal_components_with(array $checklist, string $result): array
{
    $labels = [];
    foreach (PROPOSAL_COMPONENTS as $key => $label) if (($checklist['dosen']['hasil'][$key] ?? '') === $result) $labels[] = $label;
    return $labels;
}

/** Komponen yang tidak ditemukan pemindaian otomatis. */
function proposal_components_not_found(array $checklist): array
{
    $labels = [];
    foreach (PROPOSAL_COMPONENTS as $key => $label) if (($checklist['otomatis'][$key] ?? null) === false) $labels[] = $label;
    return $labels;
}

function proposal_checklist_complete(array $checklist): bool
{
    foreach (array_keys(PROPOSAL_COMPONENTS) as $key) if (($checklist['dosen']['hasil'][$key] ?? '') !== 'sesuai') return false;
    return true;
}
