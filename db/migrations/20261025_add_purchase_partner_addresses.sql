ALTER TABLE tbl_order
  ADD COLUMN provider_address_id BIGINT NULL AFTER provider_id,
  ADD COLUMN provider_address VARCHAR(300) NOT NULL DEFAULT '' AFTER provider_address_id,
  ADD KEY idx_order_provider_address (provider_address_id),
  ADD CONSTRAINT fk_order_provider_address FOREIGN KEY (provider_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE tbl_purchase
  ADD COLUMN provider_address_id BIGINT NULL AFTER provider_id,
  ADD COLUMN provider_address VARCHAR(300) NOT NULL DEFAULT '' AFTER provider_address_id,
  ADD KEY idx_purchase_provider_address (provider_address_id),
  ADD CONSTRAINT fk_purchase_provider_address FOREIGN KEY (provider_address_id) REFERENCES tbl_business_partner_address(id) ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE tbl_order ord JOIN tbl_provider provider ON provider.id=ord.provider_id SET ord.provider_address=provider.address WHERE ord.provider_address='';
UPDATE tbl_purchase purchase JOIN tbl_provider provider ON provider.id=purchase.provider_id SET purchase.provider_address=provider.address WHERE purchase.provider_address='';
