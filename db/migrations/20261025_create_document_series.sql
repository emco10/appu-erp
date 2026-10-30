CREATE TABLE IF NOT EXISTS `tbl_document_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type` enum('INVOICE','RECEIPT','CREDIT_NOTE') NOT NULL,
  `series` varchar(10) NOT NULL,
  `next_number` int(11) NOT NULL DEFAULT 1,
  `number_padding` tinyint(2) NOT NULL DEFAULT 7,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_document_series` (`document_type`,`series`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `tbl_document_series` (`document_type`,`series`,`next_number`,`number_padding`) VALUES ('INVOICE','F001',1,7),('RECEIPT','B001',1,7),('CREDIT_NOTE','FNC1',1,7),('CREDIT_NOTE','BNC1',1,7);
