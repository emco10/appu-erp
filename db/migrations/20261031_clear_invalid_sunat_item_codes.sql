-- Los valores anteriores no pertenecen al catálogo 25 (UNSPSC) de SUNAT.
-- El código de producto SUNAT es opcional; el código interno se conserva.
UPDATE tbl_product
SET sunat_item_code = ''
WHERE code IN ('H001', 'H002')
  AND sunat_item_code IN ('42548648', '42548649');
