UPDATE reference_ranges
SET reference_note = 'Aturan warisan sistem lama; persetujuan klinik dikonfirmasi pada 30/08/2026.'
WHERE is_active = 1
  AND approval_status = 'approved'
  AND (reference_note IS NULL OR TRIM(reference_note) = '')
  AND test_code IN (
      'LYMPH_PCT', 'MID_PCT', 'GRAN_PCT', 'MCV', 'MCH', 'MCHC',
      'EOSINOPHIL', 'BASOPHIL', 'NEUTROPHIL', 'LYMPHOCYTE', 'MONOCYTE'
  );
