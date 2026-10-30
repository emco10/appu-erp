-- Integridad referencial de documentos comerciales APPU.
-- Se usa RESTRICT para conservar el historial: una cabecera con detalle
-- no puede eliminarse accidentalmente.
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE tbl_invoice_detail
  ADD CONSTRAINT fk_invoice_detail_header FOREIGN KEY (invoice_id) REFERENCES tbl_invoice(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_receipt_detail
  ADD CONSTRAINT fk_receipt_detail_header FOREIGN KEY (receipt_id) REFERENCES tbl_receipt(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_credit_note_detail
  ADD CONSTRAINT fk_credit_note_detail_header FOREIGN KEY (credit_note_id) REFERENCES tbl_credit_note(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_quotation_detail
  ADD CONSTRAINT fk_quotation_detail_header FOREIGN KEY (quotation_id) REFERENCES tbl_quotation(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_sales_order_detail
  ADD CONSTRAINT fk_sales_order_detail_header FOREIGN KEY (sales_order_id) REFERENCES tbl_sales_order(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_order_detail
  ADD CONSTRAINT fk_order_detail_header FOREIGN KEY (order_id) REFERENCES tbl_order(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_purchase_detail
  ADD CONSTRAINT fk_purchase_detail_header FOREIGN KEY (purchase_id) REFERENCES tbl_purchase(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_purchase_request_detail
  ADD CONSTRAINT fk_purchase_request_detail_header FOREIGN KEY (request_id) REFERENCES tbl_purchase_request(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_goods_receipt_detail
  ADD CONSTRAINT fk_goods_receipt_detail_header FOREIGN KEY (goods_receipt_id) REFERENCES tbl_goods_receipt(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tbl_remission_guide_detail
  ADD CONSTRAINT fk_remission_guide_detail_header FOREIGN KEY (remission_guide_id) REFERENCES tbl_remission_guide(id) ON UPDATE CASCADE ON DELETE RESTRICT;
