ALTER TABLE tbl_order ADD COLUMN purchase_request_id INT NULL AFTER provider_id;
ALTER TABLE tbl_order ADD KEY idx_order_purchase_request (purchase_request_id);
