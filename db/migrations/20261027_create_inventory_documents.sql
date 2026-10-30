CREATE TABLE IF NOT EXISTS tbl_inventory_document (
  id INT NOT NULL AUTO_INCREMENT,
  number VARCHAR(50) NOT NULL,
  document_type ENUM('ENTRY','ISSUE') NOT NULL,
  warehouse_id INT NOT NULL,
  document_date DATE NOT NULL,
  status ENUM('Registrado','Anulado') NOT NULL DEFAULT 'Registrado',
  external_reference VARCHAR(100) NOT NULL DEFAULT '',
  reason VARCHAR(100) NOT NULL DEFAULT '',
  notes VARCHAR(500) NOT NULL DEFAULT '',
  created_by INT NOT NULL,
  registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_by INT NULL,
  cancelled_at DATETIME NULL,
  cancellation_reason VARCHAR(500) NOT NULL DEFAULT '',
  movement_watermark_id INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_document_number (number),
  KEY idx_inventory_document_type_date (document_type,document_date),
  KEY idx_inventory_document_warehouse (warehouse_id,status),
  CONSTRAINT fk_inventory_document_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_document_created_by FOREIGN KEY (created_by) REFERENCES tbl_user(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_document_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES tbl_user(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_inventory_document_detail (
  id INT NOT NULL AUTO_INCREMENT,
  inventory_document_id INT NOT NULL,
  line_number INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
  inventory_value DECIMAL(16,4) NOT NULL DEFAULT 0,
  lot_number VARCHAR(80) NOT NULL DEFAULT '',
  expiration_date DATE NULL,
  line_notes VARCHAR(250) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_document_line (inventory_document_id,line_number),
  UNIQUE KEY uq_inventory_document_product (inventory_document_id,product_id),
  KEY idx_inventory_document_detail_product (product_id),
  CONSTRAINT fk_inventory_document_detail_header FOREIGN KEY (inventory_document_id) REFERENCES tbl_inventory_document(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_document_detail_product FOREIGN KEY (product_id) REFERENCES tbl_product(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_inventory_document_quantity CHECK (quantity > 0),
  CONSTRAINT chk_inventory_document_value CHECK (inventory_value >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tbl_document_series MODIFY document_type ENUM(
  'INVOICE','RECEIPT','CREDIT_NOTE','QUOTATION','SALES_ORDER','PURCHASE',
  'PURCHASE_REQUEST','ORDER','GOODS_RECEIPT','REMISSION_GUIDE','INCOMING_PAYMENT',
  'OUTGOING_PAYMENT','STOCK_TRANSFER','LANDED_COST','INVENTORY_ENTRY','INVENTORY_ISSUE'
) NOT NULL;

INSERT IGNORE INTO tbl_document_series(document_type,series,next_number,number_padding,active_status) VALUES
  ('INVENTORY_ENTRY','EI',1,7,1),
  ('INVENTORY_ISSUE','SI',1,7,1);
