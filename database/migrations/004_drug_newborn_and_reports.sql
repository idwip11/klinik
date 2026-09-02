ALTER TABLE patients
    ADD COLUMN occupation VARCHAR(150) NULL AFTER phone;

ALTER TABLE examinations
    MODIFY examination_type ENUM('mcu_general', 'newborn_blood', 'drug_screen') NOT NULL DEFAULT 'mcu_general',
    ADD COLUMN purpose VARCHAR(255) NULL AFTER condition_note;

ALTER TABLE reference_ranges
    MODIFY section ENUM('blood', 'urine', 'drug') NOT NULL;

ALTER TABLE examination_result_flags
    MODIFY section ENUM('blood', 'urine', 'drug') NOT NULL;

ALTER TABLE print_logs
    MODIFY document_type ENUM('blood_general', 'urine_complete', 'newborn_blood', 'drug_screen') NOT NULL;

CREATE TABLE IF NOT EXISTS drug_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    examination_id BIGINT UNSIGNED NOT NULL,
    amphetamine VARCHAR(30) NULL,
    marijuana VARCHAR(30) NULL,
    morphine VARCHAR(30) NULL,
    benzodiazepine VARCHAR(30) NULL,
    methamphetamine VARCHAR(30) NULL,
    cocaine VARCHAR(30) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY drug_results_examination_unique (examination_id),
    CONSTRAINT drug_results_examination_foreign FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS widal_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    examination_id BIGINT UNSIGNED NOT NULL,
    widal_o VARCHAR(50) NULL,
    widal_ao VARCHAR(50) NULL,
    widal_bo VARCHAR(50) NULL,
    widal_h VARCHAR(50) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY widal_results_examination_unique (examination_id),
    CONSTRAINT widal_results_examination_foreign FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
