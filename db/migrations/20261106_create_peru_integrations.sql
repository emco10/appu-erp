-- Additive configuration. Every integration and tax assistance starts disabled.
CREATE TABLE IF NOT EXISTS tbl_peru_integration_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 identity_enabled TINYINT NOT NULL DEFAULT 0,
 gre_enabled TINYINT NOT NULL DEFAULT 0,
 sire_enabled TINYINT NOT NULL DEFAULT 0,
 retention_enabled TINYINT NOT NULL DEFAULT 0,
 detraction_enabled TINYINT NOT NULL DEFAULT 0,
 company_retention_agent TINYINT NULL,
 updated_by INT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO tbl_peru_integration_settings(id) VALUES(1);
CREATE TABLE IF NOT EXISTS tbl_partner_tax_profile (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 partner_type VARCHAR(10) NOT NULL,
 partner_id INT NOT NULL,
 document_number VARCHAR(11) NOT NULL,
 profile_json JSON NOT NULL,
 updated_by INT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_partner_tax_profile(partner_type,partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tbl_peru_tax_evaluation (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_key VARCHAR(80) NOT NULL UNIQUE,
 request_sha256 CHAR(64) NOT NULL,
 operation_type VARCHAR(20) NOT NULL,
 document_reference VARCHAR(80) NOT NULL,
 evaluation_json JSON NOT NULL,
 created_by INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE tbl_remission_guide ADD COLUMN IF NOT EXISTS gross_weight DECIMAL(14,3) NULL,
 ADD COLUMN IF NOT EXISTS weight_unit VARCHAR(3) NOT NULL DEFAULT 'KGM',
 ADD COLUMN IF NOT EXISTS driver_last_name VARCHAR(100) NULL;
