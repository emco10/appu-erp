INSERT IGNORE INTO tbl_app_value_list (code, name, active_status)
VALUES ('CANAL_VENTA', 'Canal de ventas', 1);

INSERT INTO tbl_app_value_option (list_id, value_code, label, sort_order, active_status)
SELECT catalog.id, desired.value_code, desired.label, desired.sort_order, 1
FROM tbl_app_value_list catalog
CROSS JOIN (
    SELECT 'LICITACIONES' AS value_code, 'Licitaciones' AS label, 10 AS sort_order
    UNION ALL SELECT 'MARKETPLACE', 'Marketplace', 20
    UNION ALL SELECT 'CORPORATIVO', 'Corporativo', 30
    UNION ALL SELECT 'HORIZONTAL', 'Horizontal', 40
) desired
WHERE catalog.code = 'CANAL_VENTA'
  AND NOT EXISTS (
      SELECT 1 FROM tbl_app_value_option existing
      WHERE existing.list_id = catalog.id AND existing.value_code = desired.value_code
  );

INSERT IGNORE INTO tbl_custom_field_definition
    (entity, field_key, label, field_type, value_list_code, required_status, active_status, copy_to_target)
VALUES
    ('quotation', 'canal_venta', 'Canal de ventas', 'select', 'CANAL_VENTA', 0, 1, 1),
    ('sales_order', 'canal_venta', 'Canal de ventas', 'select', 'CANAL_VENTA', 0, 1, 1),
    ('remission_guide', 'canal_venta', 'Canal de ventas', 'select', 'CANAL_VENTA', 0, 1, 1),
    ('invoice', 'canal_venta', 'Canal de ventas', 'select', 'CANAL_VENTA', 0, 1, 1),
    ('receipt', 'canal_venta', 'Canal de ventas', 'select', 'CANAL_VENTA', 0, 1, 1);
