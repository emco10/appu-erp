ALTER TABLE `tbl_purchase_detail`
  ADD COLUMN `item_id` int(11) DEFAULT NULL AFTER `purchase_id`,
  ADD KEY `idx_purchase_detail_product` (`item_id`);

UPDATE `tbl_purchase_detail` detail
JOIN `tbl_product` product ON product.code = detail.item_code
SET detail.item_id = product.id
WHERE detail.item_id IS NULL;

CREATE TABLE IF NOT EXISTS `tbl_goods_receipt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` varchar(30) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Registrado',
  `receipt_date` date NOT NULL,
  `notes` varchar(500) NOT NULL DEFAULT '',
  `received_by` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_goods_receipt_number` (`number`),
  KEY `idx_goods_receipt_purchase` (`purchase_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_goods_receipt_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` int(11) NOT NULL,
  `purchase_detail_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_goods_receipt_detail_receipt` (`goods_receipt_id`),
  KEY `idx_goods_receipt_detail_purchase` (`purchase_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

