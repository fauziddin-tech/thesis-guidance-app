CREATE TABLE IF NOT EXISTS password_resets (
 id INT PRIMARY KEY AUTO_INCREMENT,
 user_id INT NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 UNIQUE KEY uq_password_reset_token (token_hash),
 INDEX idx_password_resets_user (user_id),
 INDEX idx_password_resets_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
