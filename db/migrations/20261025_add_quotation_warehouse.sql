-- Almacén de referencia para cotizaciones y su conversión comercial.
ALTER TABLE tbl_quotation ADD COLUMN warehouse_id INT NOT NULL DEFAULT 1 AFTER customer_id;
ALTER TABLE tbl_quotation
  ADD CONSTRAINT fk_quotation_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT;
