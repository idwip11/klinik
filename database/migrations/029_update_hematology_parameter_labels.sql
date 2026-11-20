UPDATE test_parameters
SET label = 'Hemoglobin (HGB)', updated_at = UTC_TIMESTAMP()
WHERE code = 'HGB';

UPDATE test_parameters
SET label = 'Leukosit (WBC)', updated_at = UTC_TIMESTAMP()
WHERE code = 'WBC';

UPDATE test_parameters
SET label = 'Trombosit (PLT)', updated_at = UTC_TIMESTAMP()
WHERE code = 'PLT';

UPDATE test_parameters
SET label = 'Eritrosit (RBC)', updated_at = UTC_TIMESTAMP()
WHERE code = 'RBC';

UPDATE test_parameters
SET label = 'Hematokrit (HCT)', updated_at = UTC_TIMESTAMP()
WHERE code = 'HCT';

UPDATE examination_parameter_results
SET parameter_label_snapshot = 'Hemoglobin (HGB)', updated_at = UTC_TIMESTAMP()
WHERE parameter_code_snapshot = 'HGB' AND parameter_label_snapshot = 'Hemoglobin';

UPDATE examination_parameter_results
SET parameter_label_snapshot = 'Leukosit (WBC)', updated_at = UTC_TIMESTAMP()
WHERE parameter_code_snapshot = 'WBC' AND parameter_label_snapshot = 'Leukosit';

UPDATE examination_parameter_results
SET parameter_label_snapshot = 'Trombosit (PLT)', updated_at = UTC_TIMESTAMP()
WHERE parameter_code_snapshot = 'PLT' AND parameter_label_snapshot = 'Trombosit';

UPDATE examination_parameter_results
SET parameter_label_snapshot = 'Eritrosit (RBC)', updated_at = UTC_TIMESTAMP()
WHERE parameter_code_snapshot = 'RBC' AND parameter_label_snapshot = 'Eritrosit';

UPDATE examination_parameter_results
SET parameter_label_snapshot = 'Hematokrit (HCT)', updated_at = UTC_TIMESTAMP()
WHERE parameter_code_snapshot = 'HCT' AND parameter_label_snapshot = 'Hematokrit';

UPDATE examination_result_flags
SET display_name = 'Hemoglobin (HGB)'
WHERE test_code = 'HGB' AND display_name = 'Hemoglobin';

UPDATE examination_result_flags
SET display_name = 'Leukosit (WBC)'
WHERE test_code = 'WBC' AND display_name = 'Leukosit';

UPDATE examination_result_flags
SET display_name = 'Trombosit (PLT)'
WHERE test_code = 'PLT' AND display_name = 'Trombosit';

UPDATE examination_result_flags
SET display_name = 'Eritrosit (RBC)'
WHERE test_code = 'RBC' AND display_name = 'Eritrosit';

UPDATE examination_result_flags
SET display_name = 'Hematokrit (HCT)'
WHERE test_code = 'HCT' AND display_name = 'Hematokrit';
