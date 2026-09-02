<section class="form-section" id="drug">
    <h2>Hasil skrining narkoba</h2>
    <p class="help-text">Masukkan istilah hasil yang digunakan klinik. Daftar nilai normal/abnormal dapat dikonfigurasi pada Rentang Referensi sebelum finalisasi.</p>
    <label class="checkbox-label"><input type="checkbox" id="drug-negative-all" aria-controls="drug-result-fields"> Negatif semua</label>
    <div class="field-grid" id="drug-result-fields">
        <?php foreach (['drug_amphetamine' => 'Amphetamine', 'drug_methamphetamine' => 'Methamphetamine', 'drug_morphine' => 'Morfin', 'drug_marijuana' => 'Marijuana', 'drug_benzodiazepine' => 'Benzodiazepine', 'drug_cocaine' => 'Cocaine'] as $field => $label): ?>
            <label><?= $label ?><input data-drug-result name="<?= $field ?>" maxlength="30" value="<?= View::e($examination[$field] ?? '') ?>" placeholder="Contoh: Negatif"></label>
        <?php endforeach; ?>
    </div>
</section>
<script>
(() => {
    const toggle = document.getElementById('drug-negative-all');
    const fields = document.querySelectorAll('[data-drug-result]');
    if (!toggle || fields.length === 0) return;

    toggle.addEventListener('change', () => {
        if (!toggle.checked) return;
        fields.forEach((field) => { field.value = 'Negatif'; });
    });

    fields.forEach((field) => {
        field.addEventListener('input', () => {
            if (field.value.trim().toLowerCase() !== 'negatif') toggle.checked = false;
        });
    });
})();
</script>
