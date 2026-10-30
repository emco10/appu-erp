CREATE TABLE IF NOT EXISTS `tbl_document_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type` varchar(30) NOT NULL,
  `document_id` int(11) NOT NULL,
  `document_number` varchar(50) NOT NULL DEFAULT '',
  `previous_status` varchar(50) NOT NULL DEFAULT '',
  `new_status` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `comment` varchar(500) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_document_status_history` (`document_type`,`document_id`,`registration_date`),
  KEY `idx_document_status_user` (`user_id`,`registration_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
