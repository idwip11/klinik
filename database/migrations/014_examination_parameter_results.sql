CREATE TABLE IF NOT EXISTS examination_parameter_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    examination_id BIGINT UNSIGNED NOT NULL,
    parameter_id BIGINT UNSIGNED NOT NULL,
    parameter_code_snapshot VARCHAR(64) NOT NULL,
    parameter_label_snapshot VARCHAR(150) NOT NULL,
    unit_snapshot VARCHAR(32) NULL,
    value_numeric DECIMAL(20, 6) NULL,
    value_text VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY examination_parameter_results_unique (examination_id, parameter_id),
    KEY examination_parameter_results_parameter_index (parameter_id),
    KEY examination_parameter_results_code_index (parameter_code_snapshot),
    CONSTRAINT examination_parameter_results_examination_foreign FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE CASCADE,
    CONSTRAINT examination_parameter_results_parameter_foreign FOREIGN KEY (parameter_id) REFERENCES test_parameters(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
