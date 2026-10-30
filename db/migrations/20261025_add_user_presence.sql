ALTER TABLE tbl_user
    ADD COLUMN last_seen_at DATETIME NULL AFTER registration_date;
