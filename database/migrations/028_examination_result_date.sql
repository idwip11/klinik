ALTER TABLE examinations
    ADD COLUMN result_date DATE NULL AFTER examined_on;

UPDATE examinations
SET result_date = examined_on
WHERE result_date IS NULL;
