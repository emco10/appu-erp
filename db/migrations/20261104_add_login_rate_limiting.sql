CREATE TABLE IF NOT EXISTS tbl_login_attempt (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempt_pair_time (username_hash, ip_hash, attempted_at),
  KEY idx_login_attempt_ip_time (ip_hash, attempted_at),
  KEY idx_login_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
