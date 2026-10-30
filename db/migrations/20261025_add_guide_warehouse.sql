-- Almacén asociado al documento operativo de entrega.
ALTER TABLE tbl_remission_guide ADD COLUMN warehouse_id INT NOT NULL DEFAULT 1 AFTER source_id;
ALTER TABLE tbl_remission_guide
  ADD CONSTRAINT fk_remission_guide_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT;
