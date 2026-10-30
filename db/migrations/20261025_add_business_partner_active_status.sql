-- Maestros SAP B1: conservar socios de negocio y controlar su disponibilidad.
ALTER TABLE tbl_customer ADD COLUMN active_status TINYINT(1) NOT NULL DEFAULT 1 AFTER commission;
ALTER TABLE tbl_provider ADD COLUMN active_status TINYINT(1) NOT NULL DEFAULT 1 AFTER registration_date;
