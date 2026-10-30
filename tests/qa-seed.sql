USE `db_appu_qa`;

START TRANSACTION;

INSERT IGNORE INTO `tbl_company_settings` (`id`, `tax_id`, `legal_name`, `trade_name`, `currency`)
VALUES (1, '20123456789', 'EMPRESA DE PRUEBA QA', 'APPU QA', 'PEN');

INSERT IGNORE INTO `tbl_role` (`code`, `name`, `description`) VALUES
  ('admin', 'Administrador QA', 'Perfil sintetico para pruebas'),
  ('sales', 'Ventas QA', 'Perfil sintetico para pruebas'),
  ('purchases', 'Compras QA', 'Perfil sintetico para pruebas');

INSERT INTO `tbl_permission` (`code`, `name`) VALUES
  ('dashboard.view', 'Consultar panel'),
  ('customers.view', 'Consultar clientes'),
  ('customers.manage', 'Gestionar clientes'),
  ('providers.view', 'Consultar proveedores'),
  ('providers.manage', 'Gestionar proveedores'),
  ('sales.view', 'Consultar ventas'),
  ('sales.manage', 'Gestionar ventas'),
  ('purchases.view', 'Consultar compras'),
  ('purchases.manage', 'Gestionar compras'),
  ('purchases.approve', 'Aprobar compras'),
  ('inventory.view', 'Consultar inventario'),
  ('inventory.manage', 'Gestionar inventario'),
  ('logistics.view', 'Consultar logística'),
  ('logistics.manage', 'Gestionar logística'),
  ('finance.view', 'Consultar finanzas'),
  ('finance.manage', 'Gestionar finanzas'),
  ('reports.view', 'Consultar reportes'),
  ('employees.view', 'Consultar empleados'),
  ('employees.manage', 'Gestionar empleados'),
  ('users.view', 'Consultar usuarios'),
  ('users.manage', 'Gestionar usuarios'),
  ('management.view', 'Consultar gestion'),
  ('management.manage', 'Gestionar configuracion')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT IGNORE INTO `tbl_role_permission` (`role_id`, `permission_id`)
SELECT role.`id`, permission.`id`
FROM `tbl_role` role
CROSS JOIN `tbl_permission` permission
WHERE role.`code` = 'admin';

INSERT IGNORE INTO `tbl_app_value_list` (`code`, `name`)
VALUES ('QA_BASE', 'Valores base QA');

INSERT IGNORE INTO `tbl_financial_account` (`code`, `name`, `account_type`, `currency`)
VALUES ('QA-CASH', 'Caja QA', 'Caja', 'PEN');

INSERT IGNORE INTO `tbl_document_series` (`document_type`, `series`, `next_number`) VALUES
  ('INVOICE', 'QAI1', 1),
  ('RECEIPT', 'QAB1', 1),
  ('CREDIT_NOTE', 'QAC1', 1),
  ('QUOTATION', 'QAQ1', 1),
  ('SALES_ORDER', 'QAO1', 1),
  ('PURCHASE', 'QAP1', 1),
  ('PURCHASE_REQUEST', 'QAS1', 1),
  ('ORDER', 'QAO2', 1),
  ('GOODS_RECEIPT', 'QAGR', 1),
  ('REMISSION_GUIDE', 'QARG', 1),
  ('INCOMING_PAYMENT', 'QAIP', 1),
  ('OUTGOING_PAYMENT', 'QAOP', 1),
  ('STOCK_TRANSFER', 'QAST', 1),
  ('LANDED_COST', 'QALC', 1),
  ('INVENTORY_ENTRY', 'QAEI', 1),
  ('INVENTORY_ISSUE', 'QASI', 1);

INSERT IGNORE INTO `tbl_price_list` (`code`, `name`, `currency`)
VALUES ('GENERAL', 'Lista general QA', 'PEN');

INSERT IGNORE INTO `tbl_employee`
  (`name`, `last_name_1`, `last_name_2`, `id_doc_type`, `id_doc_number`, `civil_status`,
   `email`, `phone`, `address`, `job`, `study_level`, `study_career`, `birth_date`, `admission_date`)
VALUES
  ('Usuario', 'Prueba', 'QA', 'DNI', 'QA-EMPLOYEE-0001', 'Soltero',
   'usuario.qa@example.test', '', 'Direccion de prueba', 'QA', '', '', '1990-01-01', '2026-01-01');

INSERT IGNORE INTO `tbl_user` (`username`, `password`, `employee_id`, `role_id`, `photo_url`)
SELECT 'qa_smoke', '$2y$10$wH4Zb8FS2wFJbHq8w0Q5veIbrplcru38Q2oY4xk2v2lLhF2F3Tb7K',
       employee.`id`, role.`id`, ''
FROM `tbl_employee` employee
CROSS JOIN `tbl_role` role
WHERE employee.`id_doc_number` = 'QA-EMPLOYEE-0001'
  AND role.`code` = 'admin';

INSERT IGNORE INTO `tbl_provider`
  (`code`, `business_name`, `address`, `country`, `city`, `district`,
   `contact1_name`, `contact1_phone`, `contact2_name`, `contact2_phone`,
   `bank1_name`, `bank1_currency`, `bank1_account_number`, `bank1_account_holder`,
   `bank2_name`, `bank2_currency`, `bank2_account_number`, `bank2_account_holder`)
VALUES
  ('QA-BASE', 'PROVEEDOR DE PRUEBA QA', '', 'Peru', '', '', '', '', '', '',
   '', '', '', '', '', '', '', '');

INSERT IGNORE INTO `tbl_product`
  (`code`, `barcode`, `sunat_item_code`, `brand`, `name`, `description`, `stock_quantity`,
   `unit_price`, `unit_value`, `sunat_unit_code`, `tax_affectation_code`, `osce_catalog_code`,
   `manufacturer_code`, `model`, `active_status`)
VALUES
  ('QA-ART-001', '', '', 'APPU QA', 'ARTICULO DE PRUEBA QA', 'Fixture sintetico para pruebas', 0,
   10, 'UND', '', '', '', '', '', 1);

INSERT IGNORE INTO `tbl_customer`
  (`ruc`, `business_name`, `trade_name`, `email`, `phone`, `cellphone`, `address`,
   `department_id`, `province_id`, `district_id`, `contact1_name`, `contact1_phone`,
   `contact2_name`, `contact2_phone`, `commission`, `price_list_id`)
SELECT '20999999980', 'CLIENTE DE PRUEBA QA', 'CLIENTE QA', 'cliente.qa@example.test', '', '',
       'Direccion de prueba', '', '', '', '', '', '', '', 0, `id`
FROM `tbl_price_list`
WHERE `code` = 'GENERAL';

INSERT IGNORE INTO `tbl_warehouse` (`code`, `name`, `address`)
VALUES ('QA-BASE', 'Almacen de prueba QA', 'Direccion de prueba');

INSERT IGNORE INTO `ubigeo_peru_departments` (`id`, `name`)
VALUES ('15', 'Lima');

INSERT IGNORE INTO `ubigeo_peru_provinces` (`id`, `name`, `department_id`)
VALUES ('1501', 'Lima', '15');

INSERT IGNORE INTO `ubigeo_peru_districts` (`id`, `name`, `province_id`, `department_id`)
VALUES ('150131', 'San Isidro', '1501', '15');

COMMIT;
