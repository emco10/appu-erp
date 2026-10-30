-- Mantiene el estado real de clientes y proveedores en la vista unificada.
-- Las bajas de maestros son lógicas y no deben reaparecer como activas.
CREATE OR REPLACE VIEW vw_business_partner AS
SELECT CONCAT('C:', client_id) AS bp_key,
       'CUSTOMER' AS bp_type,
       client_id AS source_id,
       ruc AS tax_id,
       business_name AS legal_name,
       trade_name,
       address,
       email,
       phone,
       active_status
  FROM tbl_customer
UNION ALL
SELECT CONCAT('V:', id) AS bp_key,
       'VENDOR' AS bp_type,
       id AS source_id,
       '' AS tax_id,
       business_name AS legal_name,
       business_name AS trade_name,
       address,
       '' AS email,
       contact1_phone AS phone,
       active_status
  FROM tbl_provider;
