-- Dimensiones opcionales por línea comercial. Ninguno de estos campos es
-- obligatorio para SUNAT y los documentos históricos conservan valores vacíos.

ALTER TABLE tbl_quotation_detail
  ADD COLUMN IF NOT EXISTS item_discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER item_unit_price,
  ADD COLUMN IF NOT EXISTS item_discounted_total DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER item_discount_rate,
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER item_discounted_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

UPDATE tbl_quotation_detail
SET item_discounted_total = ROUND(item_quantity * item_unit_price, 4)
WHERE item_discounted_total = 0 AND item_discount_rate = 0 AND item_quantity * item_unit_price <> 0;

ALTER TABLE tbl_sales_order_detail
  ADD COLUMN IF NOT EXISTS item_discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER item_unit_price,
  ADD COLUMN IF NOT EXISTS item_discounted_total DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER item_discount_rate,
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER item_discounted_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

UPDATE tbl_sales_order_detail
SET item_discounted_total = item_total
WHERE item_discounted_total = 0 AND item_discount_rate = 0 AND item_total <> 0;

ALTER TABLE tbl_invoice_detail
  ADD COLUMN IF NOT EXISTS item_discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER item_unit_price,
  ADD COLUMN IF NOT EXISTS item_discounted_total DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER item_discount_rate,
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER item_discounted_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

UPDATE tbl_invoice_detail
SET item_discounted_total = ROUND(item_quantity * item_unit_price, 4)
WHERE item_discounted_total = 0 AND item_discount_rate = 0 AND item_quantity * item_unit_price <> 0;

ALTER TABLE tbl_receipt_detail
  ADD COLUMN IF NOT EXISTS item_discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER item_unit_price,
  ADD COLUMN IF NOT EXISTS item_discounted_total DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER item_discount_rate,
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER item_discounted_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

UPDATE tbl_receipt_detail
SET item_discounted_total = ROUND(item_quantity * item_unit_price, 4)
WHERE item_discounted_total = 0 AND item_discount_rate = 0 AND item_quantity * item_unit_price <> 0;

ALTER TABLE tbl_credit_note_detail
  ADD COLUMN IF NOT EXISTS item_discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER item_unit_price,
  ADD COLUMN IF NOT EXISTS item_discounted_total DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER item_discount_rate,
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER item_discounted_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

UPDATE tbl_credit_note_detail
SET item_discounted_total = ROUND(item_quantity * item_unit_price, 4)
WHERE item_discounted_total = 0 AND item_discount_rate = 0 AND item_quantity * item_unit_price <> 0;

ALTER TABLE tbl_purchase_request_detail
  ADD COLUMN IF NOT EXISTS discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER estimated_unit_price,
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER line_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

ALTER TABLE tbl_order_detail
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER item_discounted_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

ALTER TABLE tbl_purchase_detail
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER item_discounted_total,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS lot_number VARCHAR(80) NOT NULL DEFAULT '' AFTER cost_center_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;

ALTER TABLE tbl_goods_receipt_detail
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER base_inventory_value,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) NOT NULL DEFAULT '' AFTER lot_number;

ALTER TABLE tbl_remission_guide_detail
  ADD COLUMN IF NOT EXISTS project_code VARCHAR(50) NOT NULL DEFAULT '' AFTER packages,
  ADD COLUMN IF NOT EXISTS cost_center_code VARCHAR(50) NOT NULL DEFAULT '' AFTER project_code,
  ADD COLUMN IF NOT EXISTS expiration_date DATE NULL AFTER serial_number;
