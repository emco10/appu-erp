ALTER TABLE tbl_purchase
  ADD COLUMN document_subtype VARCHAR(20) NOT NULL DEFAULT 'RESERVE' AFTER status,
  ADD KEY idx_purchase_subtype_status (document_subtype, status);
