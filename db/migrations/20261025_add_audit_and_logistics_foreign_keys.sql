-- Asegura la trazabilidad de quién creó, recibió o modificó cada movimiento.
ALTER TABLE tbl_document_flow
  ADD CONSTRAINT fk_document_flow_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_document_status_history
  ADD CONSTRAINT fk_document_status_history_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_goods_receipt
  ADD KEY idx_goods_receipt_provider (provider_id),
  ADD KEY idx_goods_receipt_received_by (received_by),
  ADD CONSTRAINT fk_goods_receipt_purchase FOREIGN KEY (purchase_id) REFERENCES tbl_purchase (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_goods_receipt_provider FOREIGN KEY (provider_id) REFERENCES tbl_provider (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_goods_receipt_received_by FOREIGN KEY (received_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_inventory_transfer
  ADD CONSTRAINT fk_inventory_transfer_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_inventory_lot_application
  ADD KEY idx_inventory_lot_application_created_by (created_by),
  ADD CONSTRAINT fk_inventory_lot_application_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE SET NULL;
ALTER TABLE tbl_warehouse_movement
  ADD KEY idx_warehouse_movement_user (user_id),
  ADD CONSTRAINT fk_warehouse_movement_user FOREIGN KEY (user_id) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
