ALTER TABLE reference_ranges
    ADD COLUMN min_age_years SMALLINT UNSIGNED NULL AFTER max_age_days,
    ADD COLUMN max_age_years SMALLINT UNSIGNED NULL AFTER min_age_years,
    ADD KEY reference_ranges_active_year_scope_index (test_code, sex, is_active, min_age_years, max_age_years);
