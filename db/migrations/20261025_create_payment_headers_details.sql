CREATE TABLE IF NOT EXISTS `tbl_incoming_payment` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'PEN',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Efectivo',
  `financial_account_id` int(11) DEFAULT NULL,
  `reference` varchar(100) NOT NULL DEFAULT '',
  `notes` varchar(300) NOT NULL DEFAULT '',
  `status` enum('Registrado','Anulado') NOT NULL DEFAULT 'Registrado',
  `created_by` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_incoming_payment_number` (`payment_number`), KEY `idx_incoming_payment_customer_date` (`customer_id`,`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_incoming_payment_detail` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `incoming_payment_id` bigint(20) NOT NULL,
  `account_receivable_id` bigint(20) NOT NULL,
  `applied_amount` decimal(14,2) NOT NULL,
  `notes` varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), UNIQUE KEY `uq_incoming_payment_account` (`incoming_payment_id`,`account_receivable_id`), CONSTRAINT `fk_incoming_payment_detail_header` FOREIGN KEY (`incoming_payment_id`) REFERENCES `tbl_incoming_payment` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_incoming_payment_detail_account` FOREIGN KEY (`account_receivable_id`) REFERENCES `tbl_accounts_receivable` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_outgoing_payment` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(30) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'PEN',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Transferencia',
  `financial_account_id` int(11) DEFAULT NULL,
  `reference` varchar(100) NOT NULL DEFAULT '',
  `notes` varchar(300) NOT NULL DEFAULT '',
  `status` enum('Registrado','Anulado') NOT NULL DEFAULT 'Registrado',
  `created_by` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_outgoing_payment_number` (`payment_number`), KEY `idx_outgoing_payment_provider_date` (`provider_id`,`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_outgoing_payment_detail` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `outgoing_payment_id` bigint(20) NOT NULL,
  `account_payable_id` bigint(20) NOT NULL,
  `applied_amount` decimal(14,2) NOT NULL,
  `notes` varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), UNIQUE KEY `uq_outgoing_payment_account` (`outgoing_payment_id`,`account_payable_id`), CONSTRAINT `fk_outgoing_payment_detail_header` FOREIGN KEY (`outgoing_payment_id`) REFERENCES `tbl_outgoing_payment` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_outgoing_payment_detail_account` FOREIGN KEY (`account_payable_id`) REFERENCES `tbl_accounts_payable` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
