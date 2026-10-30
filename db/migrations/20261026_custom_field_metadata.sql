-- Apply once, after the 20261025 migrations. No operational rows are rewritten.
ALTER TABLE tbl_custom_field_definition
  ADD COLUMN searchable TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN copy_to_target TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN display_order INT NOT NULL DEFAULT 0,
  ADD COLUMN default_value VARCHAR(255) DEFAULT NULL,
  ADD COLUMN max_length INT DEFAULT NULL,
  ADD COLUMN min_value DECIMAL(20,6) DEFAULT NULL,
  ADD COLUMN max_value DECIMAL(20,6) DEFAULT NULL,
  ADD COLUMN validation_pattern VARCHAR(250) NOT NULL DEFAULT '',
  ADD COLUMN section VARCHAR(80) NOT NULL DEFAULT '';

CREATE INDEX idx_custom_field_search ON tbl_custom_field_value
  (field_definition_id, entity, field_value(100), record_id);
