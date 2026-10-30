-- Cada empleado representa una sola identidad de acceso al ERP.
ALTER TABLE tbl_user
    ADD CONSTRAINT uq_user_employee UNIQUE (employee_id);
