ALTER TABLE `tbl_product`
  ADD COLUMN IF NOT EXISTS `tracking_type` ENUM('NONE','SERIAL','LOT') NOT NULL DEFAULT 'NONE' AFTER `model`;

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

CREATE TABLE IF NOT EXISTS `tbl_sunat_document` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_type` enum('invoice','receipt','credit-note','debit-note','voided','daily-summary','despatch') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `sunat_type` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','queued','processing','accepted','observed','rejected','technical_error','voided') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `xml_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `digest_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdr_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sunat_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sunat_description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sunat_notes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sunat_notes_json`)),
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sunat_source` (`source_type`,`source_id`),
  UNIQUE KEY `uq_sunat_number` (`sunat_type`,`series`,`number`),
  UNIQUE KEY `uq_sunat_idempotency` (`idempotency_key`),
  KEY `idx_sunat_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_sunat_document_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sunat_document_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sunat_event_document` (`sunat_document_id`,`created_at`),
  CONSTRAINT `fk_sunat_event_document` FOREIGN KEY (`sunat_document_id`) REFERENCES `tbl_sunat_document` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW `vw_business_partner` AS
SELECT CONCAT('C:', `client_id`) AS `bp_key`,
       'CUSTOMER' AS `bp_type`,
       `client_id` AS `source_id`,
       `ruc` AS `tax_id`,
       `business_name` AS `legal_name`,
       `trade_name`,
       `address`,
       `email`,
       `phone`,
       `active_status`
  FROM `tbl_customer`
UNION ALL
SELECT CONCAT('V:', `id`) AS `bp_key`,
       'VENDOR' AS `bp_type`,
       `id` AS `source_id`,
       '' AS `tax_id`,
       `business_name` AS `legal_name`,
       `business_name` AS `trade_name`,
       `address`,
       '' AS `email`,
       `contact1_phone` AS `phone`,
       `active_status`
  FROM `tbl_provider`;
