-- Apply once after 20261026_document_line_flow.sql.
CREATE INDEX idx_invoice_order_effect ON tbl_invoice (sales_order_id,status,inventory_origin,document_subtype);
CREATE INDEX idx_receipt_order_effect ON tbl_receipt (sales_order_id,status);
CREATE INDEX idx_guide_source_effect ON tbl_remission_guide (source_type,source_id,status,inventory_posted);
ALTER TABLE tbl_invoice ADD COLUMN rounding_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0;

CREATE TABLE tbl_document_request (
  user_id INT NOT NULL,
  operation VARCHAR(40) NOT NULL,
  request_key VARCHAR(64) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  result_type VARCHAR(30) DEFAULT NULL,
  result_id INT DEFAULT NULL,
  result_json TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id,operation,request_key),
  KEY idx_document_request_result (result_type,result_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
