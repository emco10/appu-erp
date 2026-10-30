-- Integridad de cabeceras financieras: socio de negocio, cuenta y usuario.
ALTER TABLE tbl_accounts_receivable
  ADD CONSTRAINT fk_ar_customer FOREIGN KEY (customer_id) REFERENCES tbl_customer (client_id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_accounts_payable
  ADD CONSTRAINT fk_ap_provider FOREIGN KEY (provider_id) REFERENCES tbl_provider (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_incoming_payment
  ADD KEY idx_incoming_payment_customer (customer_id),
  ADD KEY idx_incoming_payment_account (financial_account_id),
  ADD KEY idx_incoming_payment_created_by (created_by),
  ADD CONSTRAINT fk_incoming_payment_customer FOREIGN KEY (customer_id) REFERENCES tbl_customer (client_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_incoming_payment_account FOREIGN KEY (financial_account_id) REFERENCES tbl_financial_account (id) ON UPDATE CASCADE ON DELETE SET NULL,
  ADD CONSTRAINT fk_incoming_payment_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_outgoing_payment
  ADD KEY idx_outgoing_payment_provider (provider_id),
  ADD KEY idx_outgoing_payment_account (financial_account_id),
  ADD KEY idx_outgoing_payment_created_by (created_by),
  ADD CONSTRAINT fk_outgoing_payment_provider FOREIGN KEY (provider_id) REFERENCES tbl_provider (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_outgoing_payment_account FOREIGN KEY (financial_account_id) REFERENCES tbl_financial_account (id) ON UPDATE CASCADE ON DELETE SET NULL,
  ADD CONSTRAINT fk_outgoing_payment_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE tbl_account_payment
  ADD CONSTRAINT fk_account_payment_created_by FOREIGN KEY (created_by) REFERENCES tbl_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
