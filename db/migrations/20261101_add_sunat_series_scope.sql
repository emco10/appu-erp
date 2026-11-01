ALTER TABLE tbl_document_series
  ADD COLUMN IF NOT EXISTS sunat_enabled tinyint(1) NOT NULL DEFAULT 0 AFTER number_padding;
UPDATE tbl_document_series SET sunat_enabled=1 WHERE document_type='INVOICE' AND series REGEXP '^F[A-Z0-9]{3}$';
UPDATE tbl_document_series SET sunat_enabled=1 WHERE document_type='RECEIPT' AND series REGEXP '^B[A-Z0-9]{3}$';
UPDATE tbl_document_series SET sunat_enabled=1 WHERE document_type='CREDIT_NOTE' AND series REGEXP '^[FB][A-Z0-9]{3}$';
INSERT IGNORE INTO tbl_document_series(document_type,series,next_number,number_padding,sunat_enabled,active_status)
VALUES ('INVOICE','INT-FAC',1,7,0,1),('RECEIPT','INT-BOL',1,7,0,1),('CREDIT_NOTE','INT-NC',1,7,0,1);
