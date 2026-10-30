CREATE TABLE IF NOT EXISTS `tbl_external_integration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `name` varchar(150) NOT NULL,
  `integration_type` enum('MARKETPLACE','LOGISTICS','FINANCIAL','WEBHOOK','OTHER') NOT NULL DEFAULT 'OTHER',
  `base_url` varchar(300) NOT NULL DEFAULT '',
  `configuration_json` text NOT NULL,
  `active_status` tinyint(1) NOT NULL DEFAULT 0,
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_external_integration_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
