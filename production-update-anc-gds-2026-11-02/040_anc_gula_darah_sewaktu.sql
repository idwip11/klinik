-- Tambah parameter Gula Darah Sewaktu ke panel ANC ibu hamil.
-- Nilai rujukan sama dengan GDS umum: < 200 mg/dL (batas atas eksklusif), khusus perempuan.
-- Ditempatkan setelah parameter darah (Sifilis, urutan 50) dan sebelum parameter urine (urutan 60).

UPDATE examination_panels
SET description = 'Pemeriksaan ANC khusus pasien perempuan: hemoglobin, golongan darah/Rhesus, HBsAg, HIV/AIDS, sifilis, gula darah sewaktu, albumin dan reduksi urine.',
    updated_at = UTC_TIMESTAMP()
WHERE code = 'anc_ibu_hamil';

INSERT INTO test_parameters
    (panel_id, code, label, subsection, result_type, unit, options_json, legacy_field,
     is_required, print_when_empty, is_active, display_order, created_at, updated_at)
SELECT p.id, 'ANC_GDS', 'Gula Darah Sewaktu', 'ANC ibu hamil', 'numeric', 'mg/dL', NULL, NULL,
       0, 1, 1, 55, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM examination_panels p
WHERE p.code = 'anc_ibu_hamil'
ON DUPLICATE KEY UPDATE
    label = VALUES(label), subsection = VALUES(subsection), result_type = VALUES(result_type),
    unit = VALUES(unit), options_json = VALUES(options_json), is_active = 1,
    display_order = VALUES(display_order), updated_at = UTC_TIMESTAMP();

INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive, normal_values, reference_note,
     version, is_active, approval_status, created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    'ANC_GDS', 'Gula Darah Sewaktu', 'blood', 'numeric', 'mg/dL', 'P',
    NULL, NULL, NULL, NULL,
    NULL, 200, 1, 0, NULL,
    'Sama dengan rujukan GDS umum (< 200 mg/dL); khusus perempuan (ANC).',
    COALESCE((SELECT MAX(r.version) + 1 FROM reference_ranges r WHERE r.test_code = 'ANC_GDS'), 1),
    1, 'approved', creator.id, UTC_TIMESTAMP(), creator.id, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1) creator
WHERE NOT EXISTS (
    SELECT 1 FROM reference_ranges r WHERE r.test_code = 'ANC_GDS' AND r.sex = 'P'
        AND r.is_active = 1 AND r.approval_status = 'approved'
);
