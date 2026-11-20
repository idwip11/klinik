# Pembaruan produksi 20 Oktober 2026

Panduan ini mendukung pendampingan langkah per langkah di percakapan. Koneksi
dan pengujian otomatis hanya menggunakan database lokal. Pemasangan di cPanel
dilakukan pengguna melalui File Manager dan phpMyAdmin.

## Perbaikan hapus pemeriksaan — 22 Oktober 2026

Paket sebelumnya membuang route POST `/examinations/{id}/delete` dan metode
`ExaminationRepository::deleteExamination`, tetapi halaman pasien production
masih menampilkan formulir hapus. Akibatnya permintaan sah menerima 404 dan
pemeriksaan tetap tersimpan. Pembuat paket lokal kini mempertahankan route dan
metode tersebut, termasuk otorisasi admin, CSRF, konfirmasi nomor lab, transaksi,
dan audit penghapusan.

`klinik-hotfix-hapus-pemeriksaan-2026-10-22.zip` hanya memuat:

- `app/ExaminationRepository.php`
- `public_html/index.php`

Perbandingan otomatis terhadap gabungan kode production sebelumnya dan hotfix
riwayat memastikan perubahan hanya menambahkan kembali metode dan route hapus.
Konstanta kompatibilitas riwayat dari hotfix 20 Oktober tetap ada.

Pemasangan melalui cPanel:

1. Aktifkan maintenance; simpan backup terbaru database dan dua berkas di atas
   di luar `public_html`.
2. Unggah ZIP hotfix ke `/home/basg2576`, lalu Extract ke `/home/basg2576`
   dengan menimpa dua berkas tersebut.
3. Nonaktifkan maintenance dengan menghapus hanya tiga aturan maintenance
   sebelumnya; pertahankan blok PHP 8.2 dan aturan routing lain.
4. Buka riwayat pasien, hapus hanya pemeriksaan yang memang hendak dihapus,
   lalu pastikan kembali ke riwayat dengan pesan berhasil. Pemeriksaan lainnya
   dan identitas pasien harus tetap tampil.

Tidak ada SQL, migrasi, `.env`, atau data pasien dalam hotfix ini. Tidak perlu
mengulang migrasi 031–039. Paket lengkap `klinik-production-code-2026-10-22.zip`
juga sudah mempertahankan route dan metode hapus; untuk hosting yang sudah
diperbarui sebelumnya, gunakan hotfix kecil di atas.

Pengujian kode lokal dan simulasi production memakai database QA MySQL terpisah
berisi skema dan data sintetis karena database pengembangan port 3307 sedang
tidak aktif. `tests/examination_deletion_regression.php` menjalankan front
controller sebenarnya setelah bootstrap dengan tabel temporer. Enam skenario
lulus: POST sah, nomor lab salah, peran ditolak, CSRF salah, GET, dan ID tidak
ada. Uji juga memeriksa sembilan tabel hasil terkait, audit, identitas pasien,
dan pemeriksaan lain. `scripts/verify_examination_deletion_patch.php`
mereproduksi 404 sebelum patch, lalu menguji hapus, identitas/riwayat/kumulatif,
Rekap/Analisis, tujuh kasus PDF, dan dry-run impor setelah patch. Semuanya lulus.

Uji paket lengkap lama tidak seluruhnya dapat dijalankan di database QA kosong
ini: beberapa tes mengharuskan pasien awal, dan tes migrasi 039 menggunakan
self-join tabel temporer yang ditolak MySQL dengan error 1137. Migrasi tersebut
tidak diubah atau dikirim dalam hotfix. Hasil pengujian patch yang lulus dicatat
di `release-artifacts/production-2026-10-22/deletion-overlay-verification.json`.
Pemasangan serta hasil hapus di hosting masih menunggu konfirmasi pengguna.

## Perbaikan riwayat setelah pemasangan

Log production mengonfirmasi `Undefined constant
ExaminationRepository::DATA_RETENTION_START` pada `PatientRepository.php:169`.
Paket awal menghapus deklarasi konstanta tersebut, sedangkan repository
riwayat/kumulatif dan layanan impor production lama masih memakainya. Pemeriksaan
awal memakai berkas baseline Git untuk kode yang tidak dikirim, sehingga tidak
menangkap ketidakcocokan dengan berkas deployment production September.

- `klinik-hotfix-riwayat-2026-10-20.zip` hanya berisi
  `app/ExaminationRepository.php`. Perubahannya hanya mengembalikan deklarasi
  konstanta kompatibilitas beserta komentar. Ekstrak ke `/home/basg2576` saat
  maintenance aktif, timpa berkas tersebut, lalu uji riwayat dan hasil kumulatif.
- `klinik-production-code-2026-10-20-r2.zip` adalah paket lengkap yang sudah
  memuat perbaikan yang sama. Untuk production yang sudah memasang paket awal,
  cukup pasang ZIP hotfix kecil; migrasi 031–039 tidak perlu dijalankan ulang.
- `scripts/verify_production_overlay.php` merekonstruksi deployment memakai
  `app/` dari `klinik-core.zip` lama dan menimpanya dengan ZIP pembaruan awal.
  Error asli direproduksi sebelum patch. Setelah patch, riwayat/identitas,
  rendering halaman riwayat dan kumulatif ANC, Rekap/Analisis, tujuh kasus PDF,
  serta dry-run impor diuji. Berkas lama lainnya dipertahankan persis.

Pemasangan patch production perlu dikonfirmasi dengan membuka ulang
`/patients/3297`, halaman kumulatif, dan salah satu hasil PDF. Uji lokal yang
lulus tidak menggantikan konfirmasi hasil pemasangan di hosting.

## Paket

- `klinik-production-code-2026-10-20.zip`: kode revisi yang dipilih, aset,
  `composer.json`, `composer.lock`, dan dependensi `vendor`, termasuk Dompdf.
- `production-migrations-031-039.zip`: migrasi katalog, opsi, skema, dan rujukan.
- Kode ZIP diekstrak ke `/home/basg2576`, sehingga jalurnya menjadi `app/`,
  `vendor/`, dan `public_html/`. Ini bukan ZIP untuk diekstrak di `public_html`.

Paket kode tidak memuat `.env`, `storage`, isi database, berkas SQL, konfigurasi
server `.htaccess`/`.user.ini`/`php.ini`, atau pembatasan riwayat 2026 yang baru.
Penanganan hapus pemeriksaan yang sudah ada dipertahankan mulai paket
22 Oktober. Kode pasien yang tidak terkait revisi dibiarkan memakai
versi yang sudah ada di produksi. Untuk pengujian paket, berkas tersebut memakai
baseline repository dan tidak dimasukkan ke ZIP.

## Urutan pendampingan

1. Periksa database aktif, migrasi terakhir, admin aktif, dan dua kolom baru
   dengan query baca saja. Jika sudah ada migrasi di atas 030 atau kolom baru,
   sesuaikan daftar yang belum berjalan sebelum melakukan import.
2. Periksa versi PHP domain pada cPanel: minimal 8.2, dengan `pdo_mysql`, `gd`,
   `dom`, `mbstring`, dan `zip`. Ekspor Excel memakai `ZipArchive`.
3. Unggah ZIP kode ke `/home/basg2576` melalui File Manager tanpa mengekstraknya.
   Periksa isi `.htaccess` produksi untuk menentukan aturan maintenance yang
   sesuai dengan konfigurasi sebenarnya; pertahankan konfigurasi lainnya.
4. Tentukan jeda input petugas. Aktifkan halaman maintenance, verifikasi dari
   browser, lalu buat backup database terbaru serta salinan berkas kode yang
   akan ditimpa. Simpan backup di luar `public_html`.
5. Di phpMyAdmin, import migrasi yang belum ada satu per satu sesuai urutan.
   Setelah satu file berhasil, catat nama persisnya di `schema_migrations`.
   Jika error, hentikan langkah berikutnya dan periksa query terakhir; beberapa
   perintah DDL bisa sudah tersimpan walaupun perintah berikutnya gagal.
6. Saat maintenance masih aktif, ekstrak ZIP kode ke `/home/basg2576`.
   `.env`, `storage`, dan konfigurasi server tetap memakai versi produksi.
   Composer/NPM/Terminal tidak perlu dijalankan di hosting untuk paket ini.
7. Periksa migrasi, rujukan aktif, dan susunan berkas. Pulihkan `.htaccess`
   dengan menghapus hanya aturan maintenance yang baru ditambahkan.
8. Periksa login, menu Nilai rujukan, form pemeriksaan perempuan/laki-laki
   tanpa menyimpan data uji, preview hasil lama, download PDF/Excel, serta
   filter Rekap dan Analisis. Hasil lama tetap memakai snapshot rujukan yang
   tersimpan; evaluasi baru memakai aturan baru.

Jika aplikasi gagal setelah pembaruan kode, aktifkan kembali maintenance dan
pulihkan berkas kode dari backup yang tepat. Jangan melakukan DROP/TRUNCATE,
impor database lokal, atau menjalankan ulang ALTER TABLE tanpa pemeriksaan.
Pemulihan database penuh memerlukan perhatian pada data produksi yang masuk
setelah backup; karena itu pemasangan dilakukan dalam jeda input petugas.

## Pemeriksaan rujukan setelah migrasi

```sql
SELECT test_code, sex, result_kind, unit, normal_values, version
FROM reference_ranges
WHERE test_code IN (
    'URINE_PROTEIN', 'URINE_GLUCOSE', 'URINE_UROBILINOGEN',
    'URINE_BILIRUBIN', 'URINE_OCCULT_BLOOD', 'URINE_KETONE'
)
AND is_active = 1 AND approval_status = 'approved'
ORDER BY test_code;
```

Harus ada enam baris dengan `sex = ALL`, `result_kind = qualitative`,
`unit = NULL`. Urobilinogen `Normal`, lima parameter lainnya `Negatif`.
Rujukan ANC tetap khusus perempuan. Kolom Dokter tidak digunakan pada form,
riwayat, maupun identitas cetak/PDF/Excel; migrasi 038 dipertahankan sebagai
riwayat skema tanpa menghapus data kolom yang pernah tersimpan.
