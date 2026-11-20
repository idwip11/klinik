Paket migrasi produksi 031-039 - revisi 20 Oktober 2026
====================================================

Paket ini menggantikan ZIP 031-038 yang dibagikan sebelumnya. Isinya SQL
skema/konfigurasi dan nilai rujukan saja, tanpa .env, pasien, hasil pemeriksaan,
pengguna, ataupun dump database lokal. Paket kode aplikasi diberikan terpisah.

Sebelum import: pilih database produksi yang benar di phpMyAdmin, pastikan
backup tersedia, dan periksa migrasi terakhir masih 030. Jalankan:

SELECT COUNT(*) AS admin_aktif FROM users WHERE role = 'admin' AND is_active = 1;

Hasil harus minimal 1. Admin aktif dipakai sebagai pencatat nilai rujukan.
Ikuti panduan deployment di percakapan; jangan mulai import sebelum langkah
pembaruan kode aplikasi dan jeda penggunaan aplikasi ditentukan.

Import berkas SATU PER SATU dari 031 sampai 039. Setelah satu file berhasil,
catat migrasinya di tab SQL dengan nama file yang sama persis, contoh:

INSERT INTO schema_migrations (migration, executed_at)
VALUES ('031_widal_result_dropdowns.sql', UTC_TIMESTAMP());

Ulangi proses import dan pencatatan tersebut untuk file berikutnya.
Jika error: berhenti, jangan catat migrasi gagal, jangan import ulang otomatis.
Kirim nama file dan teks error. Jika baru pencatatan yang gagal, SQL migrasi
mungkin sudah berhasil; periksa dulu sebelum mengulang.

Urutan:
031_widal_result_dropdowns.sql
032_widal_o_manual_entry.sql
033_immunoserology_rapid_dropdowns.sql
034_widal_always_included.sql
035_panel_required_sex.sql
036_anc_ibu_hamil.sql
037_urine_negative_reference_ranges.sql
038_examination_doctor_name.sql
039_urine_qualitative_reference_ranges.sql

038 dipertahankan sebagai riwayat skema karena sudah dijalankan pada database
lokal. Kolomnya tidak digunakan oleh kode aplikasi terbaru. Dokter sudah
dihapus dari input, identitas cetak/PDF/Excel, dan riwayat pasien. Jangan DROP
kolom atau tabel untuk revisi tampilan ini.

039 memperbarui rujukan untuk semua jenis kelamin dan usia:
- Urobilinogen: Normal
- Bilirubin: Negatif
- Darah Samar: Negatif
- Keton: Negatif

Satuan mg/dL dihapus untuk empat hasil kualitatif tersebut. Versi rujukan lama
diarsipkan. Hasil pasien dan snapshot rujukan tersimpan tidak diubah.

Verifikasi setelah semua migrasi:

SELECT migration FROM schema_migrations ORDER BY migration DESC LIMIT 9;

SELECT test_code, sex, result_kind, unit, normal_values, version
FROM reference_ranges
WHERE test_code IN ('URINE_PROTEIN', 'URINE_GLUCOSE', 'URINE_UROBILINOGEN',
                    'URINE_BILIRUBIN', 'URINE_OCCULT_BLOOD', 'URINE_KETONE')
  AND is_active = 1 AND approval_status = 'approved'
ORDER BY test_code;

Hasil query rujukan harus enam baris, sex ALL, result_kind qualitative,
unit NULL; Urobilinogen ["Normal"], lima parameter lainnya ["Negatif"].
