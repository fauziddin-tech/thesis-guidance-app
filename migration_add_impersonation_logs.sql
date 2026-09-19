CREATE TABLE IF NOT EXISTS impersonation_logs (
 id INT PRIMARY KEY AUTO_INCREMENT,
 impersonator_id INT NOT NULL,
 impersonator_nama VARCHAR(150) NOT NULL,
 impersonator_role VARCHAR(20) NOT NULL,
 target_id INT NOT NULL,
 target_nama VARCHAR(150) NOT NULL,
 mode VARCHAR(10) NOT NULL DEFAULT 'view',
 ip_address VARCHAR(45) NULL,
 started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ended_at DATETIME NULL,
 INDEX idx_impersonation_target (target_id),
 INDEX idx_impersonation_actor (impersonator_id),
 INDEX idx_impersonation_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
