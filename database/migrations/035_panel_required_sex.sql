ALTER TABLE examination_panels
    ADD COLUMN required_sex ENUM('L', 'P') NULL AFTER is_always_included;
