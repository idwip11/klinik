<section class="page-heading">
    <p class="eyebrow">Data pasien</p>
    <h1>Edit identitas pasien</h1>
    <p>Perbarui alamat, nomor telepon, atau data identitas lainnya. Riwayat pemeriksaan pasien tetap tersimpan.</p>
</section>

<?php if (!empty($errors['form'])): ?><div class="notice notice-error"><?= View::e($errors['form']) ?></div><?php endif; ?>
<form method="post" action="/patients/<?= View::e($patient['id']) ?>/edit" class="data-form">
    <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
    <div class="form-section">
        <h2>Identitas pasien</h2>
        <div class="field-grid">
            <div class="patient-identity-grid">
                <label>Nomor rekam medis *<input name="medical_record_number" value="<?= View::e($input['medical_record_number'] ?? $patient['medical_record_number'] ?? '') ?>" required maxlength="50"></label>
                <label>NIK<input name="national_id" value="<?= View::e($input['national_id'] ?? $patient['national_id'] ?? '') ?>" inputmode="numeric" maxlength="32"></label>
            </div>
            <label class="field-wide">Nama lengkap *<input name="full_name" value="<?= View::e($input['full_name'] ?? $patient['full_name'] ?? '') ?>" required maxlength="150"></label>
            <label>Tempat lahir<input name="birth_place" value="<?= View::e($input['birth_place'] ?? $patient['birth_place'] ?? '') ?>" maxlength="100"></label>
            <label>Tanggal lahir<input name="birth_date" type="date" value="<?= View::e($input['birth_date'] ?? $patient['birth_date'] ?? '') ?>"></label>
            <label>Jenis kelamin
                <?php $selectedSex = $input['sex'] ?? $patient['sex'] ?? ''; ?>
                <select name="sex"><option value="">Pilih</option><option value="L" <?= $selectedSex === 'L' ? 'selected' : '' ?>>Laki-laki</option><option value="P" <?= $selectedSex === 'P' ? 'selected' : '' ?>>Perempuan</option></select>
            </label>
            <label>Nomor telepon<input name="phone" value="<?= View::e($input['phone'] ?? $patient['phone'] ?? '') ?>" inputmode="tel" maxlength="30"></label>
            <label>Pekerjaan<input name="occupation" value="<?= View::e($input['occupation'] ?? $patient['occupation'] ?? '') ?>" maxlength="150"></label>
            <label>Asuransi / penjamin
                <?php $selectedInsurance = $input['insurance_type'] ?? $patient['insurance_type'] ?? ''; ?>
                <select name="insurance_type">
                    <option value="">Belum diisi</option>
                    <?php foreach (PatientRepository::INSURANCE_TYPES as $value => $label): ?>
                        <option value="<?= View::e($value) ?>" <?= $selectedInsurance === $value ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>No. Asuransi<input name="insurance_number" value="<?= View::e($input['insurance_number'] ?? $patient['insurance_number'] ?? '') ?>" maxlength="50" inputmode="numeric"></label>
            <label class="field-wide">Alamat<textarea name="address" rows="3" maxlength="1000"><?= View::e($input['address'] ?? $patient['address'] ?? '') ?></textarea></label>
        </div>
    </div>
    <div class="form-actions"><a href="/patients/<?= View::e($patient['id']) ?>">Batal</a><button type="submit">Simpan perubahan</button></div>
</form>
