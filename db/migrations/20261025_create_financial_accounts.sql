CREATE TABLE IF NOT EXISTS tbl_financial_account (
    id INT NOT NULL AUTO_INCREMENT,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    account_type ENUM('Caja','Banco') NOT NULL DEFAULT 'Caja',
    currency VARCHAR(10) NOT NULL DEFAULT 'PEN',
    account_number VARCHAR(100) NOT NULL DEFAULT '',
    active_status TINYINT(1) NOT NULL DEFAULT 1,
    registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_financial_account_code (code), KEY idx_financial_account_active (active_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_financial_account (code,name,account_type,currency) VALUES
('CAJA-PEN','Caja principal','Caja','PEN'),
('BANCO-PEN','Cuenta bancaria principal','Banco','PEN');

