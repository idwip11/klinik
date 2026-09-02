CREATE TABLE IF NOT EXISTS import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_filename VARCHAR(255) NOT NULL,
    source_sheet VARCHAR(100) NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    examination_type ENUM('mcu_general', 'drug_screen') NOT NULL,
    status ENUM('importing', 'completed', 'failed') NOT NULL DEFAULT 'importing',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
    failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
    warning_count INT UNSIGNED NOT NULL DEFAULT 0,
    started_by BIGINT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY import_batches_source_index (source_sha256, source_sheet),
    KEY import_batches_status_index (status, started_at),
    CONSTRAINT import_batches_user_foreign FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_errors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_batch_id BIGINT UNSIGNED NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    severity ENUM('warning', 'error') NOT NULL,
    field_name VARCHAR(100) NULL,
    error_code VARCHAR(64) NOT NULL,
    message VARCHAR(500) NOT NULL,
    row_fingerprint CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY import_errors_batch_row_index (import_batch_id, source_row_number),
    CONSTRAINT import_errors_batch_foreign FOREIGN KEY (import_batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE examinations
    ADD COLUMN examined_time TIME NULL AFTER examined_on,
    ADD COLUMN import_batch_id BIGINT UNSIGNED NULL AFTER purpose,
    ADD COLUMN source_sheet VARCHAR(100) NULL AFTER import_batch_id,
    ADD COLUMN source_row_number INT UNSIGNED NULL AFTER source_sheet,
    ADD COLUMN legacy_source_key CHAR(64) NULL AFTER source_row_number,
    ADD UNIQUE KEY examinations_legacy_source_unique (legacy_source_key),
    ADD KEY examinations_import_batch_index (import_batch_id),
    ADD CONSTRAINT examinations_import_batch_foreign FOREIGN KEY (import_batch_id) REFERENCES import_batches(id) ON DELETE RESTRICT;
