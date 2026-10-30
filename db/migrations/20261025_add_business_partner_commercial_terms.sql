CREATE TABLE IF NOT EXISTS tbl_price_list (
  id INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'PEN',
  factor DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
  active_status TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_price_list_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_price_list(code,name,currency,factor,active_status) VALUES ('GENERAL','Lista general','PEN',1.0000,1);

ALTER TABLE tbl_customer
  ADD COLUMN default_currency VARCHAR(10) NOT NULL DEFAULT 'PEN' AFTER commission,
  ADD COLUMN payment_days INT NOT NULL DEFAULT 0 AFTER default_currency,
  ADD COLUMN credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER payment_days,
  ADD COLUMN price_list_id INT NULL AFTER credit_limit,
  ADD COLUMN sales_employee_id INT NULL AFTER price_list_id,
  ADD KEY idx_customer_price_list (price_list_id),
  ADD KEY idx_customer_sales_employee (sales_employee_id),
  ADD CONSTRAINT fk_customer_price_list FOREIGN KEY (price_list_id) REFERENCES tbl_price_list(id) ON UPDATE CASCADE ON DELETE SET NULL,
  ADD CONSTRAINT fk_customer_sales_employee FOREIGN KEY (sales_employee_id) REFERENCES tbl_employee(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE tbl_provider
  ADD COLUMN default_currency VARCHAR(10) NOT NULL DEFAULT 'PEN' AFTER bank2_account_holder,
  ADD COLUMN payment_days INT NOT NULL DEFAULT 0 AFTER default_currency,
  ADD COLUMN credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER payment_days,
  ADD COLUMN buyer_employee_id INT NULL AFTER credit_limit,
  ADD KEY idx_provider_buyer_employee (buyer_employee_id),
  ADD CONSTRAINT fk_provider_buyer_employee FOREIGN KEY (buyer_employee_id) REFERENCES tbl_employee(id) ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE tbl_customer SET price_list_id=(SELECT id FROM tbl_price_list WHERE code='GENERAL' LIMIT 1) WHERE price_list_id IS NULL;
