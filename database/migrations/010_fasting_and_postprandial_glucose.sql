ALTER TABLE blood_results
    ADD COLUMN fasting_glucose DECIMAL(8, 2) NULL AFTER gds,
    ADD COLUMN glucose_2h_pp DECIMAL(8, 2) NULL AFTER fasting_glucose;
