ALTER TABLE blood_results
    ADD COLUMN lymph_pct DECIMAL(7, 2) NULL AFTER hct,
    ADD COLUMN mid_pct DECIMAL(7, 2) NULL AFTER lymph_pct,
    ADD COLUMN gran_pct DECIMAL(7, 2) NULL AFTER mid_pct,
    ADD COLUMN mcv DECIMAL(8, 2) NULL AFTER gran_pct,
    ADD COLUMN mch DECIMAL(8, 2) NULL AFTER mcv,
    ADD COLUMN mchc DECIMAL(8, 2) NULL AFTER mch,
    ADD COLUMN eosinophil DECIMAL(7, 2) NULL AFTER mchc,
    ADD COLUMN basophil DECIMAL(7, 2) NULL AFTER eosinophil,
    ADD COLUMN neutrophil DECIMAL(7, 2) NULL AFTER basophil,
    ADD COLUMN lymphocyte DECIMAL(7, 2) NULL AFTER neutrophil,
    ADD COLUMN monocyte DECIMAL(7, 2) NULL AFTER lymphocyte;
