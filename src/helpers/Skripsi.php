<?php
/**
 * Tahap akhir: unggah skripsi lengkap (cover sampai lampiran) setelah Bab 1–5 disetujui Pembimbing 1.
 * Alur:
 *   → mahasiswa mengunggah draf "Skripsi Lengkap" (Word DOC/DOCX) dan mencentang kelengkapannya
 *   → sistem memindai judul bagian di file DOCX (bantuan), Pembimbing 1 menilai setiap komponen
 *     (Sesuai / Perlu perbaikan) lalu Setujui atau Revisi, sama seperti proposal penelitian
 *   → setelah disetujui, mahasiswa mengunggah versi akhir "Skripsi Final (PDF)" (wajib PDF).
 *     File PDF tidak direview lagi; mahasiswa dapat menggantinya selama bimbingan masih aktif.
 * Memakai kolom bab_skripsi.kelengkapan (migrasi 20261001_proposal_checklist.sql); tidak ada tabel baru.
 */
const SKRIPSI_DOC = 'Skripsi Lengkap';
const SKRIPSI_TARGET = 92; // kode target unggah untuk draf skripsi lengkap (Word)
const SKRIPSI_PDF_DOC = 'Skripsi Final (PDF)';
const SKRIPSI_PDF_TARGET = 93; // kode target unggah untuk versi akhir PDF

const SKRIPSI_COMPONENTS = [
    'cover' => 'Cover (halaman judul)',
    'pengesahan' => 'Lembar Persetujuan/Pengesahan',
    'pernyataan' => 'Pernyataan Keaslian Skripsi',
    'abstrak' => 'Abstrak',
    'kata_pengantar' => 'Kata Pengantar',
    'daftar_isi' => 'Daftar Isi',
    'daftar_tabel' => 'Daftar Tabel',
    'daftar_gambar' => 'Daftar Gambar',
    'daftar_lampiran' => 'Daftar Lampiran',
    'bab1' => 'Bab I Pendahuluan',
    'bab2' => 'Bab II Kajian Pustaka',
    'bab3' => 'Bab III Metode Penelitian',
    'bab4' => 'Bab IV Hasil Penelitian dan Pembahasan',
    'bab5' => 'Bab V Penutup (Kesimpulan dan Saran)',
    'daftar_pustaka' => 'Daftar Pustaka',
    'lampiran' => 'Lampiran',
];
const SKRIPSI_COMPONENT_HINTS = [
    'pengesahan' => 'Pada draf boleh belum ditandatangani.',
    'abstrak' => 'Termasuk Abstract (bahasa Inggris) bila diwajibkan program studi.',
    'daftar_tabel' => 'Centang juga bila skripsi memang tidak memuat tabel.',
    'daftar_gambar' => 'Centang juga bila skripsi memang tidak memuat gambar.',
    'daftar_lampiran' => 'Daftar seluruh lampiran beserta nomor halamannya.',
    'lampiran' => 'Instrumen, data penelitian, surat izin, dokumentasi, dan lainnya.',
];
const SKRIPSI_SCAN_PATTERNS = [
    'pengesahan' => '/(LEMBAR|HALAMAN)\s+(PERSETUJUAN|PENGESAHAN)|PERSETUJUAN\s+PEMBIMBING|PENGESAHAN\s+(SKRIPSI|PENGUJI)/u',
    'pernyataan' => '/(SURAT|LEMBAR|HALAMAN)\s+PERNYATAAN|PERNYATAAN\s+(KEASLIAN|ORISINALITAS)/u',
    'abstrak' => '/\bABSTRAK\b|\bABSTRACT\b/u',
    'kata_pengantar' => '/KATA\s+PENGANTAR/u',
    'daftar_isi' => '/DAFTAR\s+ISI/u',
    'daftar_tabel' => '/DAFTAR\s+TABEL/u',
    'daftar_gambar' => '/DAFTAR\s+GAMBAR/u',
    'daftar_lampiran' => '/DAFTAR\s+LAMPIRAN/u',
    'bab1' => '/\bBAB\s+(I|1)\b/u',
    'bab2' => '/\bBAB\s+(II|2)\b/u',
    'bab3' => '/\bBAB\s+(III|3)\b/u',
    'bab4' => '/\bBAB\s+(IV|4)\b/u',
    'bab5' => '/\bBAB\s+(V|5)\b/u',
    'daftar_pustaka' => '/DAFTAR\s+PUSTAKA|DAFTAR\s+REFERENSI|DAFTAR\s+RUJUKAN|BIBLIOGRAFI/u',
    'lampiran' => '/\bLAMPIRAN\b/u',
];

function skripsi_is_doc(string $name): bool
{
    return stripos(trim($name), SKRIPSI_DOC) === 0;
}

function skripsi_is_pdf_doc(string $name): bool
{
    return stripos(trim($name), 'Skripsi Final') === 0;
}

/** Komponen kelengkapan menurut jenis dokumen: proposal, skripsi lengkap, atau [] (tanpa daftar periksa). */
function checklist_components_for(string $name): array
{
    if (proposal_is_doc($name)) return PROPOSAL_COMPONENTS;
    if (skripsi_is_doc($name)) return SKRIPSI_COMPONENTS;
    return [];
}

function checklist_doc_label(string $name): string
{
    return skripsi_is_doc($name) ? 'skripsi' : 'proposal';
}

/** Memindai draf skripsi DOCX. Hasil: [komponen => true|false|null]; cover tidak dapat dikenali otomatis. */
function skripsi_scan_docx(string $path): array
{
    return checklist_scan_docx($path, SKRIPSI_COMPONENTS, SKRIPSI_SCAN_PATTERNS);
}

/**
 * Tahap skripsi lengkap: terkunci | terbuka | review | revisi | pdf (draf disetujui, PDF belum diunggah) | selesai.
 */
function skripsi_stage(mysqli $db, int $guidanceId): array
{
    $latest = p2_latest_documents($db, $guidanceId);
    $draft = $latest['skripsi'];
    $final = $latest['final'];
    if (!$draft && ($latest['bab'][5]['status'] ?? '') !== 'disetujui') return ['tahap' => 'terkunci', 'keterangan' => 'Belum terbuka', 'versi' => 0];
    if (!$draft) return ['tahap' => 'terbuka', 'keterangan' => 'Siap diunggah', 'versi' => 0];
    $version = (int)$draft['versi'];
    if ($draft['status'] === 'direvisi') return ['tahap' => 'revisi', 'keterangan' => 'Perlu revisi', 'versi' => $version];
    if ($draft['status'] !== 'disetujui') return ['tahap' => 'review', 'keterangan' => 'Menunggu review', 'versi' => $version];
    if (!$final) return ['tahap' => 'pdf', 'keterangan' => 'Disetujui, unggah PDF', 'versi' => $version, 'versi_pdf' => 0];
    return ['tahap' => 'selesai', 'keterangan' => 'PDF final diunggah', 'versi' => $version, 'versi_pdf' => (int)$final['versi']];
}

/** Aturan unggah draf skripsi lengkap (Word), dengan bentuk yang sama seperti chapter_upload_state(). */
function skripsi_upload_state(mysqli $db, int $guidanceId): array
{
    $locked = function (string $reason) {return ['bab' => 0, 'nama' => SKRIPSI_DOC, 'versi' => 0, 'alasan' => $reason];};
    $stage = skripsi_stage($db, $guidanceId);
    switch ($stage['tahap']) {
        case 'terkunci': return $locked('Skripsi lengkap diunggah setelah Bab 1 sampai Bab 5 disetujui Pembimbing 1.');
        case 'terbuka': return ['bab' => SKRIPSI_TARGET, 'nama' => SKRIPSI_DOC, 'versi' => 1, 'alasan' => 'Bab 1–5 sudah disetujui. Unggah draf skripsi lengkap, mulai dari cover sampai lampiran, dalam satu file Word (DOC/DOCX).'];
        case 'revisi': return ['bab' => SKRIPSI_TARGET, 'nama' => SKRIPSI_DOC, 'versi' => $stage['versi'] + 1, 'alasan' => 'Unggah perbaikan draf skripsi lengkap sesuai catatan Pembimbing 1.'];
        case 'review': return $locked('Draf skripsi lengkap versi ' . $stage['versi'] . ' masih menunggu review Pembimbing 1.');
        default: return $locked('Draf skripsi lengkap sudah disetujui Pembimbing 1.');
    }
}

/** Aturan unggah versi akhir PDF: terbuka setelah draf skripsi lengkap disetujui; boleh diganti selama bimbingan aktif. */
function skripsi_pdf_upload_state(mysqli $db, int $guidanceId): array
{
    $stage = skripsi_stage($db, $guidanceId);
    if ($stage['tahap'] === 'pdf') return ['bab' => SKRIPSI_PDF_TARGET, 'nama' => SKRIPSI_PDF_DOC, 'versi' => 1, 'alasan' => 'Draf skripsi lengkap sudah disetujui. Unggah versi akhir skripsi lengkap (cover sampai lampiran) dalam format PDF.'];
    if ($stage['tahap'] === 'selesai') return ['bab' => SKRIPSI_PDF_TARGET, 'nama' => SKRIPSI_PDF_DOC, 'versi' => $stage['versi_pdf'] + 1, 'alasan' => 'PDF final versi ' . $stage['versi_pdf'] . ' sudah diunggah. Unggah lagi hanya bila perlu mengganti file (mis. salah file atau sudah ditandatangani).'];
    return ['bab' => 0, 'nama' => SKRIPSI_PDF_DOC, 'versi' => 0, 'alasan' => 'PDF final diunggah setelah draf skripsi lengkap disetujui Pembimbing 1.'];
}

/** File benar-benar PDF (diawali tanda %PDF-), bukan file lain yang diganti ekstensinya. */
function skripsi_is_pdf_file(string $path): bool
{
    $handle = @fopen($path, 'rb');
    if (!$handle) return false;
    $header = (string)fread($handle, 1024);
    fclose($handle);
    return strpos($header, '%PDF-') !== false;
}

function skripsi_badge(mysqli $db, int $guidanceId): string
{
    $stage = skripsi_stage($db, $guidanceId)['tahap'];
    if ($stage === 'selesai') return '<span class="tag-ready is-done">PDF skripsi final diunggah</span>';
    if ($stage === 'pdf') return '<span class="tag-ready">Skripsi lengkap disetujui</span>';
    return '';
}
