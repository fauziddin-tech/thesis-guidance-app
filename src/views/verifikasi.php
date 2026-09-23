<?php
// Halaman publik untuk memeriksa keaslian kartu bimbingan melalui kode verifikasi.
$code = strtoupper(trim((string)($_GET['kode'] ?? '')));
$code = preg_replace('/[^A-F0-9-]/', '', $code);
$hasCode = column_exists($conn, 'bimbingan', 'kode_verifikasi');
$academicReady = academic_ready($conn);
$result = null;
$stats = null;

if ($hasCode && $code !== '') {
    $sql = 'SELECT b.id,b.judul_skripsi,b.status,b.created_at,m.nama_lengkap AS mahasiswa_nama,d.nama_lengkap AS dosen_nama'
        . ($academicReady ? ',m.nim,m.angkatan,ps.nama AS prodi_nama,ps.jenjang AS prodi_jenjang,p.tahun_ajaran,p.semester' : '')
        . ' FROM bimbingan b JOIN users m ON b.mahasiswa_id=m.id JOIN users d ON b.dosen_id=d.id'
        . ($academicReady ? ' LEFT JOIN program_studi ps ON m.prodi_id=ps.id LEFT JOIN periode_akademik p ON b.periode_id=p.id' : '')
        . ' WHERE b.kode_verifikasi=? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($result) {
        $guidanceId = (int)$result['id'];
        $stmt = $conn->prepare("SELECT COUNT(*) AS dokumen,SUM(status='disetujui') AS disetujui,MAX(uploaded_at) AS terakhir_unggah FROM bab_skripsi WHERE bimbingan_id=?");
        $stmt->bind_param('i', $guidanceId);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stmt = $conn->prepare('SELECT COUNT(*) AS revisi,MAX(r.created_at) AS terakhir_revisi FROM revisi r JOIN bab_skripsi bs ON r.bab_id=bs.id WHERE bs.bimbingan_id=?');
        $stmt->bind_param('i', $guidanceId);
        $stmt->execute();
        $stats += $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$maskNim = function (?string $nim): string {
    $nim = (string)$nim;
    if ($nim === '') return '—';
    if (strlen($nim) <= 6) return substr($nim, 0, 2) . str_repeat('•', max(0, strlen($nim) - 2));
    return substr($nim, 0, 4) . str_repeat('•', strlen($nim) - 6) . substr($nim, -2);
};
$lastActivity = $stats ? max((string)$stats['terakhir_unggah'], (string)$stats['terakhir_revisi']) : '';
$logoExists = is_file(__DIR__ . '/../../logo.png');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Verifikasi Kartu Bimbingan | MyThesis</title>
<?php if($logoExists): ?><link rel="icon" type="image/png" href="logo.png"><?php endif; ?>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:#F4F6F8;color:#18212F;font:15px/1.55 "Segoe UI",Arial,sans-serif}
.wrap{max-width:620px;margin:0 auto;padding:32px 18px}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:18px;color:#18212F;font-weight:800;font-size:18px;text-decoration:none}
.brand img{width:40px;height:40px;object-fit:contain}
.card{padding:24px;border:1px solid #E1E5EA;border-radius:12px;background:#fff;box-shadow:0 10px 30px rgba(24,33,47,.07)}
h1{margin:0 0 6px;font-size:22px}
p{margin:0 0 14px;color:#5C6878}
form{display:flex;gap:8px;margin-top:6px}
input{flex:1;padding:11px 12px;border:1px solid #C8CFD8;border-radius:7px;font:inherit;text-transform:uppercase}
button{padding:11px 16px;border:0;border-radius:7px;background:#7B4654;color:#fff;font:600 14px inherit;cursor:pointer}
.status{margin:18px 0 14px;padding:12px 14px;border-radius:8px;font-weight:700}
.ok{background:#E9F5EE;color:#216441}.bad{background:#FAECEC;color:#8A3030}
table{width:100%;border-collapse:collapse}
td{padding:7px 0;border-bottom:1px solid #EEF1F5;vertical-align:top}
td:first-child{width:42%;color:#5C6878}
small{display:block;margin-top:14px;color:#5C6878}
@media(max-width:520px){form{flex-direction:column}}
</style>
</head>
<body>
<div class="wrap">
    <a class="brand" href="?page=home"><?php if($logoExists): ?><img src="logo.png" alt=""><?php endif; ?>MyThesis</a>
    <div class="card">
        <h1>Verifikasi Kartu Bimbingan</h1>
        <p>Masukkan kode verifikasi yang tercantum pada kartu bimbingan, atau pindai QR code pada kartu.</p>
        <form method="get"><input type="hidden" name="page" value="verifikasi"><label for="kode" hidden>Kode verifikasi</label><input id="kode" name="kode" value="<?=h($code)?>" placeholder="Contoh: A1B2-C3D4-E5F6" maxlength="16" required><button type="submit">Periksa</button></form>
        <?php if(!$hasCode): ?>
            <div class="status bad">Fitur verifikasi belum diaktifkan oleh administrator.</div>
        <?php elseif($code !== '' && !$result): ?>
            <div class="status bad">Kode tidak ditemukan. Kartu ini tidak dapat diverifikasi; periksa kembali kode yang dimasukkan.</div>
        <?php elseif($result): ?>
            <div class="status ok">✓ Kartu terdaftar di MyThesis.</div>
            <table>
                <tr><td>Nama Mahasiswa</td><td><strong><?=h($result['mahasiswa_nama'])?></strong></td></tr>
                <?php if($academicReady): ?>
                <tr><td>NIM</td><td><?=h($maskNim($result['nim'] ?? ''))?></td></tr>
                <tr><td>Program Studi</td><td><?=h(prodi_label($result, 'prodi_nama', 'prodi_jenjang') ?: '—')?></td></tr>
                <tr><td>Periode Mulai</td><td><?=h(period_label($result))?></td></tr>
                <?php endif; ?>
                <tr><td>Judul Skripsi</td><td><?=h($result['judul_skripsi'])?></td></tr>
                <tr><td>Dosen Pembimbing</td><td><?=h($result['dosen_nama'])?></td></tr>
                <tr><td>Status Bimbingan</td><td><?=h(status_label((string)$result['status']))?></td></tr>
                <tr><td>Tanggapan dosen</td><td><?=(int)$stats['revisi'] + (int)$stats['disetujui']?> kali (revisi &amp; persetujuan)</td></tr>
                <tr><td>Dokumen diunggah</td><td><?=(int)$stats['dokumen']?> dokumen, <?=(int)$stats['disetujui']?> disetujui</td></tr>
                <tr><td>Aktivitas terakhir</td><td><?=h($lastActivity !== '' ? tanggal_id($lastActivity, true) : tanggal_id($result['created_at'], true))?></td></tr>
            </table>
            <small>Data di atas adalah kondisi terkini di sistem. Cocokkan jumlah tanggapan dosen dan tanggal aktivitas terakhir dengan kartu yang Anda terima; kartu yang dicetak lebih awal dapat memuat riwayat yang lebih sedikit.</small>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
