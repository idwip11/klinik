ALTER TABLE reference_ranges
    ADD COLUMN lower_inclusive TINYINT(1) NOT NULL DEFAULT 1 AFTER upper_bound,
    ADD COLUMN upper_inclusive TINYINT(1) NOT NULL DEFAULT 1 AFTER lower_inclusive;
