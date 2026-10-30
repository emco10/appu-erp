CREATE OR REPLACE VIEW `vw_business_partner` AS
SELECT CONCAT('C:', client_id) AS bp_key, 'CUSTOMER' AS bp_type, client_id AS source_id, ruc AS tax_id, business_name AS legal_name, trade_name, address, email, phone, 1 AS active_status
FROM tbl_customer
UNION ALL
SELECT CONCAT('V:', id) AS bp_key, 'VENDOR' AS bp_type, id AS source_id, '' AS tax_id, business_name AS legal_name, business_name AS trade_name, address, '' AS email, contact1_phone AS phone, 1 AS active_status
FROM tbl_provider;

CREATE TABLE IF NOT EXISTS `tbl_document_flow` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `source_type` varchar(40) NOT NULL,
  `source_id` bigint(20) NOT NULL,
  `target_type` varchar(40) NOT NULL,
  `target_id` bigint(20) NOT NULL,
  `relationship_type` varchar(40) NOT NULL DEFAULT 'BASE_TO_RESULT',
  `quantity_applied` decimal(14,3) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_document_flow` (`source_type`,`source_id`,`target_type`,`target_id`,`relationship_type`), KEY `idx_document_flow_source` (`source_type`,`source_id`), KEY `idx_document_flow_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
