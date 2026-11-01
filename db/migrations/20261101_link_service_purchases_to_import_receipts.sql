ALTER TABLE tbl_purchase
  ADD COLUMN import_goods_receipt_id INT NULL AFTER purchase_order_id,
  ADD KEY idx_purchase_import_receipt (import_goods_receipt_id),
  ADD CONSTRAINT fk_purchase_import_receipt FOREIGN KEY (import_goods_receipt_id)
    REFERENCES tbl_goods_receipt(id) ON UPDATE CASCADE ON DELETE RESTRICT;
