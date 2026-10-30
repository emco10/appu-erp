CREATE TABLE IF NOT EXISTS `tbl_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permission_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_role_permission` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_role_permission_permission` (`permission_id`),
  CONSTRAINT `fk_role_permission_role` FOREIGN KEY (`role_id`) REFERENCES `tbl_role` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permission_permission` FOREIGN KEY (`permission_id`) REFERENCES `tbl_permission` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_role` (`id`, `code`, `name`, `description`) VALUES
  (1, 'admin', 'Administrador', 'Acceso total al sistema'),
  (2, 'management', 'Gerencia', 'Consulta gerencial y reportes'),
  (3, 'sales', 'Ventas', 'Clientes, ventas y consulta de inventario'),
  (4, 'purchases', 'Compras', 'Proveedores, compras y consulta de inventario'),
  (5, 'warehouse', 'Almacen y logistica', 'Inventario, movimientos y entregas'),
  (6, 'read_only', 'Solo consulta', 'Consulta general sin modificaciones')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`);

INSERT INTO `tbl_permission` (`code`, `name`) VALUES
  ('dashboard.view', 'Ver inicio'),
  ('customers.view', 'Consultar clientes'),
  ('customers.manage', 'Gestionar clientes'),
  ('providers.view', 'Consultar proveedores'),
  ('providers.manage', 'Gestionar proveedores'),
  ('sales.view', 'Consultar ventas'),
  ('sales.manage', 'Gestionar ventas'),
  ('purchases.view', 'Consultar compras'),
  ('purchases.manage', 'Gestionar compras'),
  ('inventory.view', 'Consultar inventario'),
  ('inventory.manage', 'Gestionar inventario'),
  ('logistics.view', 'Consultar logistica'),
  ('logistics.manage', 'Gestionar logistica'),
  ('reports.view', 'Consultar reportes'),
  ('employees.view', 'Consultar empleados'),
  ('employees.manage', 'Gestionar empleados'),
  ('users.view', 'Consultar usuarios y perfiles'),
  ('users.manage', 'Gestionar usuarios y perfiles')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT IGNORE INTO `tbl_role_permission` (`role_id`, `permission_id`)
SELECT role.id, permission.id
FROM `tbl_role` role
CROSS JOIN `tbl_permission` permission
WHERE role.code = 'admin'
   OR (role.code = 'management' AND permission.code LIKE '%.view')
   OR (role.code = 'sales' AND permission.code IN (
       'dashboard.view', 'customers.view', 'customers.manage', 'sales.view', 'sales.manage',
       'inventory.view', 'logistics.view', 'reports.view'
   ))
   OR (role.code = 'purchases' AND permission.code IN (
       'dashboard.view', 'providers.view', 'providers.manage', 'purchases.view', 'purchases.manage',
       'inventory.view', 'reports.view'
   ))
   OR (role.code = 'warehouse' AND permission.code IN (
       'dashboard.view', 'inventory.view', 'inventory.manage', 'logistics.view', 'logistics.manage',
       'sales.view', 'purchases.view', 'reports.view'
   ))
   OR (role.code = 'read_only' AND permission.code LIKE '%.view');

ALTER TABLE `tbl_user`
  ADD COLUMN `role_id` int(11) NOT NULL DEFAULT 1 AFTER `employee_id`,
  ADD KEY `idx_user_role` (`role_id`),
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `tbl_role` (`id`);

