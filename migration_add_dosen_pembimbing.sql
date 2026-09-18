ALTER TABLE users
  ADD COLUMN dosen_pembimbing_id INT NULL AFTER no_telp,
  ADD INDEX idx_users_dosen_pembimbing (dosen_pembimbing_id),
  ADD CONSTRAINT fk_users_dosen_pembimbing
    FOREIGN KEY (dosen_pembimbing_id) REFERENCES users(id)
    ON DELETE SET NULL;
