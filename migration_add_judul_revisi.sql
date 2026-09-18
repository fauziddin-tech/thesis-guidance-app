ALTER TABLE bimbingan
  MODIFY COLUMN status ENUM('pengajuan_judul','revisi_judul','aktif','selesai','ditangguhkan') DEFAULT 'pengajuan_judul',
  ADD COLUMN judul_revision_catatan TEXT NULL AFTER deskripsi,
  ADD COLUMN judul_revision_at TIMESTAMP NULL AFTER judul_revision_catatan;

CREATE TABLE IF NOT EXISTS judul_revisi (
 id INT PRIMARY KEY AUTO_INCREMENT,
 bimbingan_id INT NOT NULL,
 dosen_id INT NOT NULL,
 mahasiswa_id INT NOT NULL,
 judul_sebelum VARCHAR(255) NOT NULL,
 deskripsi_sebelum TEXT,
 catatan_dosen TEXT NOT NULL,
 judul_sesudah VARCHAR(255) NULL,
 deskripsi_sesudah TEXT NULL,
 status ENUM('diminta','diajukan_ulang','disetujui') NOT NULL DEFAULT 'diminta',
 requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 resubmitted_at TIMESTAMP NULL,
 approved_at TIMESTAMP NULL,
 FOREIGN KEY (bimbingan_id) REFERENCES bimbingan(id) ON DELETE CASCADE,
 FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_judul_revisi_bimbingan (bimbingan_id),
 INDEX idx_judul_revisi_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;