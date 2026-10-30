CREATE TABLE IF NOT EXISTS `tbl_sales_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `quotation_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL DEFAULT 0,
  `ruc` varchar(11) NOT NULL DEFAULT '',
  `customer_name` varchar(200) NOT NULL DEFAULT '',
  `address` varchar(300) NOT NULL DEFAULT '',
  `reference` varchar(300) NOT NULL DEFAULT '',
  `payment_days` int(11) NOT NULL DEFAULT 0,
  `issue_date` date NOT NULL,
  `delivery_date` date NOT NULL,
  `currency` varchar(50) NOT NULL DEFAULT '',
  `discount_rate` float NOT NULL DEFAULT 0,
  `discount_value` float NOT NULL DEFAULT 0,
  `total_sub` float NOT NULL DEFAULT 0,
  `total_tax` float NOT NULL DEFAULT 0,
  `total_net` float NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(500) NOT NULL DEFAULT '',
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_order_number` (`number`),
  UNIQUE KEY `uq_sales_order_quotation` (`quotation_id`),
  KEY `idx_sales_order_status` (`status`,`issue_date`),
  KEY `idx_sales_order_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_sales_order_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_code` varchar(100) NOT NULL DEFAULT '',
  `item_name` varchar(300) NOT NULL DEFAULT '',
  `item_description` varchar(300) NOT NULL DEFAULT '',
  `item_quantity` int(11) NOT NULL,
  `item_unit_price` float NOT NULL DEFAULT 0,
  `item_total` float NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sales_order_detail_order` (`sales_order_id`),
  KEY `idx_sales_order_detail_product` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tbl_invoice`
  ADD COLUMN `sales_order_id` int(11) DEFAULT NULL AFTER `quotation_id`;

ALTER TABLE `tbl_receipt`
  ADD COLUMN `sales_order_id` int(11) DEFAULT NULL AFTER `quotation_id`;

ALTER TABLE `tbl_credit_note`
  ADD COLUMN `sales_order_id` int(11) DEFAULT NULL AFTER `referenced_doc_type_id`;
