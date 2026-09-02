ALTER TABLE examination_result_flags
    ADD COLUMN reference_sex ENUM('ALL', 'L', 'P') NULL AFTER reference_range_id;
