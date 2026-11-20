<?php
$sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
?>
<section class="page-heading page-heading-inline">
    <div>
        <p class="eyebrow">Riwayat pasien</p>
        <h1>Hasil pemeriksaan kumulatif</h1>
        <p>Bandingkan hasil pemeriksaan pasien berdasarkan tanggal pemeriksaan.</p>
    </div>
    <a class="button-link" href="/patients/<?= View::e($patient['id']) ?>">← Kembali ke data pasien</a>
</section>

<section class="form-section patient-detail-card">
    <h2>Pasien</h2>
    <dl class="patient-summary">
        <div><dt>Nama</dt><dd><?= View::e($patient['full_name']) ?></dd></div>
        <div><dt>No. RM</dt><dd><?= View::e($patient['medical_record_number']) ?></dd></div>
        <div><dt>Jenis kelamin</dt><dd><?= View::e($sexLabels[$patient['sex']] ?? ($patient['sex'] ?: '—')) ?></dd></div>
        <div><dt>Tanggal lahir</dt><dd><?= View::e(View::formatDate($patient['birth_date']) ?: '—') ?></dd></div>
    </dl>
</section>

<section class="form-section">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Perbandingan</p>
            <h2>Tabel hasil kumulatif</h2>
        </div>
    </div>
    <form method="get" class="field-grid cumulative-filter-form">
        <label>
            Dari tanggal
            <input type="date" name="from" value="<?= View::e($filters['from']) ?>">
        </label>
        <label>
            Sampai tanggal
            <input type="date" name="to" value="<?= View::e($filters['to']) ?>">
        </label>
        <label>
            Kategori pemeriksaan
            <select name="category">
                <option value="">Semua kategori</option>
                <?php foreach ($cumulativeData['category_options'] as $categoryOption): ?>
                    <option value="<?= View::e($categoryOption['code']) ?>"<?= $filters['category'] === $categoryOption['code'] ? ' selected' : '' ?>><?= View::e($categoryOption['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Jumlah pemeriksaan
            <select name="limit">
                <option value="5"<?= $filters['limit'] === 5 ? ' selected' : '' ?>>5 pemeriksaan terbaru</option>
                <option value="10"<?= $filters['limit'] === 10 ? ' selected' : '' ?>>10 pemeriksaan terbaru</option>
                <option value="all"<?= $filters['limit'] === null ? ' selected' : '' ?>>Semua pemeriksaan</option>
            </select>
        </label>
        <div class="cumulative-filter-actions">
            <button type="submit">Terapkan filter</button>
            <a href="/patients/<?= View::e($patient['id']) ?>/cumulative-results">Reset</a>
        </div>
    </form>
    <?php if (empty($cumulativeData['examinations'])): ?>
        <p class="empty-state">Belum ada pemeriksaan yang dapat dibandingkan untuk pasien ini.</p>
    <?php elseif (empty($cumulativeData['categories'])): ?>
        <p class="empty-state">Pemeriksaan pasien tersedia, tetapi belum ada hasil yang dapat dibandingkan.</p>
    <?php else: ?>
        <p class="help-text">Urutan kolom dimulai dari pemeriksaan terlama ke terbaru. Geser tabel ke samping untuk melihat seluruh tanggal pemeriksaan.</p>
        <div class="table-wrap cumulative-results-table-wrap">
            <table class="cumulative-results-table">
                <thead>
                    <tr>
                        <th scope="col">Parameter</th>
                        <th scope="col">Satuan</th>
                        <th scope="col">Nilai rujukan</th>
                        <?php foreach ($cumulativeData['examinations'] as $index => $examination): ?>
                            <?php $isLatest = $index === array_key_last($cumulativeData['examinations']); ?>
                            <th scope="col" class="cumulative-examination-column<?= $isLatest ? ' cumulative-latest' : '' ?>">
                                <time datetime="<?= View::e($examination['examined_on']) ?>"><?= View::e(View::formatDate($examination['examined_on'])) ?></time>
                                <small>No. Lab <?= View::e($examination['lab_number']) ?></small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cumulativeData['categories'] as $category): ?>
                        <tr class="cumulative-category-row">
                            <th scope="colgroup" colspan="<?= View::e(count($cumulativeData['examinations']) + 3) ?>"><?= View::e($category['name']) ?></th>
                        </tr>
                        <?php foreach ($category['parameters'] as $parameter): ?>
                            <tr>
                                <th scope="row" class="cumulative-parameter"><?= View::e($parameter['label']) ?></th>
                                <td class="cumulative-unit"><?= View::e($parameter['unit'] ?: '—') ?></td>
                                <?php
                                $referenceSummary = $cumulativeData['reference_summaries'][$parameter['code']] ?? ['available_count' => 0, 'entries' => []];
                                $referenceEntries = $referenceSummary['entries'];
                                $referenceAppliesToAll = $referenceSummary['available_count'] === count($cumulativeData['examinations']);
                                ?>
                                <td class="cumulative-reference">
                                    <?php if (!ReferenceEvaluator::requiresReference($parameter['code'])): ?>
                                        <span class="cumulative-empty">—</span>
                                    <?php elseif ($referenceEntries === []): ?>
                                        <span class="cumulative-reference-unavailable">Belum tersedia</span>
                                    <?php else: ?>
                                        <?php foreach ($referenceEntries as $referenceEntry): ?>
                                            <span class="cumulative-reference-value"><?= View::e($referenceEntry['text']) ?></span>
                                            <?php if (count($referenceEntries) > 1 || !$referenceAppliesToAll): ?>
                                                <small class="cumulative-reference-dates">Berlaku: <?= View::e(implode(', ', array_map(static fn (string $date): string => View::formatDate($date), $referenceEntry['examined_on']))) ?></small>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($cumulativeData['examinations'] as $index => $examination): ?>
                                    <?php
                                    $isLatest = $index === array_key_last($cumulativeData['examinations']);
                                    $result = $cumulativeData['results'][$examination['id']][$parameter['code']] ?? null;
                                    $interpretation = $result['interpretation'] ?? null;
                                    $isAbnormal = in_array($interpretation, ['low', 'high', 'abnormal'], true);
                                    ?>
                                    <td class="cumulative-result<?= $isLatest ? ' cumulative-latest' : '' ?><?= $isAbnormal ? ' cumulative-result-abnormal interpretation-' . View::e($interpretation) : '' ?>">
                                        <?php if ($result === null): ?>
                                            <span class="cumulative-empty">—</span>
                                        <?php else: ?>
                                            <span class="cumulative-result-value"><?= View::e($result['numeric'] ? View::formatNumber($result['value']) : $result['value']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
