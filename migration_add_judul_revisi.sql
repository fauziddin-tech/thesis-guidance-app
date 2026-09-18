ALTER TABLE bimbingan
  MODIFY COLUMN status ENUM('pengajuan_judul','revisi_judul','aktif','selesai','ditangguhkan') DEFAULT 'pengajuan_judul',
  ADD COLUMN judul_revision_catatan TEXT NULL AFTER deskripsi,
  ADD COLUMN judul_revision_at TIMESTAMP NULL AFTER judul_revision_catatan;