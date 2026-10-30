-- Empleados ERP: conservar referencias históricas y controlar disponibilidad.
ALTER TABLE tbl_employee ADD COLUMN active_status TINYINT(1) NOT NULL DEFAULT 1 AFTER admission_date;
