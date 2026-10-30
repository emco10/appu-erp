-- Almacén de salida de documentos de ventas.
ALTER TABLE tbl_invoice ADD COLUMN warehouse_id INT NOT NULL DEFAULT 1 AFTER customer_id;
ALTER TABLE tbl_receipt ADD COLUMN warehouse_id INT NOT NULL DEFAULT 1 AFTER customer_id;
ALTER TABLE tbl_credit_note ADD COLUMN warehouse_id INT NOT NULL DEFAULT 1 AFTER customer_id;

ALTER TABLE tbl_invoice
  ADD CONSTRAINT fk_invoice_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_receipt
  ADD CONSTRAINT fk_receipt_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_credit_note
  ADD CONSTRAINT fk_credit_note_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT;
