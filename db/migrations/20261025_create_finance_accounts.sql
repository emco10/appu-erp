CREATE TABLE IF NOT EXISTS `tbl_accounts_receivable` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `document_type` varchar(30) NOT NULL,
  `document_id` int(11) NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'PEN',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `balance_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `status` enum('Pendiente','Parcial','Pagada','Anulada') NOT NULL DEFAULT 'Pendiente',
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_receivable_document` (`document_type`,`document_id`), KEY `idx_receivable_customer_status` (`customer_id`,`status`), KEY `idx_receivable_due` (`due_date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_accounts_payable` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `document_type` varchar(30) NOT NULL,
  `document_id` int(11) NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'PEN',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `balance_amount` decimal(14,2) NOT NULL DEFAULT 0,
  `status` enum('Pendiente','Parcial','Pagada','Anulada') NOT NULL DEFAULT 'Pendiente',
  `last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_payable_document` (`document_type`,`document_id`), KEY `idx_payable_provider_status` (`provider_id`,`status`), KEY `idx_payable_due` (`due_date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_account_payment` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `account_type` enum('receivable','payable') NOT NULL,
  `account_id` bigint(20) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Efectivo',
  `reference` varchar(100) NOT NULL DEFAULT '',
  `notes` varchar(300) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_account_payment_account` (`account_type`,`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_permission` (`code`,`name`) VALUES ('finance.view','Consultar cuentas y pagos'),('finance.manage','Gestionar cuentas y pagos') ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
INSERT IGNORE INTO `tbl_role_permission` (`role_id`,`permission_id`) SELECT role.id, permission.id FROM tbl_role role CROSS JOIN tbl_permission permission WHERE role.code IN ('admin','management') AND permission.code IN ('finance.view','finance.manage');
