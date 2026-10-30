ALTER TABLE `tbl_warehouse_movement`
  ADD COLUMN `source_type` varchar(30) DEFAULT NULL AFTER `doc_reference`,
  ADD COLUMN `source_id` int(11) DEFAULT NULL AFTER `source_type`,
  ADD KEY `idx_warehouse_source` (`source_type`, `source_id`),
  ADD KEY `idx_warehouse_product_date` (`product_id`, `registration_date`);

