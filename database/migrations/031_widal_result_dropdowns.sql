UPDATE test_parameters
SET result_type = 'select',
    options_json = '["Negatif","(+) 1/80","(+) 1/160","(+) 1/320","(+) 1/640"]',
    updated_at = UTC_TIMESTAMP()
WHERE code IN ('WIDAL_O', 'WIDAL_AO', 'WIDAL_BO', 'WIDAL_H');
