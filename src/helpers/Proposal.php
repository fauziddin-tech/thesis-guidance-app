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
