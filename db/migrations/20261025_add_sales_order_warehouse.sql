-- Almacén de referencia para el cumplimiento de órdenes de venta.
ALTER TABLE tbl_sales_order ADD COLUMN warehouse_id INT NOT NULL DEFAULT 1 AFTER customer_id;
ALTER TABLE tbl_sales_order
  ADD CONSTRAINT fk_sales_order_warehouse FOREIGN KEY (warehouse_id) REFERENCES tbl_warehouse(id) ON UPDATE CASCADE ON DELETE RESTRICT;
