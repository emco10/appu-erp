-- El código del proveedor es su identificador operativo y no debe repetirse.
ALTER TABLE tbl_provider ADD UNIQUE KEY uq_provider_code (code);
