CREATE TABLE IF NOT EXISTS tbl_external_integration_log (
  id BIGINT NOT NULL AUTO_INCREMENT,
  integration_id INT NOT NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  available_status TINYINT(1) NOT NULL DEFAULT 0,
  http_status SMALLINT NOT NULL DEFAULT 0,
  elapsed_ms INT NOT NULL DEFAULT 0,
  message VARCHAR(500) NOT NULL DEFAULT '',
  checked_by INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_integration_log_integration_date (integration_id, checked_at),
  KEY idx_integration_log_checked_by (checked_by),
  CONSTRAINT fk_integration_log_integration FOREIGN KEY (integration_id) REFERENCES tbl_external_integration (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_integration_log_checked_by FOREIGN KEY (checked_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
