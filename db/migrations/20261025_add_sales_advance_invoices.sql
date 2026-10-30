ALTER TABLE tbl_invoice
  ADD COLUMN advance_percentage DECIMAL(7,4) NULL AFTER document_subtype;

CREATE TABLE IF NOT EXISTS tbl_sales_advance_application (
  id BIGINT NOT NULL AUTO_INCREMENT,
  advance_invoice_id INT NOT NULL,
  final_invoice_id INT NOT NULL,
  applied_amount DECIMAL(14,2) NOT NULL,
  status ENUM('ACTIVE','REVERSED') NOT NULL DEFAULT 'ACTIVE',
  created_by INT NOT NULL,
  registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reversed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_advance_final (advance_invoice_id, final_invoice_id),
  KEY idx_sales_advance_final_status (final_invoice_id, status),
  CONSTRAINT fk_sales_advance_source FOREIGN KEY (advance_invoice_id) REFERENCES tbl_invoice(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sales_advance_final FOREIGN KEY (final_invoice_id) REFERENCES tbl_invoice(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sales_advance_user FOREIGN KEY (created_by) REFERENCES tbl_user(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_sales_advance_amount CHECK (applied_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
