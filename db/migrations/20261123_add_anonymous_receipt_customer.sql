-- Identidad técnica para boletas de consumidor final no identificado.
-- Se oculta de maestros y catálogos; conserva integridad referencial histórica.

INSERT INTO tbl_customer (
  ruc, business_name, trade_name, email, phone, cellphone, address,
  department_id, province_id, district_id,
  contact1_name, contact1_phone, contact2_name, contact2_phone,
  commission, default_currency, payment_days, credit_limit,
  price_list_id, sales_employee_id, active_status
)
SELECT
  '', 'CONSUMIDOR FINAL', '', '', '', '', '',
  '', '', '', '', '', '', '',
  0, 'PEN', 0, 0, NULL, NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM tbl_customer WHERE ruc = '');

UPDATE tbl_customer
SET business_name = 'CONSUMIDOR FINAL', active_status = 1
WHERE ruc = '';
