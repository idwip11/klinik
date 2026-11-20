START TRANSACTION;

-- Preserve previous rules and result snapshots; future evaluations use Negatif.
UPDATE reference_ranges
SET is_active = 0
WHERE test_code IN ('URINE_GLUCOSE', 'URINE_PROTEIN')
    AND is_active = 1 AND approval_status = 'approved';

-- These results are qualitative, not concentrations in mg/dL.
UPDATE test_parameters
SET unit = NULL, updated_at = UTC_TIMESTAMP()
WHERE code IN ('URINE_GLUCOSE', 'URINE_PROTEIN');

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive, normal_values, reference_note,
     version, is_active, approval_status, created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    parameter.code, parameter.label, 'urine', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1, '["Negatif"]',
    'Pembaruan sesuai permintaan pengguna pada 20/10/2026: Negatif untuk semua jenis kelamin dan usia.',
    COALESCE((SELECT MAX(r.version) + 1 FROM reference_ranges r WHERE r.test_code = parameter.code), 1),
    1, 'approved', creator.id, UTC_TIMESTAMP(), creator.id, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM test_parameters parameter
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1) creator
WHERE parameter.code IN ('URINE_GLUCOSE', 'URINE_PROTEIN');

COMMIT;
