ALTER TABLE patients
    ADD COLUMN archived_at DATETIME NULL AFTER updated_at,
    ADD COLUMN archived_by BIGINT UNSIGNED NULL AFTER archived_at,
    ADD KEY patients_archived_at_index (archived_at),
    ADD CONSTRAINT patients_archived_by_foreign FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE RESTRICT;
