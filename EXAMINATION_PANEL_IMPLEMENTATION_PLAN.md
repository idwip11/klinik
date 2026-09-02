# Rencana Implementasi — Panel/Kategori Pemeriksaan

Status: **Fase 1–5 selesai; sistem Fase 6 selesai dan konfigurasi nilai rujukan langsung aktif setelah disimpan oleh admin pada 30/08/2026.** Katalog telah diterapkan pada database lokal dan terverifikasi berisi 6 panel aktif serta 67 parameter aktif. Form serta cetak A4 hasil baru mengikuti panel yang dipilih, sementara pemeriksaan lama tanpa pilihan panel tetap memakai form dan cetakan lama. Nilai rujukan tetap dikelola dengan rentang umur/jenis kelamin, versioning, dan catatan sumber klinis. Hasil validasi rinci tersedia pada [PANEL_CONFIGURATION_VALIDATION.md](PANEL_CONFIGURATION_VALIDATION.md).

## 1. Tujuan

Mengurangi form yang terlalu panjang dengan menampilkan parameter berdasarkan panel pemeriksaan yang dipilih. Hasil cetak A4 mengikuti panel yang dipilih, sehingga petugas tidak perlu melewati kolom yang tidak dikerjakan.

## 2. Keputusan rancangan

1. Data pasien/rekam medis hanya menyimpan identitas pasien. Pasien **tidak** memiliki kategori pemeriksaan permanen.
2. Panel dipilih setelah menekan **Pemeriksaan baru**, bersamaan dengan nomor lab dan tanggal pemeriksaan.
3. Satu nomor lab dapat memuat **lebih dari satu panel** bila dikerjakan pada kunjungan yang sama, misalnya DL + Urine Lengkap + Kimia Klinik.
4. Hanya panel terpilih yang ditampilkan pada form input dan cetakan A4.
5. Panel **Imunoserologi** adalah pengecualian: tampil pada semua kondisi/panel, baik saat input maupun cetak, sesuai arahan klinik.
6. Nilai rujukan tetap dikelola per parameter dan dapat berbeda berdasarkan jenis kelamin/usia. Nilai pada screenshot hanya dicatat sebagai referensi awal; aktivasi di aplikasi tetap memerlukan persetujuan penanggung jawab klinis.

## 3. Panel awal dan parameter

| Kode panel | Nama panel | Parameter awal |
| --- | --- | --- |
| `hematology_complete` | DL / Darah Lengkap / Hematologi | Hemoglobin, Leukosit, Trombosit, Eritrosit, Hematokrit, LYMPH%, MID%, GRAN%, MCV, MCH, MCHC, Eosinofil, Basofil, Neutrofil, Limfosit, Monosit. |
| `urinalysis_complete` | Urine Lengkap / Urinalisis | Warna, kejernihan, berat jenis, pH, protein, glukosa/reduksi, urobilinogen, bilirubin, darah samar, keton, nitrit, leukosit, eritrosit, epitel/squamos, silinder, kristal, bakteri. |
| `clinical_chemistry` | Kimia Klinik | Gula darah sewaktu, gula darah puasa, gula darah 2 jam PP, asam urat, kolesterol, trigliserida, HDL, LDL, HbA1c, ureum, kreatinin, SGOT, SGPT. |
| `widal` | Widal / Terkait Demam Tifoid | *Salmonella typhi* O, AO, BO, H. |
| `immunoserology` | Imunoserologi | Golongan darah, rhesus, HBsAg Eclia, HBsAg Rapid, Anti-HIV Rapid, Sifilis Rapid, DHF Dengue IgG, DHF Dengue IgM, NS1 Dengue, *Salmonella Typhi* IgM, tes kehamilan. Ditampilkan di seluruh kondisi. |
| `additional_rare` | Pemeriksaan tambahan/jarang digunakan | Malaria Rapid, PAN (*P. vivax*, *P. ovale*, MAL), PF (*P. falciparum*), preparat BTA, preparat malaria, serta parameter tambahan yang nantinya disetujui klinik. |

Catatan: gambar 3 dan 4 digabung sebagai satu panel **Kimia Klinik**. Pengelompokan subbagian pada layar boleh dipisah agar rapi, tetapi tetap merupakan satu pilihan panel.

## 4. Alur kerja yang diusulkan

```text
Cari/buat pasien
    -> Pemeriksaan baru
    -> Isi nomor lab, tanggal, dan pilih satu atau beberapa panel
    -> Form menampilkan hanya parameter panel terpilih
       + Imunoserologi selalu ditampilkan
    -> Simpan hasil
    -> Halaman pasien menampilkan riwayat per nomor lab
    -> Cetak A4 hanya memuat panel terpilih + Imunoserologi
```

## 5. Rancangan tampilan

### Saat membuat pemeriksaan

- Gunakan daftar checkbox panel, bukan dropdown tunggal, supaya DL dan Urine dapat dicatat dalam satu nomor lab bila diperlukan.
- `DL / Darah Lengkap`, `Urine Lengkap`, `Kimia Klinik`, dan `Widal` tampil sebagai pilihan utama.
- `Pemeriksaan tambahan` diletakkan pada bagian terpisah/opsional agar tidak membebani alur harian.
- Imunoserologi diberi keterangan bahwa panel ini tersedia untuk semua pilihan pemeriksaan.

### Saat input hasil

- Tampilkan kartu/bagian per panel yang dipilih.
- Setiap bagian memiliki judul, satuan, dan nilai rujukan yang relevan.
- Parameter yang kosong tidak menghalangi penyimpanan, kecuali nanti klinik menetapkan parameter wajib untuk panel tertentu.

### Saat cetak A4

- Header dan identitas pasien selalu dicetak.
- Cetak bagian hanya untuk panel yang dipilih pada nomor lab tersebut.
- Imunoserologi selalu dicetak pada semua jenis pemeriksaan.
- Jika konten melebihi satu halaman, browser membagi halaman A4 secara alami tanpa memotong baris tabel; pengujian cetak diperlukan untuk tiap kombinasi panel.

## 6. Rancangan data yang direkomendasikan

Untuk menghindari penambahan kolom database setiap kali ada parameter baru, gunakan data berbasis katalog:

| Entitas | Fungsi |
| --- | --- |
| `examination_panels` | Master daftar panel dan status aktifnya. |
| `test_parameters` | Kode parameter, label, jenis nilai (angka/teks/pilihan), satuan, urutan tampilan, dan panel asal. |
| `examination_panel_selections` | Panel yang dipilih pada satu nomor lab. |
| `examination_parameter_results` | Nilai hasil per parameter untuk satu nomor lab. |
| `reference_ranges` | Tetap menjadi sumber rujukan per parameter, jenis kelamin, usia, dan versi persetujuan. |

Tabel hasil lama yang sudah ada tetap dipertahankan untuk kompatibilitas data impor. Migrasi ke struktur panel dilakukan bertahap, tanpa menghapus hasil historis.

## 7. Tahapan implementasi

1. **Selesai —** validasi bersama klinik: nama panel, parameter wajib, parameter opsional, satuan, dan format cetak. Lihat [PANEL_CONFIGURATION_VALIDATION.md](PANEL_CONFIGURATION_VALIDATION.md).
2. **Selesai —** buat katalog panel dan parameter tanpa memasukkan nilai rujukan klinis yang belum disetujui. Implementasi berada pada migrasi `012_examination_panel_catalog.sql` dan dapat diverifikasi dengan `php scripts/verify_panel_catalog.php`.
3. **Selesai —** pemilihan beberapa panel di halaman **Pemeriksaan baru**. Pilihan disimpan per nomor lab melalui `examination_panel_selections`; Imunoserologi laditambahkan otomatis.
4. **Selesai —** form input ditentukan oleh panel yang dipilih; Imunoserologi selalu tersedia. Hasil disimpan pada `examination_parameter_results` dan parameter yang memiliki kolom lama tetap disinkronkan untuk kompatibilitas rekap/riwayat saat ini.
5. **Selesai —** template cetak A4 berbasis panel tersedia melalui **Preview cetak A4** pada pemeriksaan yang memakai panel. Template memakai header/footer klinik yang sudah ada, hanya mencetak panel terpilih ditambah Imunoserologi, menampilkan parameter kosong sebagai `—`, serta mencatat aksi cetak sebagai `panel_results`.
6. **Implementasi sistem selesai; konfigurasi langsung aktif —** seluruh parameter panel tersedia pada katalog nilai rujukan. Admin menyimpan aturan baru beserta sumber/catatan persetujuan klinis; aturan tersebut langsung aktif untuk pemeriksaan baru. Sistem memvalidasi jenis kelamin, batas umur inklusif, overlap rentang usia, versi pengganti, arsip versi lama, dan snapshot hasil. Sebelas aturan aktif warisan telah ditandai dengan konfirmasi persetujuan klinik pada 30/08/2026. Tidak ada angka klinis baru yang diputuskan oleh AI.
7. **Uji teknis selesai; UAT klinik berjalan —** skenario penerimaan panel ditambahkan pada [UAT_SCENARIOS.md](UAT_SCENARIOS.md). Uji sintetis otomatis mencakup kombinasi panel, Imunoserologi otomatis, pembatasan parameter, penyimpanan/evaluasi hasil, dan render cetak A4 tanpa menyisakan data. Uji penerimaan manual tetap wajib dilakukan petugas dan penanggung jawab klinis menggunakan data sintetis atau data yang telah diizinkan, termasuk persetujuan angka/satuan dan uji cetak fisik sebelum go-live.

## 8. Keputusan klinik yang sudah dikonfirmasi

1. Satu nomor lab boleh memuat beberapa panel. Nomor RM tetap merupakan identitas unik pasien, sedangkan nomor lab mengidentifikasi satu pemeriksaan/kunjungan.
2. Semua parameter Imunoserologi selalu muncul dan selalu tercetak, termasuk bila nilainya belum diisi.
3. Panel tambahan/jarang digunakan ikut dibangun pada implementasi awal.
4. Tidak ada parameter yang diwajibkan sebelum simpan atau cetak. Hasil boleh tersimpan sebagian; nilai yang kosong ditampilkan sebagai `—` agar alur kerja tidak terhambat.
5. Pengguna dengan peran **admin** berwenang menyimpan dan mengaktifkan nilai rujukan setelah memastikan satuan serta nilai tersebut telah disetujui pihak klinik. Catatan sumber/persetujuan klinis wajib diisi pada saat simpan.

## 9. Catatan tindak lanjut Fase 6–7

- Sebelas aturan lama yang masih aktif telah dikonfirmasi pihak klinik pada 30/08/2026 dan diberi catatan persetujuan melalui migrasi `018_confirm_legacy_reference_range_approval.sql`.
- Rentang usia baru dikelola dalam tahun berdasarkan usia ulang tahun penuh pada tanggal pemeriksaan. Rentang hari historis tetap dibaca untuk menjaga hasil lama; rincian implementasi tersedia pada [REFERENCE_RANGE_AGE_YEARS_IMPLEMENTATION_PLAN.md](REFERENCE_RANGE_AGE_YEARS_IMPLEMENTATION_PLAN.md).
- Fase 7 belum boleh dinyatakan lulus go-live sebelum skenario UAT-25 sampai UAT-34 ditandatangani petugas klinik, penanggung jawab klinis, dan penanggung jawab teknis.
