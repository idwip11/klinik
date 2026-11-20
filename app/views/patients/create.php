<section class="page-heading">
    <p class="eyebrow">Registrasi</p>
    <h1>Daftarkan pasien baru</h1>
    <p>Isi nomor rekam medis secara manual. Nomor yang sudah terdaftar tidak dapat digunakan kembali.</p>
</section>

<?php $birthDateInput = (string) ($input['birth_date'] ?? ''); ?>

<?php if (!empty($errors['form'])): ?><div class="notice notice-error"><?= View::e($errors['form']) ?></div><?php endif; ?>
<form method="post" action="/patients" class="data-form">
    <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
    <div class="form-section">
        <h2>Identitas pasien</h2>
        <div class="field-grid">
            <div class="patient-identity-grid">
                <label>Nomor rekam medis *<input name="medical_record_number" value="<?= View::e($input['medical_record_number'] ?? '') ?>" required maxlength="50" autocomplete="off" aria-describedby="medical-record-number-help"><small id="medical-record-number-help">Ketik nomor RM sesuai administrasi klinik. Nomor yang sudah digunakan akan ditolak saat disimpan.</small></label>
                <label>NIK<input name="national_id" value="<?= View::e($input['national_id'] ?? '') ?>" inputmode="numeric" maxlength="32"></label>
            </div>
            <label class="field-wide">Nama lengkap *<input name="full_name" value="<?= View::e($input['full_name'] ?? '') ?>" required maxlength="150"></label>
            <label>Tempat lahir<input name="birth_place" value="<?= View::e($input['birth_place'] ?? '') ?>" maxlength="100"></label>
            <label>Tanggal lahir
                <span class="date-entry" data-date-paste>
                    <input data-date-input name="birth_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" value="<?= View::e(View::formatDateForInput($birthDateInput)) ?>" autocomplete="bday" aria-describedby="birth-date-help">
                    <input data-date-picker type="date" value="" aria-label="Pilih tanggal lahir">
                </span>
                <small id="birth-date-help">Bisa ketik atau paste dari aplikasi lain, contoh: 30-12-2022.</small>
            </label>
            <label>Jenis kelamin
                <select name="sex"><option value="">Pilih</option><option value="L" <?= ($input['sex'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option><option value="P" <?= ($input['sex'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option></select>
            </label>
            <label>Nomor telepon<input name="phone" value="<?= View::e($input['phone'] ?? '') ?>" inputmode="tel" maxlength="30"></label>
            <label>Pekerjaan<input name="occupation" value="<?= View::e($input['occupation'] ?? '') ?>" maxlength="150"></label>
            <label>Asuransi / penjamin
                <select name="insurance_type" required>
                    <option value="">Pilih</option>
                    <?php foreach (PatientRepository::INSURANCE_TYPES as $value => $label): ?>
                        <option value="<?= View::e($value) ?>" <?= ($input['insurance_type'] ?? '') === $value ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>No. Asuransi<input name="insurance_number" value="<?= View::e($input['insurance_number'] ?? '') ?>" maxlength="50" inputmode="numeric"></label>
            <label class="field-wide">Alamat<textarea name="address" rows="3" maxlength="1000"><?= View::e($input['address'] ?? '') ?></textarea></label>
        </div>
    </div>
    <div class="form-actions"><a href="/patients">Batal</a><button type="submit">Simpan dan buat pemeriksaan</button></div>
</form>
