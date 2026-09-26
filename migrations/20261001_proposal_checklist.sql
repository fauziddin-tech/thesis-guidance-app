-- Kelengkapan dan tata tulis proposal penelitian: centang mahasiswa, pemindaian otomatis DOCX,
-- dan penilaian Pembimbing 1 per komponen (cover, kata pengantar, daftar isi/tabel/gambar,
-- Bab I–III, daftar pustaka, lampiran). Disimpan sebagai JSON pada dokumen proposal.
-- Aman dijalankan ulang. Cadangkan database terlebih dahulu.

ALTER TABLE bab_skripsi
    ADD COLUMN IF NOT EXISTS kelengkapan TEXT NULL;
