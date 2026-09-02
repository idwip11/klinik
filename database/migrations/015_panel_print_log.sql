ALTER TABLE print_logs
    MODIFY document_type ENUM('blood_general', 'urine_complete', 'newborn_blood', 'drug_screen', 'panel_results') NOT NULL;
