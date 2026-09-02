INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'SYPHILIS_RAPID', 'Sifilis Rapid', 'blood', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1,
    '["Negatif"]', 'Nilai rujukan universal: Negatif.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges WHERE test_code = 'SYPHILIS_RAPID'), 1),
    1, 'approved',
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(),
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges
    WHERE test_code = 'SYPHILIS_RAPID' AND is_active = 1 AND approval_status = 'approved'
      AND sex = 'ALL' AND min_age_days IS NULL AND max_age_days IS NULL
      AND min_age_years IS NULL AND max_age_years IS NULL
);

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'DENGUE_IGG', 'DHF Dengue IgG', 'blood', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1,
    '["Negatif"]', 'Nilai rujukan universal: Negatif.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges WHERE test_code = 'DENGUE_IGG'), 1),
    1, 'approved',
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(),
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges
    WHERE test_code = 'DENGUE_IGG' AND is_active = 1 AND approval_status = 'approved'
      AND sex = 'ALL' AND min_age_days IS NULL AND max_age_days IS NULL
      AND min_age_years IS NULL AND max_age_years IS NULL
);

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'DENGUE_IGM', 'DHF Dengue IgM', 'blood', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1,
    '["Negatif"]', 'Nilai rujukan universal: Negatif.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges WHERE test_code = 'DENGUE_IGM'), 1),
    1, 'approved',
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(),
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges
    WHERE test_code = 'DENGUE_IGM' AND is_active = 1 AND approval_status = 'approved'
      AND sex = 'ALL' AND min_age_days IS NULL AND max_age_days IS NULL
      AND min_age_years IS NULL AND max_age_years IS NULL
);

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'DENGUE_NS1', 'NS1 Dengue', 'blood', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1,
    '["Negatif"]', 'Nilai rujukan universal: Negatif.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges WHERE test_code = 'DENGUE_NS1'), 1),
    1, 'approved',
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(),
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges
    WHERE test_code = 'DENGUE_NS1' AND is_active = 1 AND approval_status = 'approved'
      AND sex = 'ALL' AND min_age_days IS NULL AND max_age_days IS NULL
      AND min_age_years IS NULL AND max_age_years IS NULL
);

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'TYPHI_IGM', 'Salmonella Typhi IgM', 'blood', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1,
    '["Negatif"]', 'Nilai rujukan universal: Negatif.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges WHERE test_code = 'TYPHI_IGM'), 1),
    1, 'approved',
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(),
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges
    WHERE test_code = 'TYPHI_IGM' AND is_active = 1 AND approval_status = 'approved'
      AND sex = 'ALL' AND min_age_days IS NULL AND max_age_days IS NULL
      AND min_age_years IS NULL AND max_age_years IS NULL
);

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'PREGNANCY_TEST', 'Tes Kehamilan', 'blood', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1,
    '["Negatif"]', 'Nilai rujukan universal: Negatif.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges WHERE test_code = 'PREGNANCY_TEST'), 1),
    1, 'approved',
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(),
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges
    WHERE test_code = 'PREGNANCY_TEST' AND is_active = 1 AND approval_status = 'approved'
      AND sex = 'ALL' AND min_age_days IS NULL AND max_age_days IS NULL
      AND min_age_years IS NULL AND max_age_years IS NULL
);
