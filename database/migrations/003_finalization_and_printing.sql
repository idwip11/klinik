ALTER TABLE examinations
    MODIFY status ENUM('draft', 'completed', 'finalized') NOT NULL DEFAULT 'draft',
    ADD COLUMN finalized_by BIGINT UNSIGNED NULL AFTER completed_at,
    ADD COLUMN finalized_at DATETIME NULL AFTER finalized_by,
    ADD CONSTRAINT examinations_finalized_by_foreign FOREIGN KEY (finalized_by) REFERENCES users(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS reference_ranges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    test_code VARCHAR(64) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    section ENUM('blood', 'urine') NOT NULL,
    result_kind ENUM('numeric', 'qualitative') NOT NULL,
    unit VARCHAR(50) NULL,
    sex ENUM('ALL', 'L', 'P') NOT NULL DEFAULT 'ALL',
    min_age_days INT UNSIGNED NULL,
    max_age_days INT UNSIGNED NULL,
    lower_bound DECIMAL(18, 6) NULL,
    upper_bound DECIMAL(18, 6) NULL,
    normal_values LONGTEXT NULL,
    reference_note VARCHAR(255) NULL,
    version INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    approved_by BIGINT UNSIGNED NOT NULL,
    approved_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY reference_ranges_lookup_index (test_code, is_active, sex),
    KEY reference_ranges_approved_by_index (approved_by),
    CONSTRAINT reference_ranges_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examination_result_flags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    examination_id BIGINT UNSIGNED NOT NULL,
    test_code VARCHAR(64) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    section ENUM('blood', 'urine') NOT NULL,
    raw_value VARCHAR(255) NOT NULL,
    unit VARCHAR(50) NULL,
    reference_range_id BIGINT UNSIGNED NULL,
    reference_text VARCHAR(255) NULL,
    interpretation ENUM('low', 'normal', 'high', 'abnormal', 'not_assessed') NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY examination_result_flags_unique (examination_id, test_code),
    KEY examination_result_flags_range_index (reference_range_id),
    CONSTRAINT examination_result_flags_examination_foreign FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE CASCADE,
    CONSTRAINT examination_result_flags_range_foreign FOREIGN KEY (reference_range_id) REFERENCES reference_ranges(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS print_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    examination_id BIGINT UNSIGNED NOT NULL,
    printed_by BIGINT UNSIGNED NOT NULL,
    document_type ENUM('blood_general', 'urine_complete') NOT NULL,
    printed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY print_logs_examination_index (examination_id, printed_at),
    KEY print_logs_user_index (printed_by, printed_at),
    CONSTRAINT print_logs_examination_foreign FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE RESTRICT,
    CONSTRAINT print_logs_user_foreign FOREIGN KEY (printed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

