CREATE TABLE IF NOT EXISTS `tbl_inventory_transfer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` varchar(50) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `transfer_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Registrada',
  `observation` varchar(250) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_transfer_number` (`number`),
  KEY `idx_inventory_transfer_date` (`transfer_date`),
  CONSTRAINT `fk_inventory_transfer_from_warehouse` FOREIGN KEY (`from_warehouse_id`) REFERENCES `tbl_warehouse` (`id`),
  CONSTRAINT `fk_inventory_transfer_to_warehouse` FOREIGN KEY (`to_warehouse_id`) REFERENCES `tbl_warehouse` (`id`),
  CONSTRAINT `fk_inventory_transfer_product` FOREIGN KEY (`product_id`) REFERENCES `tbl_product` (`id`),
  CONSTRAINT `fk_inventory_transfer_lot` FOREIGN KEY (`lot_id`) REFERENCES `tbl_product_lot` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tbl_document_series
    MODIFY document_type ENUM(
        'INVOICE','RECEIPT','CREDIT_NOTE','QUOTATION','SALES_ORDER',
        'PURCHASE','PURCHASE_REQUEST','ORDER','GOODS_RECEIPT','REMISSION_GUIDE',
        'INCOMING_PAYMENT','OUTGOING_PAYMENT','STOCK_TRANSFER'
    ) NOT NULL;

INSERT IGNORE INTO tbl_document_series (document_type, series, next_number, number_padding, active_status)
VALUES ('STOCK_TRANSFER', 'TR', 1, 7, 1);
