START TRANSACTION;

-- Create the replacement rules before archiving the previous versions.
-- Existing patient results and reference snapshots are not modified.
INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive, normal_values, reference_note,
     version, is_active, approval_status, created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    parameter.code, parameter.label, 'urine', 'qualitative', NULL, 'ALL',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 1, 1,
    CASE WHEN parameter.code = 'URINE_UROBILINOGEN' THEN '["Normal"]' ELSE '["Negatif"]' END,
    'Revisi urine 20/10/2026: Urobilinogen Normal; Bilirubin, Darah Samar, dan Keton Negatif; semua jenis kelamin dan usia.',
    COALESCE((SELECT MAX(r.version) + 1 FROM reference_ranges r WHERE r.test_code = parameter.code), 1),
    1, 'approved', creator.id, UTC_TIMESTAMP(), creator.id, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM test_parameters parameter
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1) creator
WHERE parameter.code IN ('URINE_UROBILINOGEN', 'URINE_BILIRUBIN', 'URINE_OCCULT_BLOOD', 'URINE_KETONE');

-- Only archive an old rule when its replacement was successfully inserted.
UPDATE reference_ranges previous_rule
INNER JOIN reference_ranges replacement
    ON replacement.test_code = previous_rule.test_code
   AND replacement.version > previous_rule.version
   AND replacement.reference_note = 'Revisi urine 20/10/2026: Urobilinogen Normal; Bilirubin, Darah Samar, dan Keton Negatif; semua jenis kelamin dan usia.'
   AND replacement.is_active = 1 AND replacement.approval_status = 'approved'
SET previous_rule.is_active = 0
WHERE previous_rule.test_code IN ('URINE_UROBILINOGEN', 'URINE_BILIRUBIN', 'URINE_OCCULT_BLOOD', 'URINE_KETONE')
    AND previous_rule.is_active = 1 AND previous_rule.approval_status = 'approved';

UPDATE test_parameters parameter
INNER JOIN reference_ranges replacement
    ON replacement.test_code = parameter.code
   AND replacement.reference_note = 'Revisi urine 20/10/2026: Urobilinogen Normal; Bilirubin, Darah Samar, dan Keton Negatif; semua jenis kelamin dan usia.'
   AND replacement.is_active = 1 AND replacement.approval_status = 'approved'
SET parameter.unit = NULL, parameter.updated_at = UTC_TIMESTAMP()
WHERE parameter.code IN ('URINE_UROBILINOGEN', 'URINE_BILIRUBIN', 'URINE_OCCULT_BLOOD', 'URINE_KETONE');

COMMIT;
