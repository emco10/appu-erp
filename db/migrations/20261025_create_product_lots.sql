ALTER TABLE `tbl_goods_receipt_detail`
  ADD COLUMN `lot_number` varchar(50) NOT NULL DEFAULT '' AFTER `quantity`,
  ADD COLUMN `expiration_date` date NULL AFTER `lot_number`;

CREATE TABLE IF NOT EXISTS `tbl_product_lot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `lot_number` varchar(50) NOT NULL,
  `expiration_date` date NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0,
  `reserved_quantity` decimal(12,3) NOT NULL DEFAULT 0,
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_product_lot_warehouse` (`warehouse_id`,`product_id`,`lot_number`),
  KEY `idx_product_lot_expiration` (`expiration_date`),
  CONSTRAINT `fk_product_lot_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `tbl_warehouse` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_lot_product` FOREIGN KEY (`product_id`) REFERENCES `tbl_product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
