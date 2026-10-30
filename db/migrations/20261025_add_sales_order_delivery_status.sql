ALTER TABLE `tbl_sales_order`
  ADD COLUMN `delivery_status` varchar(20) NOT NULL DEFAULT 'Pendiente' AFTER `status`,
  ADD KEY `idx_sales_order_delivery_status` (`delivery_status`);

