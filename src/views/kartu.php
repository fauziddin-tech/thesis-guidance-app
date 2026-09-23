<?php
// Kartu bimbingan siap cetak untuk satu relasi bimbingan (satu mahasiswa dengan satu dosen pembimbing).
if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}
$currentUser = $_SESSION['user'];
$currentUserId = (int)$currentUser['id'];
$currentRole = (string)$currentUser['role'];
$guidanceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$academicReady = academic_ready($conn);
$hasCode = column_exists($conn, 'bimbingan', 'kode_verifikasi');
$hasApprovedAt = column_exists($conn, 'bab_skripsi', 'disetujui_at');

$sql = 'SELECT b.*,m.nama_lengkap AS mahasiswa_nama,d.nama_lengkap AS dosen_nama'
    . ($academicReady ? ',m.nim,m.angkatan,ps.nama AS prodi_nama,ps.jenjang AS prodi_jenjang,p.tahun_ajaran,p.semester' : '')
    . ' FROM bimbingan b JOIN users m ON b.mahasiswa_id=m.id JOIN users d ON b.dosen_id=d.id'
    . ($academicReady ? ' LEFT JOIN program_studi ps ON m.prodi_id=ps.id LEFT JOIN periode_akademik p ON b.periode_id=p.id' : '')
    . ' WHERE b.id=? LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $guidanceId);
$stmt->execute();
$guidance = $stmt->get_result()->fetch_assoc();
$stmt->close();

$allowed = $guidance && (
    $currentRole === 'admin'
    || ($currentRole === 'mahasiswa' && (int)$guidance['mahasiswa_id'] === $currentUserId)
    || ($currentRole === 'dosen' && p2_is_member($conn, (int)$guidance['id'], $currentUserId))
);
if (!$allowed) {
    http_response_code(404);
    exit('Kartu bimbingan tidak ditemukan atau Anda tidak memiliki akses.');
}

// Kode verifikasi dibuat sekali, saat kartu pertama kali dibuka.
$verificationCode = $hasCode ? (string)($guidance['kode_verifikasi'] ?? '') : '';
if ($hasCode && $verificationCode === '') {
    for ($attempt = 0; $attempt < 3 && $verificationCode === ''; $attempt++) {
        $candidate = implode('-', str_split(strtoupper(bin2hex(random_bytes(6))), 4));
        $stmt = $conn->prepare('UPDATE bimbingan SET kode_verifikasi=? WHERE id=? AND kode_verifikasi IS NULL');
        $stmt->bind_param('si', $candidate, $guidanceId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('SELECT kode_verifikasi FROM bimbingan WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $guidanceId);
        $stmt->execute();
        $verificationCode = (string)($stmt->get_result()->fetch_assoc()['kode_verifikasi'] ?? '');
        $stmt->close();
    }
}
$verificationUrl = $verificationCode !== '' ? app_base_url() . '?page=verifikasi&kode=' . rawurlencode($verificationCode) : '';

$stmt = $conn->prepare('SELECT id,nama_bab,versi,status,uploaded_at' . ($hasApprovedAt ? ',disetujui_at' : ',NULL AS disetujui_at') . ' FROM bab_skripsi WHERE bimbingan_id=? ORDER BY uploaded_at,id');
$stmt->bind_param('i', $guidanceId);
$stmt->execute();
$chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare('SELECT r.id,r.komentar,r.tipe_revisi,r.created_at,u.nama_lengkap AS dosen_nama,bs.nama_bab,bs.versi FROM revisi r JOIN bab_skripsi bs ON r.bab_id=bs.id JOIN users u ON r.dosen_id=u.id WHERE bs.bimbingan_id=? ORDER BY r.created_at,r.id');
$stmt->bind_param('i', $guidanceId);
$stmt->execute();
$revisions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Tim pembimbing, keputusan ACC tiap dosen, dan riwayat review judul.
$team = p2_team($conn, $guidanceId);
$chapterIds = array_map('intval', array_column($chapters, 'id'));
$approvalsByChapter = [];
if ($chapterIds && p2_ready($conn)) {
    $approvalResult = $conn->query("SELECT r.objek_id,r.updated_at,u.nama_lengkap FROM mythesis_review_dosen r JOIN users u ON u.id=r.dosen_id WHERE r.jenis='bab' AND r.keputusan='disetujui' AND r.objek_id IN (" . implode(',', $chapterIds) . ")");
    while ($approvalResult && ($approval = $approvalResult->fetch_assoc())) $approvalsByChapter[(int)$approval['objek_id']][] = $approval;
}
$titleHistory = title_history_by_guidance($conn, [$guidanceId])[$guidanceId] ?? [];

// Riwayat kegiatan dari seluruh data yang tercatat.
$events = [];
$events[] = ['time' => $guidance['created_at'], 'order' => 0, 'activity' => 'Penetapan dosen pembimbing dan judul skripsi', 'note' => '', 'actor' => 'Administrator'];
foreach ($chapters as $chapter) {
    $events[] = ['time' => $chapter['uploaded_at'], 'order' => 1, 'activity' => 'Mengunggah ' . $chapter['nama_bab'] . ' (versi ' . (int)$chapter['versi'] . ')', 'note' => '', 'actor' => $guidance['mahasiswa_nama']];
    if (!empty($approvalsByChapter[(int)$chapter['id']])) {
        foreach ($approvalsByChapter[(int)$chapter['id']] as $approval) $events[] = ['time' => $approval['updated_at'], 'order' => 3, 'activity' => 'ACC ' . $chapter['nama_bab'] . ' (versi ' . (int)$chapter['versi'] . ')', 'note' => '', 'actor' => $approval['nama_lengkap']];
    } elseif ($chapter['status'] === 'disetujui' && $chapter['disetujui_at']) {
        $events[] = ['time' => $chapter['disetujui_at'], 'order' => 3, 'activity' => 'Menyetujui ' . $chapter['nama_bab'] . ' (versi ' . (int)$chapter['versi'] . ')', 'note' => '', 'actor' => $guidance['dosen_nama']];
    }
}
$defaultComment = 'Komentar revisi dituliskan langsung di dalam file terlampir.';
foreach ($revisions as $revision) {
    $note = trim((string)$revision['komentar']);
    if ($note === $defaultComment) $note = 'Komentar di dalam file revisi.';
    if (mb_strlen($note) > 220) $note = mb_substr($note, 0, 217) . '…';
    $events[] = ['time' => $revision['created_at'], 'order' => 2, 'activity' => 'Revisi ' . $revision['tipe_revisi'] . ' — ' . $revision['nama_bab'] . ' (versi ' . (int)$revision['versi'] . ')', 'note' => $note, 'actor' => $revision['dosen_nama']];
}
$titleLabels = ['Revisi Pengajuan Judul' => 'Revisi judul diminta', 'Persetujuan Pengajuan Judul' => 'Judul disetujui', 'Pengajuan Ulang Judul' => 'Judul diajukan ulang'];
foreach ($titleHistory as $review) {
    $note = trim((string)($review['jawaban'] ?? ''));
    if (mb_strlen($note) > 220) $note = mb_substr($note, 0, 217) . '…';
    $events[] = ['time' => $review['created_at'], 'order' => 1, 'activity' => $titleLabels[$review['topik']] ?? $review['topik'], 'note' => $note, 'actor' => $review['reviewer_nama'] ?: $guidance['mahasiswa_nama']];
}
usort($events, function (array $first, array $second): int {
    return strcmp((string)$first['time'], (string)$second['time']) ?: $first['order'] <=> $second['order'];
});

// Rekap: versi terakhir dari setiap bab.
$chapterSummary = [];
foreach ($chapters as $chapter) {
    $key = mb_strtolower(trim((string)$chapter['nama_bab']));
    if (!isset($chapterSummary[$key]) || (int)$chapter['versi'] >= (int)$chapterSummary[$key]['versi']) $chapterSummary[$key] = $chapter;
}
$lecturerResponses = count($revisions) + ($approvalsByChapter ? array_sum(array_map('count', $approvalsByChapter)) : count(array_filter($chapters, function ($chapter) {return $chapter['status'] === 'disetujui';}))) + count(array_filter($titleHistory, function ($review) {return $review['topik'] !== 'Pengajuan Ulang Judul';}));
$approvedChapters = count(array_filter($chapterSummary, function ($chapter) {return $chapter['status'] === 'disetujui';}));

$prodiText = $academicReady ? prodi_label($guidance, 'prodi_nama', 'prodi_jenjang') : '';
$logoExists = is_file(__DIR__ . '/../../logo.png');
$studentPhotoUrl = '';
foreach (['jpg', 'png', 'webp'] as $photoExtension) {
    $photoPath = __DIR__ . '/../../storage/avatars/user-' . (int)$guidance['mahasiswa_id'] . '.' . $photoExtension;
    if (is_file($photoPath)) {$studentPhotoUrl = '?action=avatar&id=' . (int)$guidance['mahasiswa_id'] . '&v=' . filemtime($photoPath);break;}
}
$backUrl = '?page=dashboard';
$fileName = 'Kartu-Bimbingan-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string)($guidance['nim'] ?? $guidance['mahasiswa_nama']));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?=h($fileName)?></title>
<?php if($logoExists): ?><link rel="icon" type="image/png" href="logo.png"><?php endif; ?>
<style>
@page{size:A4;margin:16mm 14mm}
*{box-sizing:border-box}
body{margin:0;background:#E9ECF0;color:#18212F;font:13px/1.5 "Segoe UI",Arial,sans-serif}
.toolbar{position:sticky;top:0;z-index:5;display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-items:center;padding:12px;background:#18212F;color:#fff}
.toolbar button,.toolbar a{padding:9px 16px;border:0;border-radius:7px;background:#7B4654;color:#fff;font:600 14px "Segoe UI",Arial,sans-serif;text-decoration:none;cursor:pointer}
.toolbar a{background:#3A4456}
.toolbar span{font-size:13px;opacity:.8}
.sheet{width:210mm;min-height:297mm;margin:20px auto;padding:16mm 14mm;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.12)}
.head{display:flex;align-items:center;gap:14px;padding-bottom:12px;border-bottom:3px double #18212F}
.head img{width:58px;height:58px;object-fit:contain}
.head h1{margin:0;font-size:19px;letter-spacing:.06em}
.head p{margin:2px 0 0;color:#5C6878}
.identity-row{display:flex;align-items:flex-start;gap:8mm;margin:14px 0 6px}
.identity{flex:1;width:100%;border-collapse:collapse}
.photo{flex:none;display:flex;align-items:center;justify-content:center;width:30mm;height:40mm;overflow:hidden;border:1px solid #B9C1CC;background:#F7F8FA;color:#8A94A3;font-size:11px;text-align:center}
.photo img{width:100%;height:100%;object-fit:cover}
.identity td{padding:3px 0;vertical-align:top}
.identity td:first-child{width:36mm;color:#5C6878}
.identity td:nth-child(2){width:5mm}
h2{margin:18px 0 6px;font-size:14px}
table.data{width:100%;border-collapse:collapse}
table.data th,table.data td{padding:6px 7px;border:1px solid #B9C1CC;text-align:left;vertical-align:top}
table.data th{background:#F1F3F6;font-size:12px}
table.data td.num{width:9mm;text-align:center}
table.data td.date{width:30mm;white-space:nowrap}
.note{display:block;margin-top:2px;color:#5C6878;font-size:12px}
.summary{display:flex;gap:10px;margin-top:10px}
.summary div{flex:1;padding:8px 10px;border:1px solid #D5DBE2;border-radius:6px}
.summary strong{display:block;font-size:17px}
.summary span{color:#5C6878;font-size:12px}
.foot{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-top:26px;page-break-inside:avoid}
.verify{display:flex;gap:10px;align-items:flex-start;max-width:105mm;font-size:11px;color:#3A4456}
.verify .qr{width:26mm;height:26mm;flex:none}
.verify .qr svg{width:100%;height:100%}
.verify code{font-size:12px;font-weight:700;color:#18212F}
.signs{display:flex;gap:10mm}.sign{width:55mm;text-align:center}
.sign .space{height:22mm}
.sign strong{display:block;border-top:1px solid #18212F;padding-top:3px}
.empty{padding:10px;border:1px dashed #B9C1CC;color:#5C6878;text-align:center}
@media print{body{background:#fff}.toolbar{display:none}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}tr{page-break-inside:avoid}}
@media(max-width:820px){.sheet{width:auto;min-height:0;margin:0;padding:18px}.identity-row{flex-direction:column-reverse;align-items:center}.foot,.summary{flex-direction:column;align-items:stretch}.sign{width:auto}}
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button><a href="<?=h($backUrl)?>">Kembali ke Dashboard</a><span>Pada jendela cetak, pilih tujuan "Simpan sebagai PDF".</span></div>
<main class="sheet">
    <header class="head">
        <?php if($logoExists): ?><img src="logo.png" alt=""><?php endif; ?>
        <div><h1>KARTU BIMBINGAN SKRIPSI</h1><p>MyThesis — Ruang kerja bimbingan skripsi</p></div>
    </header>

    <div class="identity-row">
    <table class="identity">
        <tr><td>Nama Mahasiswa</td><td>:</td><td><strong><?=h($guidance['mahasiswa_nama'])?></strong></td></tr>
        <?php if($academicReady): ?>
        <tr><td>NIM</td><td>:</td><td><?=h($guidance['nim'] ?: '—')?></td></tr>
        <tr><td>Program Studi</td><td>:</td><td><?=h($prodiText !== '' ? $prodiText : '—')?></td></tr>
        <tr><td>Angkatan</td><td>:</td><td><?=h($guidance['angkatan'] ?: '—')?></td></tr>
        <tr><td>Periode Mulai</td><td>:</td><td><?=h(period_label($guidance))?></td></tr>
        <?php endif; ?>
        <tr><td>Judul Skripsi</td><td>:</td><td><?=h($guidance['judul_skripsi'])?></td></tr>
        <?php foreach($team as $teacher): ?><tr><td>Pembimbing <?=count($team)>1?(int)$teacher['urutan']:''?></td><td>:</td><td><?=h($teacher['nama_lengkap'])?></td></tr><?php endforeach; ?>
        <tr><td>Status Bimbingan</td><td>:</td><td><?=h(status_label((string)$guidance['status']))?></td></tr>
    </table>
    <div class="photo"><?php if($studentPhotoUrl !== ''): ?><img src="<?=h($studentPhotoUrl)?>" alt="Foto <?=h($guidance['mahasiswa_nama'])?>"><?php else: ?><span>Pas foto<br>3 × 4</span><?php endif; ?></div>
    </div>

    <div class="summary">
        <div><strong><?=$lecturerResponses?></strong><span>Tanggapan dosen (revisi &amp; persetujuan)</span></div>
        <div><strong><?=count($chapters)?></strong><span>Dokumen diunggah</span></div>
        <div><strong><?=$approvedChapters?> / <?=count($chapterSummary)?></strong><span>Bab disetujui</span></div>
    </div>

    <h2>Rekap Bab</h2>
    <?php if($chapterSummary): ?>
    <table class="data"><thead><tr><th>No</th><th>Bab</th><th>Versi Terakhir</th><th>Status</th><th>Tanggal Disetujui</th></tr></thead><tbody>
    <?php $number = 1; foreach($chapterSummary as $chapter): ?>
        <tr><td class="num"><?=$number++?></td><td><?=h($chapter['nama_bab'])?></td><td>v<?=(int)$chapter['versi']?></td><td><?=h(status_label((string)$chapter['status']))?></td><td><?=$chapter['status'] === 'disetujui' ? h($chapter['disetujui_at'] ? tanggal_id($chapter['disetujui_at']) : 'Tidak tercatat') : '—'?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="empty">Belum ada dokumen yang diunggah.</div><?php endif; ?>

    <h2>Riwayat Bimbingan</h2>
    <table class="data"><thead><tr><th>No</th><th>Tanggal</th><th>Kegiatan</th><th>Oleh</th></tr></thead><tbody>
    <?php $number = 1; foreach($events as $event): ?>
        <tr><td class="num"><?=$number++?></td><td class="date"><?=h(tanggal_id($event['time'], true))?></td><td><?=h($event['activity'])?><?php if($event['note'] !== ''): ?><span class="note"><?=h($event['note'])?></span><?php endif; ?></td><td><?=h($event['actor'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table>

    <footer class="foot">
        <div class="verify">
            <?php if($verificationCode !== ''): ?>
            <div class="qr" data-qr="<?=h($verificationUrl)?>"></div>
            <div>Kartu ini dibuat otomatis oleh MyThesis pada <?=h(tanggal_id(date('Y-m-d H:i:s'), true))?>.<br>Kode verifikasi: <code><?=h($verificationCode)?></code><br>Pindai QR atau buka <?=h(app_base_url())?>?page=verifikasi untuk memeriksa keaslian kartu.</div>
            <?php else: ?>
            <div>Kartu ini dibuat otomatis oleh MyThesis pada <?=h(tanggal_id(date('Y-m-d H:i:s'), true))?>.</div>
            <?php endif; ?>
        </div>
        <div class="signs"><?php foreach($team as $teacher): ?><div class="sign"><?=count($team)>1?'Pembimbing '.(int)$teacher['urutan']:'Dosen Pembimbing'?>,<div class="space"></div><strong><?=h($teacher['nama_lengkap'])?></strong></div><?php endforeach; ?></div>
    </footer>
</main>
<?php if($verificationCode !== ''): ?>
<script src="public/js/vendor/qrcode.js"></script>
<script>
document.querySelectorAll('[data-qr]').forEach(function (box) {
    try {
        var qr = qrcode(0, 'M');
        qr.addData(box.getAttribute('data-qr'));
        qr.make();
        box.innerHTML = qr.createSvgTag({cellSize: 3, margin: 0, scalable: true});
    } catch (error) {
        box.style.display = 'none';
    }
});
</script>
<?php endif; ?>
</body>
</html>
