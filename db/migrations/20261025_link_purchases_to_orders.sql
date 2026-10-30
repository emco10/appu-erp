ALTER TABLE tbl_purchase
    ADD COLUMN purchase_order_id INT NULL AFTER provider_id,
    ADD INDEX idx_purchase_order_id (purchase_order_id);
