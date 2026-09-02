ALTER TABLE blood_results
    ADD COLUMN urea DECIMAL(8, 2) NULL AFTER gds,
    ADD COLUMN creatinine DECIMAL(8, 2) NULL AFTER urea;
