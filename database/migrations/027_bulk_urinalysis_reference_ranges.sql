/*
 * Nilai rujukan universal dari daftar kolom F pada lembar kerja klinik.
 * Aturan dibuat aktif agar dapat langsung dipakai pada hasil pemeriksaan baru.
 * Jika aturan universal yang sama sudah ada, baris tersebut tidak dibuat ulang.
 */
INSERT INTO reference_ranges
    (test_code, display_name, section, result_kind, unit, sex,
     min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive,
     normal_values, reference_note, version, is_active, approval_status,
     created_by, submitted_at, approved_by, approved_at, created_at)
SELECT
    values_to_insert.test_code,
    values_to_insert.display_name,
    values_to_insert.section,
    values_to_insert.result_kind,
    values_to_insert.unit,
    'ALL',
    NULL, NULL, NULL, NULL,
    values_to_insert.lower_bound,
    values_to_insert.upper_bound,
    1, 1,
    values_to_insert.normal_values,
    'Nilai rujukan universal sesuai daftar kolom F; mohon verifikasi terhadap SOP klinik.',
    COALESCE((SELECT MAX(version) + 1 FROM reference_ranges r_version WHERE r_version.test_code = values_to_insert.test_code), 1),
    1, 'approved',
    admin_user.id, UTC_TIMESTAMP(), admin_user.id, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM (
    SELECT 'BTA_PREPARATION' AS test_code, 'Preparat BTA' AS display_name, 'blood' AS section, 'qualitative' AS result_kind, NULL AS unit, NULL AS lower_bound, NULL AS upper_bound, '["Negatif"]' AS normal_values
    UNION ALL SELECT 'MALARIA_PREPARATION', 'Preparat Malaria', 'blood', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'WIDAL_O', 'Salmonella typhi O', 'blood', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'WIDAL_AO', 'Salmonella typhi AO', 'blood', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'WIDAL_BO', 'Salmonella typhi BO', 'blood', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'WIDAL_H', 'Salmonella typhi H', 'blood', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'URINE_COLOR', 'Warna Urine', 'urine', 'qualitative', NULL, NULL, NULL, '["Kuning Muda"]'
    UNION ALL SELECT 'URINE_CLARITY', 'Kejernihan', 'urine', 'qualitative', NULL, NULL, NULL, '["Jernih"]'
    UNION ALL SELECT 'URINE_SG', 'Berat Jenis', 'urine', 'numeric', NULL, 1.010000, 1.030000, NULL
    UNION ALL SELECT 'URINE_PH', 'pH', 'urine', 'numeric', NULL, 4.800000, 7.400000, NULL
    UNION ALL SELECT 'URINE_PROTEIN', 'Protein Urine', 'urine', 'qualitative', NULL, NULL, NULL, '["<15"]'
    UNION ALL SELECT 'URINE_GLUCOSE', 'Glukosa/Reduksi', 'urine', 'qualitative', 'mg/dL', NULL, NULL, '["<30"]'
    UNION ALL SELECT 'URINE_UROBILINOGEN', 'Urobilinogen', 'urine', 'qualitative', 'mg/dL', NULL, NULL, '["<1"]'
    UNION ALL SELECT 'URINE_BILIRUBIN', 'Bilirubin', 'urine', 'qualitative', 'mg/dL', NULL, NULL, '["<0,2"]'
    UNION ALL SELECT 'URINE_OCCULT_BLOOD', 'Darah Samar', 'urine', 'qualitative', 'mg/dL', NULL, NULL, '["≤0,03"]'
    UNION ALL SELECT 'URINE_KETONE', 'Keton', 'urine', 'qualitative', 'mg/dL', NULL, NULL, '["<5"]'
    UNION ALL SELECT 'URINE_NITRITE', 'Nitrit', 'urine', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'URINE_LEUKOCYTE_ESTERASE', 'Lekosit', 'urine', 'qualitative', 'sel/µL', NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'URINE_ERYTHROCYTES', 'Eritrosit Sedimen', 'urine', 'qualitative', '/LPB', NULL, NULL, '["0 - 2"]'
    UNION ALL SELECT 'URINE_LEUKOCYTES', 'Lekosit Sedimen', 'urine', 'qualitative', '/LPB', NULL, NULL, '["1 - 4"]'
    UNION ALL SELECT 'URINE_SQUAMOUS', 'Squamos / Sel Epitel', 'urine', 'qualitative', '/LPB', NULL, NULL, '["0 - 2"]'
    UNION ALL SELECT 'URINE_CASTS', 'Silinder', 'urine', 'qualitative', NULL, NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'URINE_CRYSTALS', 'Kristal', 'urine', 'qualitative', '/LPK', NULL, NULL, '["Negatif"]'
    UNION ALL SELECT 'URINE_BACTERIA', 'Bakteri', 'urine', 'qualitative', '/LPK', NULL, NULL, '["Negatif"]'
) AS values_to_insert
CROSS JOIN (
    SELECT id
    FROM users
    WHERE role = 'admin' AND is_active = 1
    ORDER BY id
    LIMIT 1
) AS admin_user
LEFT JOIN reference_ranges existing_range
    ON existing_range.test_code = values_to_insert.test_code
   AND existing_range.is_active = 1
   AND existing_range.approval_status = 'approved'
   AND existing_range.sex = 'ALL'
   AND existing_range.min_age_days IS NULL
   AND existing_range.max_age_days IS NULL
   AND existing_range.min_age_years IS NULL
   AND existing_range.max_age_years IS NULL
WHERE existing_range.id IS NULL;
