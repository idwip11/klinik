<section class="page-heading page-heading-inline">
    <div>
        <p class="eyebrow">Registrasi</p>
        <h1>Cari pasien</h1>
        <p>Cari berdasarkan nomor rekam medis, NIK, atau nama.</p>
    </div>
    <div class="button-group">
        <a class="button-link" href="/patients/create">Pasien baru</a>
        <?php if ((Auth::user()['role'] ?? '') === 'admin'): ?><a class="button-link button-secondary" href="/patients/archived">Data lama</a><?php endif; ?>
    </div>
</section>

<form method="get" action="/patients" class="search-form">
    <label class="sr-only" for="patient-search">Cari pasien</label>
    <div class="search-autocomplete">
        <input id="patient-search" name="q" type="search" value="<?= View::e($query ?? '') ?>" placeholder="Nomor RM, NIK, atau nama" maxlength="150" autocomplete="off" autofocus>
        <div id="patient-suggestions" class="patient-suggestions" role="listbox" aria-label="Rekomendasi pasien" hidden></div>
    </div>
    <button type="submit">Cari</button>
</form>

<script>
(function () {
    const form = document.querySelector('.search-form');
    const input = document.querySelector('#patient-search');
    const suggestions = document.querySelector('#patient-suggestions');
    if (!form || !input || !suggestions) return;

    let timer;
    let controller;

    function hideSuggestions() {
        suggestions.hidden = true;
        suggestions.replaceChildren();
    }

    function addSuggestion(patient) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'patient-suggestion';
        button.setAttribute('role', 'option');

        const name = document.createElement('strong');
        name.textContent = patient.full_name || 'Tanpa nama';
        const detail = document.createElement('small');
        detail.textContent = [
            patient.medical_record_number ? 'No. RM ' + patient.medical_record_number : '',
            patient.national_id ? 'NIK ' + patient.national_id : '',
            patient.birth_date ? 'Tgl lahir ' + (function (d) { var p = d.split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d; })(patient.birth_date) : '',
            patient.sex === 'L' ? 'Laki-laki' : patient.sex === 'P' ? 'Perempuan' : '',
            patient.insurance_type || ''
        ].filter(Boolean).join(' · ');
        button.append(name, detail);
        button.addEventListener('click', function () {
            input.value = patient.full_name || '';
            hideSuggestions();
            form.requestSubmit();
        });
        suggestions.append(button);
    }

    async function loadSuggestions() {
        const query = input.value.trim();
        if (query.length < 2) {
            hideSuggestions();
            return;
        }
        if (controller) controller.abort();
        controller = new AbortController();
        try {
            const response = await fetch('/patients/suggestions?q=' + encodeURIComponent(query), {
                headers: { Accept: 'application/json' },
                signal: controller.signal
            });
            if (!response.ok) throw new Error('Suggestion request failed');
            const patients = await response.json();
            if (input.value.trim() !== query) return;
            suggestions.replaceChildren();
            patients.forEach(addSuggestion);
            suggestions.hidden = patients.length === 0;
        } catch (error) {
            if (error.name !== 'AbortError') hideSuggestions();
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(loadSuggestions, 180);
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hideSuggestions();
    });
    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) hideSuggestions();
    });
})();
</script>

<?php if (($searched ?? false) && empty($patients)): ?>
    <p class="empty-state">Tidak ada pasien yang cocok. Periksa kata kunci atau daftarkan pasien baru.</p>
<?php elseif (!empty($patients)): ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>No. RM</th><th>Nama</th><th>NIK</th><th>Tanggal Lahir</th><th>L/P</th><th>Asuransi</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $patient): ?>
                <tr>
                    <td data-label="No. RM"><?= View::e($patient['medical_record_number']) ?></td>
                    <td data-label="Nama"><?= View::e($patient['full_name']) ?></td>
                    <td data-label="NIK"><?= View::e($patient['national_id'] ?: '—') ?></td>
                    <td data-label="Tanggal Lahir"><?= View::e(View::formatDate($patient['birth_date']) ?: '—') ?></td>
                    <td data-label="L/P"><?= View::e($patient['sex'] ?: '—') ?></td>
                    <td data-label="Asuransi"><?= View::e($patient['insurance_type'] ?: '—') ?></td>
                    <td data-label="Aksi">
                        <a href="/patients/<?= View::e($patient['id']) ?>">Lihat data</a>
                        <span aria-hidden="true"> · </span>
                        <a href="/examinations/create?patient=<?= View::e($patient['id']) ?>">Pemeriksaan baru</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
