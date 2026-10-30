ALTER TABLE tbl_goods_receipt
  MODIFY purchase_id INT NULL,
  ADD COLUMN purchase_order_id INT NULL AFTER purchase_id,
  ADD COLUMN base_document_type ENUM('ORDER','PURCHASE') NOT NULL DEFAULT 'PURCHASE' AFTER purchase_order_id,
  ADD COLUMN base_document_id INT NULL AFTER base_document_type,
  ADD COLUMN provider_code VARCHAR(50) NOT NULL DEFAULT '' AFTER provider_id,
  ADD COLUMN provider_name VARCHAR(200) NOT NULL DEFAULT '' AFTER provider_code,
  ADD COLUMN provider_address VARCHAR(300) NOT NULL DEFAULT '' AFTER provider_name,
  ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'PEN' AFTER provider_address,
  ADD COLUMN supplier_delivery_reference VARCHAR(100) NOT NULL DEFAULT '' AFTER currency,
  ADD KEY idx_goods_receipt_order (purchase_order_id,status),
  ADD KEY idx_goods_receipt_base (base_document_type,base_document_id,status),
  ADD CONSTRAINT fk_goods_receipt_order FOREIGN KEY (purchase_order_id) REFERENCES tbl_order(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_goods_receipt_detail
  MODIFY purchase_detail_id INT NULL,
  ADD COLUMN order_detail_id INT NULL AFTER purchase_detail_id,
  ADD COLUMN base_document_type ENUM('ORDER','PURCHASE') NOT NULL DEFAULT 'PURCHASE' AFTER order_detail_id,
  ADD COLUMN base_line_id INT NULL AFTER base_document_type,
  ADD COLUMN item_code VARCHAR(100) NOT NULL DEFAULT '' AFTER product_id,
  ADD COLUMN item_description VARCHAR(500) NOT NULL DEFAULT '' AFTER item_code,
  ADD COLUMN unit_of_measure VARCHAR(100) NOT NULL DEFAULT '' AFTER item_description,
  ADD KEY idx_goods_receipt_detail_order (order_detail_id),
  ADD KEY idx_goods_receipt_detail_base (base_document_type,base_line_id),
  ADD CONSTRAINT fk_goods_receipt_detail_order FOREIGN KEY (order_detail_id) REFERENCES tbl_order_detail(id) ON UPDATE CASCADE ON DELETE RESTRICT;

UPDATE tbl_goods_receipt receipt
JOIN tbl_purchase purchase ON purchase.id=receipt.purchase_id
JOIN tbl_provider provider ON provider.id=receipt.provider_id
SET receipt.purchase_order_id=purchase.purchase_order_id,
    receipt.base_document_type='PURCHASE',receipt.base_document_id=receipt.purchase_id,
    receipt.provider_code=provider.code,receipt.provider_name=provider.business_name,
    receipt.provider_address=purchase.provider_address,receipt.currency=purchase.currency
WHERE receipt.base_document_id IS NULL;

UPDATE tbl_goods_receipt_detail detail
JOIN tbl_purchase_detail purchase_line ON purchase_line.id=detail.purchase_detail_id
SET detail.base_document_type='PURCHASE',detail.base_line_id=detail.purchase_detail_id,
    detail.item_code=purchase_line.item_code,detail.item_description=purchase_line.item_description,
    detail.unit_of_measure=purchase_line.item_unit_value
WHERE detail.base_line_id IS NULL;
