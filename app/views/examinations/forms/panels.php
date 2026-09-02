<nav class="section-nav" aria-label="Bagian form">
    <?php foreach ($panelForm as $panel): ?><a href="#panel-<?= View::e($panel['code']) ?>"><?= View::e($panel['name']) ?></a><?php endforeach; ?>
</nav>

<?php foreach ($panelForm as $panel): ?>
    <section class="form-section panel-result-section<?= $panel['is_always_included'] ? ' panel-result-always' : '' ?>" id="panel-<?= View::e($panel['code']) ?>">
        <div class="section-heading-row">
            <div>
                <h2><?= View::e($panel['name']) ?></h2>
                <?php if (!empty($panel['description'])): ?><p class="help-text"><?= View::e($panel['description']) ?></p><?php endif; ?>
            </div>
            <?php if ($panel['is_always_included']): ?><span class="status status-completed">Selalu tersedia</span><?php endif; ?>
        </div>
        <?php $currentSubsection = null; $gridOpen = false; ?>
        <?php foreach ($panel['parameters'] as $parameter): ?>
            <?php if ($parameter['subsection'] !== $currentSubsection): ?>
                <?php if ($gridOpen): ?></div><?php endif; ?>
                <?php $currentSubsection = $parameter['subsection']; $gridOpen = true; ?>
                <?php if ($currentSubsection): ?><h3 class="parameter-subsection"><?= View::e($currentSubsection) ?></h3><?php endif; ?>
                <div class="field-grid panel-parameter-grid">
            <?php endif; ?>
            <label>
                <span><?= View::e($parameter['label']) ?><?php if ($parameter['unit']): ?> <small class="parameter-unit"><?= View::e($parameter['unit']) ?></small><?php endif; ?></span>
                <?php if ($parameter['result_type'] === 'select'): ?>
                    <select name="parameter_values[<?= View::e($parameter['code']) ?>]">
                        <option value="">Pilih</option>
                        <?php foreach ($parameter['options'] ?? [] as $option): ?><option value="<?= View::e($option) ?>" <?= (string) ($parameter['value'] ?? '') === (string) $option ? 'selected' : '' ?>><?= View::e($option) ?></option><?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input name="parameter_values[<?= View::e($parameter['code']) ?>]"
                           value="<?= View::e($parameter['result_type'] === 'numeric' ? View::formatNumber($parameter['value'] ?? '') : ($parameter['value'] ?? '')) ?>"
                           <?= $parameter['result_type'] === 'numeric' ? 'type="text" inputmode="decimal" data-number-format' : 'maxlength="500"' ?>
                           autocomplete="off">
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
        <?php if ($gridOpen): ?></div><?php endif; ?>
    </section>
<?php endforeach; ?>
