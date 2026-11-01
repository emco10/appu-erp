CREATE TABLE IF NOT EXISTS `tbl_company_settings` (
  `id` tinyint(1) NOT NULL,
  `tax_id` varchar(20) NOT NULL DEFAULT '',
  `legal_name` varchar(200) NOT NULL DEFAULT '',
  `trade_name` varchar(200) NOT NULL DEFAULT '',
  `address` varchar(300) NOT NULL DEFAULT '',
  `ubigeo` varchar(6) NOT NULL DEFAULT '',
  `department` varchar(100) NOT NULL DEFAULT '',
  `province` varchar(100) NOT NULL DEFAULT '',
  `district` varchar(100) NOT NULL DEFAULT '',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL DEFAULT '',
  `currency` varchar(10) NOT NULL DEFAULT 'PEN',
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tbl_company_settings` (`id`) VALUES (1);

CREATE TABLE IF NOT EXISTS `tbl_app_value_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `name` varchar(120) NOT NULL,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_app_value_list_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_app_value_option` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `list_id` int(11) NOT NULL,
  `value_code` varchar(60) NOT NULL,
  `label` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), KEY `idx_app_value_option_list` (`list_id`),
  CONSTRAINT `fk_app_value_option_list` FOREIGN KEY (`list_id`) REFERENCES `tbl_app_value_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_custom_field_definition` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity` varchar(50) NOT NULL,
  `field_key` varchar(60) NOT NULL,
  `label` varchar(120) NOT NULL,
  `field_type` enum('text','number','date','select','checkbox') NOT NULL DEFAULT 'text',
  `value_list_code` varchar(60) NOT NULL DEFAULT '',
  `required_status` tinyint(1) NOT NULL DEFAULT 0,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_custom_field_entity_key` (`entity`,`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_custom_field_value` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `field_definition_id` int(11) NOT NULL,
  `entity` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `field_value` text NOT NULL,
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_custom_field_value_record` (`field_definition_id`,`entity`,`record_id`),
  KEY `idx_custom_field_value_entity_record` (`entity`,`record_id`),
  CONSTRAINT `fk_custom_field_value_definition` FOREIGN KEY (`field_definition_id`) REFERENCES `tbl_custom_field_definition` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_permission` (`code`, `name`) VALUES
  ('management.view', 'Consultar gestion'),
  ('management.manage', 'Gestionar configuracion')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT IGNORE INTO `tbl_role_permission` (`role_id`, `permission_id`)
SELECT role.id, permission.id FROM `tbl_role` role CROSS JOIN `tbl_permission` permission
WHERE role.code = 'admin' AND permission.code IN ('management.view', 'management.manage');

INSERT IGNORE INTO `tbl_role_permission` (`role_id`, `permission_id`)
SELECT role.id, permission.id FROM `tbl_role` role CROSS JOIN `tbl_permission` permission
WHERE role.code = 'management' AND permission.code IN ('management.view', 'management.manage');
