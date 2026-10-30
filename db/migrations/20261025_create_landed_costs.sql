ALTER TABLE tbl_warehouse_stock
  ADD COLUMN inventory_value DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER reserved_quantity,
  ADD COLUMN average_unit_cost DECIMAL(16,6) NOT NULL DEFAULT 0 AFTER inventory_value;

ALTER TABLE tbl_warehouse_movement
  ADD COLUMN inventory_value_delta DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER quantity;

ALTER TABLE tbl_goods_receipt_detail
  ADD COLUMN base_unit_cost DECIMAL(16,6) NOT NULL DEFAULT 0 AFTER quantity,
  ADD COLUMN base_inventory_value DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER base_unit_cost;

CREATE TABLE tbl_landed_cost (
  id INT NOT NULL AUTO_INCREMENT,
  number VARCHAR(30) NOT NULL,
  goods_receipt_id INT NOT NULL,
  provider_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  status ENUM('Registrado','Anulado') NOT NULL DEFAULT 'Registrado',
  cost_date DATE NOT NULL,
  concept VARCHAR(100) NOT NULL,
  allocation_method ENUM('VALUE','QUANTITY') NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'PEN',
  total_cost DECIMAL(14,2) NOT NULL,
  notes VARCHAR(500) NOT NULL DEFAULT '',
  created_by INT NOT NULL,
  registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_by INT NULL,
  cancelled_at DATETIME NULL,
  cancellation_reason VARCHAR(500) NOT NULL DEFAULT '',
  movement_watermark_id INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_landed_cost_number (number),
  KEY idx_landed_cost_receipt (goods_receipt_id,status),
  CONSTRAINT fk_landed_cost_receipt FOREIGN KEY (goods_receipt_id) REFERENCES tbl_goods_receipt(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_landed_cost_provider FOREIGN KEY (provider_id) REFERENCES tbl_provider(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_landed_cost_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_landed_cost_created_by FOREIGN KEY (created_by) REFERENCES tbl_user(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_landed_cost_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES tbl_user(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_landed_cost_total CHECK (total_cost > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_landed_cost_detail (
  id INT NOT NULL AUTO_INCREMENT,
  landed_cost_id INT NOT NULL,
  goods_receipt_detail_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  base_value DECIMAL(16,4) NOT NULL,
  allocation_factor DECIMAL(18,10) NOT NULL,
  allocated_cost DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_landed_cost_receipt_line (landed_cost_id,goods_receipt_detail_id),
  KEY idx_landed_cost_detail_product (product_id),
  CONSTRAINT fk_landed_cost_detail_header FOREIGN KEY (landed_cost_id) REFERENCES tbl_landed_cost(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_landed_cost_detail_receipt FOREIGN KEY (goods_receipt_detail_id) REFERENCES tbl_goods_receipt_detail(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_landed_cost_detail_product FOREIGN KEY (product_id) REFERENCES tbl_product(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_landed_cost_allocated CHECK (allocated_cost >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tbl_document_series MODIFY document_type ENUM('INVOICE','RECEIPT','CREDIT_NOTE','QUOTATION','SALES_ORDER','PURCHASE','PURCHASE_REQUEST','ORDER','GOODS_RECEIPT','REMISSION_GUIDE','INCOMING_PAYMENT','OUTGOING_PAYMENT','STOCK_TRANSFER','LANDED_COST') NOT NULL;
INSERT IGNORE INTO tbl_document_series(document_type,series,next_number,number_padding,active_status)
VALUES ('LANDED_COST','CE',1,7,1);
