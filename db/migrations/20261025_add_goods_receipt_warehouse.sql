ALTER TABLE `tbl_goods_receipt`
  ADD COLUMN `warehouse_id` int(11) NOT NULL DEFAULT 1 AFTER `provider_id`,
  ADD KEY `idx_goods_receipt_warehouse` (`warehouse_id`),
  ADD CONSTRAINT `fk_goods_receipt_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `tbl_warehouse` (`id`);
