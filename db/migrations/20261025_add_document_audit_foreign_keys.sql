-- Cada documento y aprobación conserva el usuario que intervino en la operación.
ALTER TABLE tbl_approval_history
  ADD CONSTRAINT fk_approval_history_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_credit_note
  ADD CONSTRAINT fk_credit_note_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_delivery
  ADD CONSTRAINT fk_delivery_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_invoice
  ADD CONSTRAINT fk_invoice_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_receipt
  ADD CONSTRAINT fk_receipt_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_quotation
  ADD CONSTRAINT fk_quotation_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_sales_order
  ADD CONSTRAINT fk_sales_order_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_order
  ADD KEY idx_order_created_by (created_by),
  ADD KEY idx_order_approved_by (approved_by),
  ADD KEY idx_order_rejected_by (rejected_by),
  ADD CONSTRAINT fk_order_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_order_approved_by FOREIGN KEY (approved_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_order_rejected_by FOREIGN KEY (rejected_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_purchase
  ADD KEY idx_purchase_created_by (created_by),
  ADD KEY idx_purchase_approved_by (approved_by),
  ADD KEY idx_purchase_rejected_by (rejected_by),
  ADD CONSTRAINT fk_purchase_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_purchase_approved_by FOREIGN KEY (approved_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_purchase_rejected_by FOREIGN KEY (rejected_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_purchase_request
  ADD CONSTRAINT fk_purchase_request_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_remission_guide
  ADD CONSTRAINT fk_remission_guide_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
