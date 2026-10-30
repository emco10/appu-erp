-- Usuarios ERP: conservar responsables históricos y controlar acceso vigente.
ALTER TABLE tbl_user ADD COLUMN active_status TINYINT(1) NOT NULL DEFAULT 1 AFTER role_id;
