ALTER TABLE `tbl_remission_guide`
  DROP INDEX `uq_remission_guide_source`;

ALTER TABLE `tbl_remission_guide_detail`
  ADD COLUMN `source_detail_id` int(11) NOT NULL AFTER `remission_guide_id`,
  ADD KEY `idx_remission_guide_detail_source` (`source_detail_id`);
