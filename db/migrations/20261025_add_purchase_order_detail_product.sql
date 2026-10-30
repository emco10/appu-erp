ALTER TABLE `tbl_order_detail`
  ADD COLUMN `item_id` int(11) DEFAULT NULL AFTER `order_id`,
  ADD KEY `idx_order_detail_product` (`item_id`),
  ADD CONSTRAINT `fk_order_detail_product` FOREIGN KEY (`item_id`) REFERENCES `tbl_product` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;
