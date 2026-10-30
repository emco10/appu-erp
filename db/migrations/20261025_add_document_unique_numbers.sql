ALTER TABLE `tbl_invoice`
  ADD UNIQUE KEY `uq_invoice_series_number` (`series`, `number`);

ALTER TABLE `tbl_receipt`
  ADD UNIQUE KEY `uq_receipt_series_number` (`series`, `number`);

ALTER TABLE `tbl_credit_note`
  ADD UNIQUE KEY `uq_credit_note_series_number` (`series`, `number`);
