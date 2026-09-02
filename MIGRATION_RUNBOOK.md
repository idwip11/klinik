# Runbook Migrasi Data Excel

Dokumen ini mengatur migrasi data historis dari workbook laboratorium ke aplikasi. Workbook asli adalah arsip read-only: jangan mengubah, menimpa, atau menghapusnya selama proses migrasi.

## 1. Batas migrasi saat ini

| Sumber | Jenis impor | Status |
| --- | --- | --- |
| `LOGBOOK MCU` | `mcu_general` | Didukung melalui CSV UTF-8. |
| `Logbook` narkoba | `drug_screen` | Didukung melalui CSV UTF-8. |
| `Laporan` | Belum diimpor | Tunggu keputusan apakah data unik atau duplikat. |
| `DL DLL`, `URINE LENGKAP`, `narkoba`, `DL BAYI` | Tidak diimpor langsung | Dipahami sebagai template cetak, bukan sumber utama. |
| `Pivot Table 1`, `Sheet2` | Tidak diimpor | Rekap/tabel bantu, bukan sumber transaksi. |

Impor XLSX langsung sengaja tidak disediakan di shared hosting. Setiap sheet sumber diekspor menjadi CSV UTF-8 agar pemetaan mudah diperiksa, ringan, dan tidak bergantung pada Node/Python.

## 2. Aturan keselamatan data

- Kerjakan salinan operasional; simpan workbook asli di lokasi read-only dengan akses terbatas.
- Jangan memasukkan workbook, CSV pasien, backup database, atau screenshot pasien ke Git/repository.
- Pindahkan CSV hanya melalui media/lokasi yang disetujui klinik dan hapus salinan kerja sesuai kebijakan retensi setelah rekonsiliasi.
- Jangan menyimpulkan atau memperbaiki nilai klinis secara otomatis. Nilai yang tidak valid masuk daftar koreksi.
- Semua hasil impor masuk sebagai `draft` dan harus ditinjau sebelum finalisasi.
- Log masalah hanya menyimpan nomor baris, nama field, kode masalah, dan fingerprint; isi baris pasien tidak disalin ke log.

## 3. Persiapan

1. Tetapkan penanggung jawab migrasi, penanggung jawab klinis, dan supervisor yang memberi persetujuan.
2. Bekukan rentang tanggal data yang akan dimigrasikan dan catat waktu cut-off.
3. Simpan checksum SHA-256 workbook asli dan lokasi arsipnya pada berita acara internal.
4. Tentukan keputusan untuk data `Laporan`, format TTL yang tidak konsisten, duplikasi RM/NIK/nomor lab, dan nilai klinis yang tidak dapat dibaca.
5. Buat backup database produksi dan lakukan satu uji restore ke database uji.
6. Jalankan migrasi skema terbaru: `php scripts/migrate.php`.
7. Jalankan `php scripts/preflight.php`; semua `FAIL` harus diselesaikan sebelum impor aktual.

## 4. Ekspor sumber

Untuk setiap sheet sumber:

1. Buka salinan workbook, bukan arsip asli.
2. Pastikan baris header tunggal tetap ada dan identifier seperti nomor RM, nomor lab, NIK, serta telepon tidak diubah menjadi angka.
3. Ekspor hanya sheet tersebut sebagai **CSV UTF-8**. Delimiter koma dan titik koma sama-sama dideteksi.
4. Jangan mengubah nama header setelah ekspor. Empat header minimal adalah nomor RM, nomor lab, nama, dan tanggal periksa.
5. Catat nama file, sheet, jumlah baris data, dan checksum yang ditampilkan alat impor.

## 5. Dry-run wajib

MCU:

```bash
php scripts/import_legacy_csv.php \
  --file=/lokasi-aman/logbook-mcu.csv \
  --type=mcu \
  --source-sheet="LOGBOOK MCU" \
  --dry-run
```

Narkoba:

```bash
php scripts/import_legacy_csv.php \
  --file=/lokasi-aman/logbook-narkoba.csv \
  --type=drug \
  --source-sheet="Logbook" \
  --dry-run
```

Dry-run tidak menulis database. Exit code `0` berarti tidak ada baris gagal; exit code `2` berarti ada baris yang perlu ditinjau. Peringatan TTL berarti nilai tidak dapat dipisahkan dengan pasti: teks tetap dipetakan sebagai tempat lahir dan tanggal lahir perlu ditinjau.

Lakukan koreksi pada salinan kerja berdasarkan nomor baris. Ulangi dry-run sampai kegagalan sudah nol atau setiap pengecualian telah didokumentasikan dan disetujui.

## 6. Impor aktual

1. Aktifkan mode pemeliharaan operasional: jangan menginput transaksi baru selama cutover.
2. Pastikan user ID yang digunakan adalah supervisor/admin aktif.
3. Jalankan perintah yang sama tanpa `--dry-run`, lalu tambahkan `--user-id`:

```bash
php scripts/import_legacy_csv.php \
  --file=/lokasi-aman/logbook-mcu.csv \
  --type=mcu \
  --source-sheet="LOGBOOK MCU" \
  --user-id=1
```

Setiap baris menggunakan transaksi tersendiri. Menjalankan ulang file yang sama tidak menggandakan pemeriksaan: sumber yang sudah ada atau nomor lab yang sudah terpakai akan dihitung sebagai dilewati.

## 7. Rekonsiliasi

Jalankan verifikasi menggunakan batch ID hasil impor:

```bash
php scripts/verify_import.php --batch-id=1 --file=/lokasi-aman/logbook-mcu.csv
```

Kemudian lakukan pemeriksaan manual bersama klinik:

- Jumlah baris sumber = berhasil + dilewati + gagal.
- Setiap kegagalan memiliki keputusan koreksi atau pengecualian tertulis.
- Sampel yang disepakati klinik mencakup data awal/tengah/akhir, pasien dengan pemeriksaan berulang, nilai kosong, hasil kualitatif, dan nilai di luar rentang.
- Identitas, nomor lab, tanggal, hasil darah/urine/narkoba, dan hasil cetak cocok dengan sumber.
- Semua data hasil impor tetap `draft` hingga peninjauan selesai.

Penanggung jawab migrasi dan supervisor klinik menandatangani hasil rekonsiliasi. Jangan memakai data migrasi untuk layanan sebelum persetujuan ini.

## 8. Rollback

Tidak disediakan perintah penghapusan massal karena berisiko menghapus pasien yang sudah terhubung dengan transaksi baru. Bila cutover gagal:

1. Hentikan input aplikasi dan catat waktu kegagalan.
2. Simpan log/batch impor untuk investigasi.
3. Pulihkan seluruh database dari backup pra-impor ke lingkungan yang sudah diverifikasi.
4. Jalankan `scripts/preflight.php` dan pemeriksaan jumlah data setelah restore.
5. Buka kembali layanan hanya setelah supervisor menyetujui hasil restore.

## 9. Bukti yang harus disimpan

- Checksum workbook arsip dan setiap CSV sumber.
- Output dry-run final dan batch ID impor aktual.
- Output `verify_import.php`.
- Catatan koreksi/pengecualian tanpa menyebarkan data pasien di kanal yang tidak aman.
- Bukti backup/restore, hasil UAT, checklist go-live, dan nama pemberi persetujuan.
