-- Los documentos conservan el socio de negocio válido que originó la operación.
-- Se impide borrar el maestro mientras existan documentos relacionados.
ALTER TABLE tbl_quotation
  ADD CONSTRAINT fk_quotation_customer FOREIGN KEY (customer_id) REFERENCES tbl_customer(client_id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_sales_order
  ADD CONSTRAINT fk_sales_order_customer FOREIGN KEY (customer_id) REFERENCES tbl_customer(client_id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_invoice
  ADD CONSTRAINT fk_invoice_customer FOREIGN KEY (customer_id) REFERENCES tbl_customer(client_id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_receipt
  ADD CONSTRAINT fk_receipt_customer FOREIGN KEY (customer_id) REFERENCES tbl_customer(client_id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_order
  ADD CONSTRAINT fk_order_provider FOREIGN KEY (provider_id) REFERENCES tbl_provider(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_purchase
  ADD CONSTRAINT fk_purchase_provider FOREIGN KEY (provider_id) REFERENCES tbl_provider(id) ON UPDATE CASCADE ON DELETE RESTRICT;
