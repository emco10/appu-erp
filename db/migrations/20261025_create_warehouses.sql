CREATE TABLE IF NOT EXISTS `tbl_warehouse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(300) NOT NULL DEFAULT '',
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_warehouse_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tbl_warehouse` (`id`,`code`,`name`,`address`) VALUES (1,'ALM-PRINCIPAL','Almacén principal','');

CREATE TABLE IF NOT EXISTS `tbl_warehouse_stock` (
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0,
  `reserved_quantity` decimal(12,3) NOT NULL DEFAULT 0,
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`warehouse_id`,`product_id`),
  CONSTRAINT `fk_warehouse_stock_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `tbl_warehouse` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_warehouse_stock_product` FOREIGN KEY (`product_id`) REFERENCES `tbl_product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tbl_warehouse_movement` ADD COLUMN `warehouse_id` int(11) NOT NULL DEFAULT 1 AFTER `product_id`;
ALTER TABLE `tbl_warehouse_movement` MODIFY COLUMN `quantity` decimal(12,3) NOT NULL;
INSERT IGNORE INTO `tbl_warehouse_stock` (`warehouse_id`,`product_id`,`quantity`)
SELECT 1, id, stock_quantity FROM tbl_product;
