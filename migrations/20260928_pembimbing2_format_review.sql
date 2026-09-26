-- Pembagian tugas Pembimbing 2 (MyThesis 1.10.0):
-- Pembimbing 1 (substansi) sendirian mereview judul dan Bab 1–5; Pembimbing 2 (format penulisan)
-- mereview satu file "Naskah Proposal (Bab 1-3)" setelah Bab 1–3 disetujui Pembimbing 1.
-- Migrasi ini hanya menyelaraskan status data lama yang masih menunggu ACC Pembimbing 2 pada
-- judul/bab. Tidak ada tabel yang diubah; riwayat review dan komentar tetap utuh.
-- Aman dijalankan ulang. Cadangkan database terlebih dahulu.

-- 1. Bab yang sudah diputuskan Pembimbing 1 mengikuti keputusan Pembimbing 1.
UPDATE bab_skripsi bs
JOIN bimbingan b ON b.id = bs.bimbingan_id
JOIN mythesis_review_dosen r ON r.jenis = 'bab' AND r.objek_id = bs.id AND r.dosen_id = b.dosen_id
SET bs.disetujui_at = CASE WHEN r.keputusan = 'disetujui' THEN COALESCE(bs.disetujui_at, r.updated_at) ELSE bs.disetujui_at END,
    bs.status = r.keputusan
WHERE bs.status IN ('menunggu_review', 'direvisi')
  AND bs.status <> r.keputusan
  AND bs.nama_bab NOT LIKE 'Naskah Proposal%';

-- 2. Judul yang sudah di-ACC Pembimbing 1 tetapi masih menunggu Pembimbing 2 menjadi aktif.
UPDATE bimbingan b
JOIN mythesis_review_dosen r ON r.jenis = 'judul' AND r.objek_id = b.id AND r.dosen_id = b.dosen_id AND r.keputusan = 'disetujui'
SET b.status = 'aktif'
WHERE b.status = 'pengajuan_judul';
