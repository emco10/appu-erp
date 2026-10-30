ALTER TABLE tbl_sales_order
  DROP INDEX uq_sales_order_quotation,
  ADD KEY idx_sales_order_quotation (quotation_id);
