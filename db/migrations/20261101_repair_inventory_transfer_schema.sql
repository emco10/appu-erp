-- Repairs production installations where the transfer-document expansion was
-- recorded in the migration baseline before its schema changes were present.

ALTER TABLE `tbl_inventory_transfer`
  ADD COLUMN IF NOT EXISTS `document_date` date NULL AFTER `transfer_date`,
  ADD COLUMN IF NOT EXISTS `comments` varchar(500) NOT NULL DEFAULT '' AFTER `observation`;

UPDATE `tbl_inventory_transfer`
SET `document_date` = `transfer_date`
WHERE `document_date` IS NULL;

CREATE TABLE IF NOT EXISTS `tbl_inventory_transfer_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `line_number` int unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit_cost` decimal(16,6) NOT NULL DEFAULT 0,
  `inventory_value` decimal(16,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_transfer_line` (`transfer_id`,`line_number`),
  KEY `idx_transfer_detail_product` (`product_id`),
  CONSTRAINT `fk_transfer_detail_header` FOREIGN KEY (`transfer_id`) REFERENCES `tbl_inventory_transfer` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transfer_detail_product` FOREIGN KEY (`product_id`) REFERENCES `tbl_product` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transfer_detail_from_warehouse` FOREIGN KEY (`from_warehouse_id`) REFERENCES `tbl_warehouse` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transfer_detail_to_warehouse` FOREIGN KEY (`to_warehouse_id`) REFERENCES `tbl_warehouse` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transfer_detail_lot` FOREIGN KEY (`lot_id`) REFERENCES `tbl_product_lot` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tbl_inventory_transfer_detail`
  (`transfer_id`,`line_number`,`product_id`,`from_warehouse_id`,`to_warehouse_id`,`lot_id`,`quantity`)
SELECT `id`,1,`product_id`,`from_warehouse_id`,`to_warehouse_id`,`lot_id`,`quantity`
FROM `tbl_inventory_transfer`;
