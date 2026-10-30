-- Distingue la factura ordinaria de la factura de reserva. La factura de
-- reserva genera CxC al registrarse, pero el inventario se descarga con sus
-- guias de remision parciales.
ALTER TABLE tbl_invoice
    ADD COLUMN document_subtype VARCHAR(20) NOT NULL DEFAULT 'STANDARD' AFTER status,
    ADD KEY idx_invoice_subtype_status (document_subtype, status);

