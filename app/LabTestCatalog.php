<?php

declare(strict_types=1);

final class LabTestCatalog
{
    /** @return array<string, array{field: string, label: string, section: string, kind: string}> */
    public static function all(): array
    {
        return [
            'HGB' => ['field' => 'hgb', 'label' => 'Hemoglobin (HGB)', 'section' => 'blood', 'kind' => 'numeric'],
            'WBC' => ['field' => 'wbc', 'label' => 'Leukosit (WBC)', 'section' => 'blood', 'kind' => 'numeric'],
            'PLT' => ['field' => 'plt', 'label' => 'Trombosit (PLT)', 'section' => 'blood', 'kind' => 'numeric'],
            'RBC' => ['field' => 'rbc', 'label' => 'Eritrosit (RBC)', 'section' => 'blood', 'kind' => 'numeric'],
            'HCT' => ['field' => 'hct', 'label' => 'Hematokrit (HCT)', 'section' => 'blood', 'kind' => 'numeric'],
            'LYMPH_PCT' => ['field' => 'lymph_pct', 'label' => 'Limfosit (LYMPH%)', 'section' => 'blood', 'kind' => 'numeric'],
            'MID_PCT' => ['field' => 'mid_pct', 'label' => 'MID%', 'section' => 'blood', 'kind' => 'numeric'],
            'GRAN_PCT' => ['field' => 'gran_pct', 'label' => 'GRAN%', 'section' => 'blood', 'kind' => 'numeric'],
            'MCV' => ['field' => 'mcv', 'label' => 'MCV', 'section' => 'blood', 'kind' => 'numeric'],
            'MCH' => ['field' => 'mch', 'label' => 'MCH', 'section' => 'blood', 'kind' => 'numeric'],
            'MCHC' => ['field' => 'mchc', 'label' => 'MCHC', 'section' => 'blood', 'kind' => 'numeric'],
            'EOSINOPHIL' => ['field' => 'eosinophil', 'label' => 'Eosinofil', 'section' => 'blood', 'kind' => 'numeric'],
            'BASOPHIL' => ['field' => 'basophil', 'label' => 'Basofil', 'section' => 'blood', 'kind' => 'numeric'],
            'NEUTROPHIL' => ['field' => 'neutrophil', 'label' => 'Neutrofil', 'section' => 'blood', 'kind' => 'numeric'],
            'LYMPHOCYTE' => ['field' => 'lymphocyte', 'label' => 'Limfosit', 'section' => 'blood', 'kind' => 'numeric'],
            'MONOCYTE' => ['field' => 'monocyte', 'label' => 'Monosit', 'section' => 'blood', 'kind' => 'numeric'],
            'GDS' => ['field' => 'gds', 'label' => 'Glukosa Darah Sewaktu', 'section' => 'blood', 'kind' => 'numeric'],
            'FBS' => ['field' => 'fasting_glucose', 'label' => 'Glukosa Darah Puasa', 'section' => 'blood', 'kind' => 'numeric'],
            'GLUCOSE_2H_PP' => ['field' => 'glucose_2h_pp', 'label' => 'Glukosa Darah 2 Jam PP', 'section' => 'blood', 'kind' => 'numeric'],
            'UREA' => ['field' => 'urea', 'label' => 'Ureum', 'section' => 'blood', 'kind' => 'numeric'],
            'CREATININE' => ['field' => 'creatinine', 'label' => 'Kreatinin', 'section' => 'blood', 'kind' => 'numeric'],
            'CHOL' => ['field' => 'cholesterol', 'label' => 'Kolesterol', 'section' => 'blood', 'kind' => 'numeric'],
            'UA' => ['field' => 'uric_acid', 'label' => 'Asam Urat', 'section' => 'blood', 'kind' => 'numeric'],
            'SGOT' => ['field' => 'sgot', 'label' => 'SGOT', 'section' => 'blood', 'kind' => 'numeric'],
            'SGPT' => ['field' => 'sgpt', 'label' => 'SGPT', 'section' => 'blood', 'kind' => 'numeric'],
            'WIDAL_O' => ['field' => 'widal_o', 'label' => 'Widal O', 'section' => 'blood', 'kind' => 'qualitative'],
            'WIDAL_AO' => ['field' => 'widal_ao', 'label' => 'Widal AO', 'section' => 'blood', 'kind' => 'qualitative'],
            'WIDAL_BO' => ['field' => 'widal_bo', 'label' => 'Widal BO', 'section' => 'blood', 'kind' => 'qualitative'],
            'WIDAL_H' => ['field' => 'widal_h', 'label' => 'Widal H', 'section' => 'blood', 'kind' => 'qualitative'],
            'URINE_COLOR' => ['field' => 'urine_color', 'label' => 'Warna Urine', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_CLARITY' => ['field' => 'urine_clarity', 'label' => 'Kejernihan', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_SG' => ['field' => 'urine_specific_gravity', 'label' => 'Berat Jenis', 'section' => 'urine', 'kind' => 'numeric'],
            'URINE_PH' => ['field' => 'urine_ph', 'label' => 'pH', 'section' => 'urine', 'kind' => 'numeric'],
            'URINE_PROTEIN' => ['field' => 'protein', 'label' => 'Protein Urine', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_GLUCOSE' => ['field' => 'glucose_reduction', 'label' => 'Glukosa/Reduksi', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_UROBILINOGEN' => ['field' => 'urobilinogen', 'label' => 'Urobilinogen', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_BILIRUBIN' => ['field' => 'bilirubin', 'label' => 'Bilirubin', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_OCCULT_BLOOD' => ['field' => 'occult_blood', 'label' => 'Darah Samar', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_KETONE' => ['field' => 'ketone', 'label' => 'Keton', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_NITRITE' => ['field' => 'nitrite', 'label' => 'Nitrit', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_LEUKOCYTE_ESTERASE' => ['field' => 'leukocyte_esterase', 'label' => 'Lekosit', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_ERYTHROCYTES' => ['field' => 'erythrocytes', 'label' => 'Eritrosit Sedimen', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_LEUKOCYTES' => ['field' => 'leukocytes', 'label' => 'Lekosit Sedimen', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_SQUAMOUS' => ['field' => 'squamous_cells', 'label' => 'Squamos', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_CASTS' => ['field' => 'casts', 'label' => 'Silinder', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_CRYSTALS' => ['field' => 'crystals', 'label' => 'Kristal', 'section' => 'urine', 'kind' => 'qualitative'],
            'URINE_BACTERIA' => ['field' => 'bacteria', 'label' => 'Bakteri', 'section' => 'urine', 'kind' => 'qualitative'],
            'DRUG_AMPHETAMINE' => ['field' => 'drug_amphetamine', 'label' => 'Amphetamine', 'section' => 'drug', 'kind' => 'qualitative'],
            'DRUG_MARIJUANA' => ['field' => 'drug_marijuana', 'label' => 'Marijuana', 'section' => 'drug', 'kind' => 'qualitative'],
            'DRUG_MORPHINE' => ['field' => 'drug_morphine', 'label' => 'Morfin', 'section' => 'drug', 'kind' => 'qualitative'],
            'DRUG_BENZODIAZEPINE' => ['field' => 'drug_benzodiazepine', 'label' => 'Benzodiazepine', 'section' => 'drug', 'kind' => 'qualitative'],
            'DRUG_METHAMPHETAMINE' => ['field' => 'drug_methamphetamine', 'label' => 'Methamphetamine', 'section' => 'drug', 'kind' => 'qualitative'],
            'DRUG_COCAINE' => ['field' => 'drug_cocaine', 'label' => 'Cocaine', 'section' => 'drug', 'kind' => 'qualitative'],
        ];
    }

    /** @return array{field: string, label: string, section: string, kind: string}|null */
    public static function find(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }
}
