ALTER TABLE `tbl_product`
  ADD COLUMN `barcode` varchar(50) NOT NULL DEFAULT '' AFTER `code`,
  ADD COLUMN `sunat_item_code` varchar(30) NOT NULL DEFAULT '' AFTER `barcode`,
  ADD COLUMN `sunat_unit_code` varchar(10) NOT NULL DEFAULT '' AFTER `unit_value`,
  ADD COLUMN `tax_affectation_code` varchar(10) NOT NULL DEFAULT '' AFTER `sunat_unit_code`,
  ADD COLUMN `osce_catalog_code` varchar(50) NOT NULL DEFAULT '' AFTER `tax_affectation_code`,
  ADD COLUMN `manufacturer_code` varchar(50) NOT NULL DEFAULT '' AFTER `osce_catalog_code`,
  ADD COLUMN `model` varchar(100) NOT NULL DEFAULT '' AFTER `manufacturer_code`;
