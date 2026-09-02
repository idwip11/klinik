<?php
$catalogGroups = [];
foreach ($catalog as $code => $test) {
    $catalogGroups[(string) $test['panel_name']][$code] = $test;
}
$selectedKind = $catalog[$input['test_code'] ?? '']['kind'] ?? null;
$selectedParameter = $catalog[$input['test_code'] ?? ''] ?? null;
$sexLabels = ['P' => 'Perempuan', 'L' => 'Laki-laki', 'ALL' => 'Semua jenis kelamin'];
$referenceText = static function (array $range): string {
    return $range['result_kind'] === 'numeric'
        ? ReferenceEvaluator::referenceText($range)
        : implode(' / ', json_decode((string) ($range['normal_values'] ?? '[]'), true) ?: []);
};
$ageText = static function (array $range): string {
    $minimumYears = $range['min_age_years'] === null ? null : (int) $range['min_age_years'];
    $maximumYears = $range['max_age_years'] === null ? null : (int) $range['max_age_years'];
    if ($minimumYears !== null || $maximumYears !== null) {
        if ($minimumYears === null) {
            return 'Maksimal ' . View::formatNumber((string) $maximumYears) . ' tahun';
        }
        if ($maximumYears === null) {
            return 'Minimal ' . View::formatNumber((string) $minimumYears) . ' tahun';
        }

        return View::formatNumber((string) $minimumYears) . '–' . View::formatNumber((string) $maximumYears) . ' tahun';
    }
    $minimum = $range['min_age_days'] === null ? null : (int) $range['min_age_days'];
    $maximum = $range['max_age_days'] === null ? null : (int) $range['max_age_days'];
    if ($minimum === null && $maximum === null) {
        return 'Semua usia';
    }
    if ($minimum === null) {
        return 'Maksimal ' . View::formatNumber((string) $maximum) . ' hari';
    }
    if ($maximum === null) {
        return 'Minimal ' . View::formatNumber((string) $minimum) . ' hari';
    }

    return View::formatNumber((string) $minimum) . '–' . View::formatNumber((string) $maximum) . ' hari';
};
$approvedRanges = array_values(array_filter($ranges, static fn (array $range): bool => $range['approval_status'] === 'approved'));
?>
<section class="page-heading">
    <p class="eyebrow">Pengaturan klinis</p>
    <h1>Konfigurasi nilai rujukan</h1>
    <p>Buat aturan berdasarkan parameter, jenis kelamin, dan usia pasien. Aturan yang disimpan langsung digunakan untuk menilai hasil baru.</p>
</section>

<div class="notice reference-clinical-notice"><strong>Batas tanggung jawab:</strong> aplikasi memvalidasi format, overlap, umur, jenis kelamin, dan versi. Angka atau nilai normal tetap harus berasal dari serta disetujui tenaga klinis yang berwenang.</div>
<?php if (!empty($errors['form'])): ?><div class="notice notice-error"><?= View::e($errors['form']) ?></div><?php endif; ?>

<details class="form-section settings-form" open>
    <summary>Buat nilai rujukan</summary>
    <form method="post" action="/settings/reference-ranges" class="data-form">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <div class="field-grid range-fields">
            <label class="field-wide">Parameter *
                <div class="parameter-autocomplete">
                    <input id="reference-parameter-search" type="text" value="<?= $selectedParameter ? View::e($selectedParameter['label'] . ' · ' . ($selectedParameter['kind'] === 'numeric' ? 'numerik' : 'kualitatif')) : '' ?>" placeholder="Ketik untuk mencari parameter atau klik untuk melihat daftar" autocomplete="off" role="combobox" aria-controls="reference-parameter-suggestions" aria-expanded="false" required>
                    <input id="reference-parameter-code" type="hidden" name="test_code" value="<?= View::e($input['test_code'] ?? '') ?>">
                    <div id="reference-parameter-suggestions" class="parameter-suggestions" role="listbox" aria-label="Daftar parameter" hidden>
                        <?php foreach ($catalogGroups as $panelName => $tests): ?>
                            <div class="parameter-suggestion-group" role="presentation"><?= View::e($panelName) ?></div>
                            <?php foreach ($tests as $code => $test): ?>
                                <button type="button" class="parameter-suggestion" role="option" data-code="<?= View::e($code) ?>" data-label="<?= View::e($test['label']) ?>" data-kind="<?= View::e($test['kind']) ?>" data-unit="<?= View::e($test['unit'] ?? '') ?>">
                                    <strong><?= View::e($test['label']) ?></strong>
                                    <small><?= $test['kind'] === 'numeric' ? 'Numerik' : 'Kualitatif' ?></small>
                                </button>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </label>
            <label>Satuan
                <input name="unit" maxlength="50" value="<?= View::e($input['unit'] ?? '') ?>" placeholder="Contoh: mg/dL">
            </label>
            <label class="field-sex">Jenis kelamin sasaran *
                <select name="sex" required>
                    <option value="ALL" <?= ($input['sex'] ?? 'ALL') === 'ALL' ? 'selected' : '' ?>>Semua jenis kelamin</option>
                    <option value="L" <?= ($input['sex'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= ($input['sex'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </label>
            <label>Usia minimum (tahun)
                <input type="number" name="min_age_years" min="0" max="150" inputmode="numeric" value="<?= View::e($input['min_age_years'] ?? '') ?>" placeholder="Kosong = tanpa batas">
            </label>
            <label>Usia maksimum (tahun)
                <input type="number" name="max_age_years" min="0" max="150" inputmode="numeric" value="<?= View::e($input['max_age_years'] ?? '') ?>" placeholder="Kosong = tanpa batas">
            </label>
            <p class="field-help reference-age-help">Kosongkan keduanya untuk semua usia pada kolom usia.</p>
            <label class="field-wide reference-input" data-reference-kind="numeric" <?= $selectedKind === 'numeric' ? '' : 'hidden' ?>>Nilai rujukan (numerik) *
                <input name="reference_value" inputmode="text" value="<?= View::e($input['reference_value'] ?? '') ?>" placeholder="Contoh: 11,4 - 15,1 atau < 200">
                <span class="field-help">Gunakan rentang angka atau batas tunggal dengan tanda &lt;, &gt;, ≤, atau ≥.</span>
            </label>
            <label class="field-wide reference-input" data-reference-kind="qualitative" <?= $selectedKind === 'qualitative' ? '' : 'hidden' ?>>Nilai normal (kualitatif) *
                <textarea name="normal_values" rows="2" maxlength="1000" placeholder="Contoh: Negatif, Normal, Jernih"><?= View::e($input['normal_values'] ?? '') ?></textarea>
                <span class="field-help">Pisahkan beberapa nilai normal dengan koma atau baris baru.</span>
            </label>
            <label class="field-wide">Sumber / catatan persetujuan klinis *
                <textarea name="reference_note" rows="2" maxlength="255" required placeholder="Contoh: SOP laboratorium ..., revisi ..., ditinjau dr./PJ ..."><?= View::e($input['reference_note'] ?? '') ?></textarea>
                <span class="field-help">Catat dokumen atau pihak klinis yang menjadi dasar sebelum aturan disimpan dan diaktifkan.</span>
            </label>
        </div>
        <div class="form-actions"><button type="submit">Simpan nilai rujukan</button></div>
    </form>
</details>

<script>
(() => {
    const form = document.querySelector('.settings-form form');
    const parameterSearch = document.querySelector('#reference-parameter-search');
    const parameterCode = document.querySelector('#reference-parameter-code');
    const parameterSuggestions = document.querySelector('#reference-parameter-suggestions');
    const unit = document.querySelector('input[name="unit"]');
    let suggestedUnit = '';
    const options = parameterSuggestions ? Array.from(parameterSuggestions.querySelectorAll('.parameter-suggestion')) : [];

    function hideSuggestions() {
        if (!parameterSuggestions) return;
        parameterSuggestions.hidden = true;
        parameterSearch?.setAttribute('aria-expanded', 'false');
    }

    function showSuggestions() {
        if (!parameterSuggestions) return;
        parameterSuggestions.hidden = false;
        parameterSearch?.setAttribute('aria-expanded', 'true');
    }

    function applyParameter(option) {
        if (!option || !parameterSearch || !parameterCode) return;
        parameterSearch.value = option.dataset.label + ' · ' + (option.dataset.kind === 'numeric' ? 'numerik' : 'kualitatif');
        parameterCode.value = option.dataset.code || '';
        parameterSearch.setCustomValidity('');
        const kind = option.dataset.kind || 'numeric';
        document.querySelectorAll('[data-reference-kind]').forEach(function (field) {
            const active = field.dataset.referenceKind === kind;
            field.hidden = !active;
            const input = field.querySelector('input, textarea');
            if (input) input.required = active;
        });
        if (unit && (unit.value === '' || unit.value === suggestedUnit)) {
            suggestedUnit = option.dataset.unit || '';
            unit.value = suggestedUnit;
        }
        hideSuggestions();
    }

    function filterSuggestions() {
        if (!parameterSearch || !parameterCode) return;
        const query = parameterSearch.value.trim().toLowerCase();
        parameterCode.value = '';
        parameterSearch.setCustomValidity('');
        let visible = 0;
        options.forEach(function (option) {
            const matches = query === '' || (option.dataset.label + ' ' + option.dataset.code).toLowerCase().includes(query);
            option.hidden = !matches;
            if (matches) visible++;
        });
        parameterSuggestions?.querySelectorAll('.parameter-suggestion-group').forEach(function (group) {
            let next = group.nextElementSibling;
            let hasVisibleOption = false;
            while (next && next.classList.contains('parameter-suggestion')) {
                if (!next.hidden) hasVisibleOption = true;
                next = next.nextElementSibling;
            }
            group.hidden = !hasVisibleOption;
        });
        if (visible > 0) showSuggestions(); else hideSuggestions();
    }

    options.forEach(function (option) {
        option.addEventListener('click', function () { applyParameter(option); });
    });
    parameterSearch?.addEventListener('focus', showSuggestions);
    parameterSearch?.addEventListener('click', showSuggestions);
    parameterSearch?.addEventListener('input', filterSuggestions);
    parameterSearch?.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hideSuggestions();
    });
    form?.addEventListener('submit', function (event) {
        if (!parameterCode?.value) {
            event.preventDefault();
            parameterSearch?.setCustomValidity('Pilih parameter dari daftar yang tersedia.');
            parameterSearch?.reportValidity();
        }
    });
    document.addEventListener('click', function (event) {
        if (parameterSearch && parameterSuggestions && !parameterSearch.closest('.parameter-autocomplete')?.contains(event.target)) hideSuggestions();
    });

    const initialCode = parameterCode?.value;
    const initialOption = options.find(function (option) { return option.dataset.code === initialCode; });
    if (initialOption) applyParameter(initialOption);
})();
</script>

<section class="form-section">
    <h2>Riwayat versi yang disetujui</h2>
    <?php if ($approvedRanges === []): ?>
        <p class="empty-state">Belum ada nilai rujukan yang disetujui. Hasil akan berstatus “belum dinilai”.</p>
    <?php else: ?>
        <?php
        $historyGroups = [];
        foreach ($approvedRanges as $range) {
            $testCode = (string) $range['test_code'];
            $historyGroups[$testCode] ??= [
                'test_code' => $testCode,
                'display_name' => $range['display_name'],
                'panel_name' => $catalog[$testCode]['panel_name'] ?? ucfirst((string) $range['section']),
                'unit' => $range['unit'],
                'rules' => [],
            ];
            $historyGroups[$testCode]['rules'][(string) $range['sex']][] = [
                'reference' => $referenceText($range),
                'age' => $ageText($range),
                'source' => $range['reference_note'],
                'is_active' => (int) $range['is_active'] === 1,
                'version' => $range['version'],
                'approved_by_name' => $range['approved_by_name'],
                'approved_at' => $range['approved_at'],
            ];
        }
        $historyPanels = [];
        foreach ($historyGroups as $group) {
            $panelName = (string) $group['panel_name'];
            $historyPanels[$panelName][] = $group;
        }
        ?>
        <p class="help-text reference-history-intro">Versi aktif dipakai untuk pemeriksaan baru. Versi arsip tetap disimpan agar hasil lama dan jejak persetujuan dapat ditelusuri.</p>
        <div class="reference-history-accordion">
            <?php foreach ($historyPanels as $panelName => $panelGroups): ?>
                <details class="reference-history-group">
                    <summary><span><?= View::e(mb_strtoupper((string) $panelName)) ?></span><small><?= count($panelGroups) ?> parameter</small></summary>
                    <div class="table-wrap reference-history-wrap">
                        <table class="reference-history-table">
                            <thead><tr><th>Parameter</th><th>Perempuan</th><th>Laki-laki</th><th>Semua jenis kelamin</th><th>Satuan</th></tr></thead>
                            <tbody>
                            <?php foreach ($panelGroups as $group): ?>
                                <tr>
                                    <td data-label="Parameter"><strong><?= View::e($group['display_name']) ?></strong></td>
                                    <?php foreach (['P', 'L', 'ALL'] as $sex): ?>
                                        <td data-label="<?= View::e($sexLabels[$sex]) ?>" class="reference-history-cell">
                                            <?php if (empty($group['rules'][$sex])): ?>
                                                <span class="reference-history-empty">—</span>
                                            <?php else: ?>
                                                <?php foreach ($group['rules'][$sex] as $rule): ?>
                                                    <div class="reference-history-item">
                                                        <strong class="reference-history-value"><?= View::e($rule['reference'] ?: '—') ?></strong>
                                                        <small><?= View::e($rule['age']) ?></small>
                                                        <span class="reference-history-meta"><span class="status <?= $rule['is_active'] ? 'status-completed' : 'status-muted' ?>"><?= $rule['is_active'] ? 'Aktif' : 'Arsip' ?></span> v<?= View::e($rule['version']) ?></span>
                                                        <small><?= View::e($rule['source']) ?></small>
                                                        <small class="reference-history-audit"><?= View::e($rule['approved_by_name']) ?><br><?= View::e($rule['approved_at']) ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td data-label="Satuan" class="reference-history-unit"><?= View::e($group['unit'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
