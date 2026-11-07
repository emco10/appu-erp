ALTER TABLE tbl_document_series
  ADD COLUMN IF NOT EXISTS sunat_enabled tinyint(1) NOT NULL DEFAULT 0 AFTER number_padding;

INSERT IGNORE INTO tbl_document_series(document_type,series,next_number,number_padding,sunat_enabled,active_status)
VALUES
  ('INVOICE','F001',1,7,1,1),
  ('RECEIPT','B001',1,7,1,1),
  ('CREDIT_NOTE','FNC1',1,7,1,1),
  ('CREDIT_NOTE','BNC1',1,7,1,1),
  ('INVOICE','INT-FAC',1,7,0,1),
  ('RECEIPT','INT-BOL',1,7,0,1),
  ('CREDIT_NOTE','INT-NC',1,7,0,1);

UPDATE tbl_document_series
   SET sunat_enabled = CASE
       WHEN document_type='INVOICE' AND series REGEXP '^F[A-Z0-9]{3}$' THEN 1
       WHEN document_type='RECEIPT' AND series REGEXP '^B[A-Z0-9]{3}$' THEN 1
       WHEN document_type='CREDIT_NOTE' AND series REGEXP '^[FB][A-Z0-9]{3}$' THEN 1
       ELSE 0
   END
 WHERE document_type IN ('INVOICE','RECEIPT','CREDIT_NOTE');

UPDATE tbl_document_series series
LEFT JOIN (SELECT series,COALESCE(MAX(CAST(number AS UNSIGNED)),0) max_number FROM tbl_invoice GROUP BY series) used ON used.series=series.series
SET series.next_number=GREATEST(series.next_number,COALESCE(used.max_number,0)+1)
WHERE series.document_type='INVOICE';

UPDATE tbl_document_series series
LEFT JOIN (SELECT series,COALESCE(MAX(CAST(number AS UNSIGNED)),0) max_number FROM tbl_receipt GROUP BY series) used ON used.series=series.series
SET series.next_number=GREATEST(series.next_number,COALESCE(used.max_number,0)+1)
WHERE series.document_type='RECEIPT';

UPDATE tbl_document_series series
LEFT JOIN (SELECT series,COALESCE(MAX(CAST(number AS UNSIGNED)),0) max_number FROM tbl_credit_note GROUP BY series) used ON used.series=series.series
SET series.next_number=GREATEST(series.next_number,COALESCE(used.max_number,0)+1)
WHERE series.document_type='CREDIT_NOTE';
