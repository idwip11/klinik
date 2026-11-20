UPDATE test_parameters
SET result_type = 'select',
    options_json = '["Non Reaktif","Reaktif"]',
    updated_at = UTC_TIMESTAMP()
WHERE code IN ('HBSAG_RAPID', 'HIV_RAPID');

UPDATE test_parameters
SET result_type = 'select',
    options_json = '["Negatif","Positif"]',
    updated_at = UTC_TIMESTAMP()
WHERE code = 'SYPHILIS_RAPID';
