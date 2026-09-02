UPDATE test_parameters
SET result_type = 'numeric',
    unit = 'COI',
    updated_at = UTC_TIMESTAMP()
WHERE code = 'HBSAG_ECLIA';

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'HBSAG_ECLIA', 'HBsAg Eclia', 'blood', 'numeric', 'COI', 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, 0.900000, 1, 1,
    NULL,
    'COI ≤ 0,9: Non Reaktif; COI > 1,0: Reaktif; 0,90–1,00: perlu konfirmasi sesuai prosedur laboratorium.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges WHERE test_code = 'HBSAG_ECLIA'), 1),
    1, 'approved',
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(),
    (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1), UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges
    WHERE test_code = 'HBSAG_ECLIA' AND is_active = 1 AND approval_status = 'approved'
      AND sex = 'ALL' AND min_age_days IS NULL AND max_age_days IS NULL
      AND min_age_years IS NULL AND max_age_years IS NULL
);
