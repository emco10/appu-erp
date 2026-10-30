INSERT INTO `tbl_permission` (`code`, `name`) VALUES
  ('purchases.approve', 'Aprobar o rechazar compras')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT IGNORE INTO `tbl_role_permission` (`role_id`, `permission_id`)
SELECT role.id, permission.id
FROM `tbl_role` role
JOIN `tbl_permission` permission ON permission.code = 'purchases.approve'
WHERE role.code IN ('admin', 'management');

ALTER TABLE `tbl_order`
  ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `approver`,
  ADD COLUMN `submitted_at` datetime DEFAULT NULL AFTER `created_by`,
  ADD COLUMN `approved_by` int(11) DEFAULT NULL AFTER `submitted_at`,
  ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN `rejected_by` int(11) DEFAULT NULL AFTER `approved_at`,
  ADD COLUMN `rejected_at` datetime DEFAULT NULL AFTER `rejected_by`,
  ADD COLUMN `approval_comment` varchar(500) NOT NULL DEFAULT '' AFTER `rejected_at`,
  ADD COLUMN `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `exchange_rate_purchase`,
  ADD KEY `idx_order_approval_status` (`status`, `submitted_at`);

ALTER TABLE `tbl_purchase`
  ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `approver`,
  ADD COLUMN `submitted_at` datetime DEFAULT NULL AFTER `created_by`,
  ADD COLUMN `approved_by` int(11) DEFAULT NULL AFTER `submitted_at`,
  ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN `rejected_by` int(11) DEFAULT NULL AFTER `approved_at`,
  ADD COLUMN `rejected_at` datetime DEFAULT NULL AFTER `rejected_by`,
  ADD COLUMN `approval_comment` varchar(500) NOT NULL DEFAULT '' AFTER `rejected_at`,
  ADD COLUMN `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `exchange_rate_purchase`,
  ADD KEY `idx_purchase_approval_status` (`status`, `submitted_at`);

CREATE TABLE IF NOT EXISTS `tbl_approval_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type` enum('order','purchase') NOT NULL,
  `document_id` int(11) NOT NULL,
  `previous_status` varchar(30) NOT NULL,
  `new_status` varchar(30) NOT NULL,
  `comment` varchar(500) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_approval_document` (`document_type`, `document_id`, `registration_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

