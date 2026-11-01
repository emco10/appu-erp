ALTER TABLE tbl_product
  ADD COLUMN tracking_type ENUM('NONE','SERIAL','LOT') NOT NULL DEFAULT 'NONE' AFTER model;

UPDATE tbl_product
SET tracking_type='LOT'
WHERE EXISTS (
  SELECT 1
  FROM tbl_product_lot
  WHERE tbl_product_lot.product_id=tbl_product.id
);
