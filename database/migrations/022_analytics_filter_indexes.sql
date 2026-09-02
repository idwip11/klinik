ALTER TABLE examinations
    ADD KEY examinations_analytics_date_status_type_patient_index (examined_on, status, examination_type, patient_id);

ALTER TABLE patients
    ADD KEY patients_analytics_archived_insurance_sex_index (archived_at, insurance_type, sex, id);
