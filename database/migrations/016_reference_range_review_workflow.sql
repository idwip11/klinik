ALTER TABLE reference_ranges
    MODIFY approved_by BIGINT UNSIGNED NULL,
    MODIFY approved_at DATETIME NULL,
    ADD COLUMN approval_status ENUM('draft', 'approved', 'rejected') NOT NULL DEFAULT 'approved' AFTER is_active,
    ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER approval_status,
    ADD COLUMN submitted_at DATETIME NULL AFTER created_by,
    ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER submitted_at,
    ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
    ADD COLUMN review_note VARCHAR(255) NULL AFTER reviewed_at,
    ADD CONSTRAINT reference_ranges_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT reference_ranges_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT;

UPDATE reference_ranges
SET created_by = approved_by,
    submitted_at = COALESCE(approved_at, created_at),
    reviewed_by = approved_by,
    reviewed_at = approved_at,
    approval_status = 'approved'
WHERE created_by IS NULL;

ALTER TABLE reference_ranges
    MODIFY created_by BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY reference_ranges_test_version_unique (test_code, version),
    ADD KEY reference_ranges_review_queue_index (approval_status, submitted_at),
    ADD KEY reference_ranges_active_scope_index (test_code, sex, is_active, min_age_days, max_age_days);
