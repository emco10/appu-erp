ALTER TABLE `tbl_company_settings`
  ADD COLUMN IF NOT EXISTS `ubigeo` varchar(6) NOT NULL DEFAULT '' AFTER `address`,
  ADD COLUMN IF NOT EXISTS `department` varchar(100) NOT NULL DEFAULT '' AFTER `ubigeo`,
  ADD COLUMN IF NOT EXISTS `province` varchar(100) NOT NULL DEFAULT '' AFTER `department`,
  ADD COLUMN IF NOT EXISTS `district` varchar(100) NOT NULL DEFAULT '' AFTER `province`;
