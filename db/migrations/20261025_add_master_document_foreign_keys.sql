-- Relaciones que mantienen la integridad entre responsables y documentos.
ALTER TABLE tbl_user
  ADD CONSTRAINT fk_user_employee FOREIGN KEY (employee_id) REFERENCES tbl_employee(id)
  ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_purchase_request
  ADD CONSTRAINT fk_purchase_request_requester FOREIGN KEY (requester_id) REFERENCES tbl_employee(id)
  ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_purchase
  ADD CONSTRAINT fk_purchase_order_link FOREIGN KEY (purchase_order_id) REFERENCES tbl_order(id)
  ON UPDATE CASCADE ON DELETE SET NULL;
ALTER TABLE tbl_order
  ADD CONSTRAINT fk_order_purchase_request FOREIGN KEY (purchase_request_id) REFERENCES tbl_purchase_request(id)
  ON UPDATE CASCADE ON DELETE SET NULL;
