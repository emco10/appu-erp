ALTER TABLE tbl_purchase
    ADD COLUMN supplier_reference VARCHAR(100) NOT NULL DEFAULT '' AFTER number;
