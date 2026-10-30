ALTER TABLE tbl_quotation
  ADD COLUMN billing_address_id BIGINT NULL AFTER warehouse_id,
  ADD COLUMN shipping_address_id BIGINT NULL AFTER billing_address_id,
  ADD COLUMN shipping_address VARCHAR(300) NOT NULL DEFAULT '' AFTER address,
  ADD KEY idx_quotation_billing_address (billing_address_id),
  ADD KEY idx_quotation_shipping_address (shipping_address_id),
  ADD CONSTRAINT fk_quotation_billing_address FOREIGN KEY (billing_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL,
  ADD CONSTRAINT fk_quotation_shipping_address FOREIGN KEY (shipping_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE tbl_sales_order
  ADD COLUMN billing_address_id BIGINT NULL AFTER warehouse_id,
  ADD COLUMN shipping_address_id BIGINT NULL AFTER billing_address_id,
  ADD COLUMN shipping_address VARCHAR(300) NOT NULL DEFAULT '' AFTER address,
  ADD KEY idx_sales_order_billing_address (billing_address_id),
  ADD KEY idx_sales_order_shipping_address (shipping_address_id),
  ADD CONSTRAINT fk_sales_order_billing_address FOREIGN KEY (billing_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL,
  ADD CONSTRAINT fk_sales_order_shipping_address FOREIGN KEY (shipping_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE tbl_remission_guide
  ADD COLUMN destination_address_id BIGINT NULL AFTER warehouse_id,
  ADD KEY idx_guide_destination_address (destination_address_id),
  ADD CONSTRAINT fk_guide_destination_address FOREIGN KEY (destination_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE tbl_invoice
  ADD COLUMN billing_address_id BIGINT NULL AFTER warehouse_id,
  ADD COLUMN shipping_address_id BIGINT NULL AFTER billing_address_id,
  ADD COLUMN shipping_address VARCHAR(300) NOT NULL DEFAULT '' AFTER address,
  ADD KEY idx_invoice_billing_address (billing_address_id),
  ADD KEY idx_invoice_shipping_address (shipping_address_id),
  ADD CONSTRAINT fk_invoice_billing_address FOREIGN KEY (billing_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL,
  ADD CONSTRAINT fk_invoice_shipping_address FOREIGN KEY (shipping_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE tbl_receipt
  ADD COLUMN billing_address_id BIGINT NULL AFTER warehouse_id,
  ADD COLUMN shipping_address_id BIGINT NULL AFTER billing_address_id,
  ADD COLUMN shipping_address VARCHAR(300) NOT NULL DEFAULT '' AFTER address,
  ADD KEY idx_receipt_billing_address (billing_address_id),
  ADD KEY idx_receipt_shipping_address (shipping_address_id),
  ADD CONSTRAINT fk_receipt_billing_address FOREIGN KEY (billing_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL,
  ADD CONSTRAINT fk_receipt_shipping_address FOREIGN KEY (shipping_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE tbl_quotation q JOIN tbl_business_partner_address a ON a.partner_type='customer' AND a.partner_id=q.customer_id AND a.address_type='billing' AND a.is_default=1 SET q.billing_address_id=a.id, q.shipping_address=q.address WHERE q.billing_address_id IS NULL;
UPDATE tbl_sales_order o JOIN tbl_business_partner_address a ON a.partner_type='customer' AND a.partner_id=o.customer_id AND a.address_type='billing' AND a.is_default=1 SET o.billing_address_id=a.id, o.shipping_address=o.address WHERE o.billing_address_id IS NULL;
