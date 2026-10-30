ALTER TABLE tbl_accounts_payable
    ADD COLUMN supplier_reference VARCHAR(100) NOT NULL DEFAULT '' AFTER document_number;
