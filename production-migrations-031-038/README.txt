Paket migrasi produksi 031-038
================================

Paket ini hanya berisi SQL skema/konfigurasi yang sudah ada di proyek.
Tidak berisi database lokal, pasien, hasil pemeriksaan, pengguna, atau .env.

Prasyarat
---------
1. Pastikan backup produksi tersedia dan masih dapat dipulihkan.
2. Di phpMyAdmin, pilih database produksi yang benar.
3. Pastikan schema_migrations berakhir di 030_reorder_hematology_parameters.sql.
4. Jalankan pemeriksaan berikut di tab SQL:

   SELECT COUNT(*) AS active_admin
   FROM users
   WHERE role = 'admin' AND is_active = 1;

   Hasil harus minimal 1. Migrasi 036 dan 037 memakai admin aktif sebagai
   pencatat persetujuan nilai rujukan.

Cara menjalankan
----------------
Untuk setiap berkas SQL bernomor 031 sampai 038, sesuai urutan:

1. Import SATU berkas melalui tab Import phpMyAdmin (jangan pilih dump DB lokal).
2. Pastikan phpMyAdmin menyatakan query berhasil.
3. Baru setelah berhasil, buka tab SQL dan catat migrasinya. Ganti nama berkas
   dengan nama berkas yang baru saja sukses, contoh untuk migrasi 031:

   INSERT INTO schema_migrations (migration, executed_at)
   VALUES ('031_widal_result_dropdowns.sql', UTC_TIMESTAMP());

4. Pastikan baris tersebut muncul pada schema_migrations sebelum lanjut.

Jika ada error, BERHENTI dan jangan catat migrasi itu di schema_migrations.
Kirim teks error beserta nama file migrasi. Jangan import ulang secara otomatis:
beberapa migrasi mengubah skema dan perlu diperiksa dulu untuk mengetahui apakah
sebagian query sudah sempat berjalan.

Jika suatu INSERT pencatatan mengatakan nama migrasi sudah ada, jangan ulang
migrasinya; periksa isi schema_migrations.

Urutan berkas:
031_widal_result_dropdowns.sql
032_widal_o_manual_entry.sql
033_immunoserology_rapid_dropdowns.sql
034_widal_always_included.sql
035_panel_required_sex.sql
036_anc_ibu_hamil.sql
037_urine_negative_reference_ranges.sql
038_examination_doctor_name.sql

Penting
-------
Migrasi hanya memperbarui database produksi. Ini BELUM mengunggah kode aplikasi.
Jangan ekstrak klinik-public.zip lama atau mengimpor database lokal. File kode
produksi harus diperbarui terpisah setelah paket rilis kode yang tepat disiapkan.
