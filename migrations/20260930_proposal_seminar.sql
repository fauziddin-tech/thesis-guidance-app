-- Tahap proposal penelitian dan seminar proposal.
-- Bab 4 terbuka setelah seminar proposal ditandai selesai oleh Pembimbing 1 atau admin.
-- Bimbingan yang sudah mengunggah Bab 4 sebelumnya tidak terpengaruh.
-- Aman dijalankan ulang. Cadangkan database terlebih dahulu.

ALTER TABLE bimbingan
    ADD COLUMN IF NOT EXISTS seminar_proposal_at DATE NULL AFTER menunggu_persetujuan;
