UPDATE `tbl_product`
SET `tax_affectation_code` = CASE UPPER(TRIM(`tax_affectation_code`))
  WHEN '' THEN '10'
  WHEN 'IGV' THEN '10'
  WHEN 'GRAVADO' THEN '10'
  WHEN 'EXO' THEN '20'
  WHEN 'EXONERADO' THEN '20'
  WHEN 'INA' THEN '30'
  WHEN 'INAFECTO' THEN '30'
  WHEN 'EXP' THEN '40'
  WHEN 'EXPORTACION' THEN '40'
  WHEN 'EXPORTACIÓN' THEN '40'
  ELSE `tax_affectation_code`
END;
