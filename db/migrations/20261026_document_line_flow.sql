-- Apply once after 20261026_custom_field_metadata.sql.
-- Preserve historical stock. Existing movements determine which guides posted it.
ALTER TABLE tbl_remission_guide ADD COLUMN inventory_posted TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tbl_invoice ADD COLUMN inventory_origin VARCHAR(20) NOT NULL DEFAULT 'DOCUMENT';
ALTER TABLE tbl_invoice_detail
  ADD COLUMN base_document_type VARCHAR(30) DEFAULT NULL,
  ADD COLUMN base_document_id INT DEFAULT NULL,
  ADD COLUMN base_line_id INT DEFAULT NULL,
  ADD KEY idx_invoice_detail_base (base_document_type, base_document_id, base_line_id);
ALTER TABLE tbl_warehouse_movement
  ADD COLUMN document_line_id INT DEFAULT NULL,
  ADD KEY idx_movement_document_line (source_type, source_id, document_line_id);

CREATE TABLE tbl_document_line_application (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_type VARCHAR(30) NOT NULL,
  source_id INT NOT NULL,
  source_line_id INT NOT NULL,
  target_type VARCHAR(30) NOT NULL,
  target_id INT NOT NULL,
  target_line_id INT NOT NULL,
  stage VARCHAR(20) NOT NULL,
  quantity DECIMAL(18,6) NOT NULL,
  status VARCHAR(15) NOT NULL DEFAULT 'ACTIVE',
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reversed_by INT DEFAULT NULL,
  reversed_at DATETIME DEFAULT NULL,
  UNIQUE KEY uq_document_line_application (source_type,source_id,source_line_id,target_type,target_id,target_line_id,stage),
  KEY idx_line_application_source (source_type,source_id,stage,status,source_line_id),
  KEY idx_line_application_target (target_type,target_id,status),
  CONSTRAINT chk_line_application_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tbl_document_line_application
 (source_type,source_id,source_line_id,target_type,target_id,target_line_id,stage,quantity,status,created_by)
SELECT guide.source_type,guide.source_id,detail.source_detail_id,'remission_guide',guide.id,detail.id,
       'FULFILLMENT',detail.quantity,IF(guide.status='Anulada','REVERSED','ACTIVE'),guide.created_by
FROM tbl_remission_guide guide JOIN tbl_remission_guide_detail detail ON detail.remission_guide_id=guide.id
WHERE detail.quantity>0;

UPDATE tbl_remission_guide guide SET inventory_posted=1
WHERE EXISTS (SELECT 1 FROM tbl_warehouse_movement movement
 WHERE movement.source_type='remission_guide' AND movement.source_id=guide.id AND movement.quantity<0 AND movement.type=3);
