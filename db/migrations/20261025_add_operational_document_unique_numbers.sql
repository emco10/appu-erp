ALTER TABLE `tbl_purchase`
  ADD UNIQUE KEY `uq_purchase_number` (`number`);

ALTER TABLE `tbl_order`
  ADD UNIQUE KEY `uq_order_number` (`number`);

ALTER TABLE `tbl_quotation`
  ADD UNIQUE KEY `uq_quotation_number` (`number`);
