INSERT INTO examination_panels
    (code, name, description, is_always_included, required_sex, is_active, display_order, created_at, updated_at)
VALUES
    ('anc_ibu_hamil', 'ANC ibu hamil', 'Pemeriksaan ANC khusus pasien perempuan: hemoglobin, golongan darah/Rhesus, HBsAg, HIV/AIDS, sifilis, albumin dan reduksi urine.', 0, 'P', 1, 70, UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE
    name = VALUES(name), description = VALUES(description), required_sex = VALUES(required_sex),
    is_always_included = 0, is_active = 1, display_order = VALUES(display_order), updated_at = UTC_TIMESTAMP();

INSERT INTO test_parameters
    (panel_id, code, label, subsection, result_type, unit, options_json, legacy_field,
     is_required, print_when_empty, is_active, display_order, created_at, updated_at)
VALUES
    ((SELECT id FROM examination_panels WHERE code = 'anc_ibu_hamil'), 'ANC_HGB', 'Hemoglobin', 'ANC ibu hamil', 'numeric', 'g/dL', NULL, NULL, 0, 1, 1, 10, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ((SELECT id FROM examination_panels WHERE code = 'anc_ibu_hamil'), 'ANC_BLOOD_GROUP', 'Golongan Darah / Rhesus', 'ANC ibu hamil', 'select', NULL, '["A / Rh (+)","A / Rh (-)","B / Rh (+)","B / Rh (-)","AB / Rh (+)","AB / Rh (-)","O / Rh (+)","O / Rh (-)"]', NULL, 0, 1, 1, 20, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ((SELECT id FROM examination_panels WHERE code = 'anc_ibu_hamil'), 'ANC_HBSAG_RAPID', 'HBsAg', 'ANC ibu hamil', 'select', NULL, '["Non Reaktif","Reaktif"]', NULL, 0, 1, 1, 30, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ((SELECT id FROM examination_panels WHERE code = 'anc_ibu_hamil'), 'ANC_HIV_RAPID', 'HIV/AIDS', 'ANC ibu hamil', 'select', NULL, '["Non Reaktif","Reaktif"]', NULL, 0, 1, 1, 40, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ((SELECT id FROM examination_panels WHERE code = 'anc_ibu_hamil'), 'ANC_SYPHILIS_RAPID', 'Sifilis', 'ANC ibu hamil', 'select', NULL, '["Negatif","Positif"]', NULL, 0, 1, 1, 50, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ((SELECT id FROM examination_panels WHERE code = 'anc_ibu_hamil'), 'ANC_URINE_ALBUMIN', 'Albumin Urine', 'ANC ibu hamil', 'text', NULL, NULL, NULL, 0, 1, 1, 60, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    ((SELECT id FROM examination_panels WHERE code = 'anc_ibu_hamil'), 'ANC_URINE_REDUCTION', 'Reduksi Urine', 'ANC ibu hamil', 'text', NULL, NULL, NULL, 0, 1, 1, 70, UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE
    label = VALUES(label), subsection = VALUES(subsection), result_type = VALUES(result_type),
    unit = VALUES(unit), options_json = VALUES(options_json), display_order = VALUES(display_order), updated_at = UTC_TIMESTAMP();

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive, normal_values, reference_note,
     version, is_active, approval_status, created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    source.code, source.label, source.section, source.kind, source.unit, 'P',
    NULL, NULL, NULL, NULL,
    source.lower_bound, source.upper_bound, 1, 1, source.normal_values,
    'Sesuai lembar ANC yang diberikan pengguna pada 20/10/2026; khusus perempuan.',
    COALESCE((SELECT MAX(r.version) + 1 FROM reference_ranges r WHERE r.test_code = source.code), 1),
    1, 'approved', creator.id, UTC_TIMESTAMP(), creator.id, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM (
    SELECT 'ANC_HGB' AS code, 'Hemoglobin' AS label, 'blood' AS section, 'numeric' AS kind,
           'g/dL' AS unit, 11.4 AS lower_bound, 15.1 AS upper_bound, NULL AS normal_values
    UNION ALL SELECT 'ANC_HBSAG_RAPID', 'HBsAg', 'blood', 'qualitative', NULL, NULL, NULL, '["Non Reaktif"]'
    UNION ALL SELECT 'ANC_HIV_RAPID', 'HIV/AIDS', 'blood', 'qualitative', NULL, NULL, NULL, '["Non Reaktif"]'
    UNION ALL SELECT 'ANC_SYPHILIS_RAPID', 'Sifilis', 'blood', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'ANC_URINE_ALBUMIN', 'Albumin Urine', 'urine', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'ANC_URINE_REDUCTION', 'Reduksi Urine', 'urine', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
) source
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1) creator
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges r WHERE r.test_code = source.code AND r.sex = 'P'
        AND r.is_active = 1 AND r.approval_status = 'approved'
);
