-- Jalankan satu kali pada database MyThesis yang sudah terpasang.
ALTER TABLE revisi
    ADD COLUMN file_path VARCHAR(255) NULL AFTER tipe_revisi;
