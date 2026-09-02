# Fase 0 — Discovery dan Validasi Kebutuhan

Status: **berjalan**  
Pemilik keputusan: klinik / penanggung jawab klinis  
Artefak sumber: `Copy of Salinan dari LAB 2026.xlsx` (disimpan sebagai arsip read-only)

## 1. Hasil pembacaan awal workbook

| Sheet | Peran saat ini | Bukti struktur | Keputusan yang masih dibutuhkan |
| --- | --- | --- | --- |
| `LOGBOOK MCU` | Sumber input MCU utama. | Tabel `A2:BB3607`, 54 kolom. | Menetapkan apakah ini satu-satunya sumber historis yang sah. |
| `DL DLL` | Template hasil darah/MCU umum. Mengambil data menurut nomor urut lalu menandai status. | 74 formula `VLOOKUP`/status. | Menyetujui tampilan cetak dan seluruh rentang referensi. |
| `URINE LENGKAP (UL)` | Template hasil urine lengkap. | 32 formula status. | Menyetujui parameter, nilai pilihan, satuan, dan rentang. |
| `Logbook` | Logbook pemeriksaan narkoba. | Tabel `A1:S42`, 19 kolom. | Menetapkan apakah format ini tetap dipakai dan siapa yang mengisinya. |
| `narkoba` | Template cetak pemeriksaan narkoba. | 14 formula lookup. | Menyetujui format cetak dan hasil yang valid. |
| `DL BAYI` | Template hasil darah bayi. | 31 formula status. | Menyetujui rentang berdasarkan usia/jenis kelamin dan formatnya. |
| `Laporan` | Tabel pelaporan dengan data yang mirip MCU. | Tabel `A2:AX2607`, 50 kolom. | Menentukan apakah duplikat, arsip, atau sumber data terpisah. |
| `Pivot Table 1` | Rekap dari data laporan. | Pivot output hingga 487 baris. | Menentukan laporan operasional yang benar-benar diperlukan. |
| `Sheet2` | Data/format pendukung, fungsi belum dapat dipastikan dari struktur. | Area `A3:N1000`; satu formula tanggal. | Konfirmasi apakah masih digunakan. |

Catatan teknis: beberapa sel pada `DL DLL` saat ini menghasilkan `#N/A`. Aplikasi baru tidak akan mewarisi ketergantungan lookup ini; data akan diambil berdasarkan ID pemeriksaan di database.

## 2. Pemetaan data awal

Ini adalah kamus data awal untuk desain aplikasi dan migrasi. Tipe dan satuan yang berlabel **perlu konfirmasi** belum boleh dipakai sebagai aturan klinis.

### 2.1 Identitas dan administrasi

| Kolom Excel | Tujuan tabel aplikasi | Tipe awal | Catatan |
| --- | --- | --- | --- |
| `NO` | Nomor urut impor lama | integer | Bukan identitas utama aplikasi. |
| `NO RM` | `patients.medical_record_number` | text unik | Harus dipertahankan nol awal bila ada. |
| `No. Lab` | `examinations.lab_number` | text unik | Pola pembuatan perlu dikonfirmasi. |
| `Nama` | `patients.full_name` | text | Wajib. |
| `NIK` | `patients.national_id` | text | Data sensitif; tidak boleh diperlakukan sebagai angka. |
| `ttl` | `patients.birth_place`, `patients.birth_date` | text + date | Perlu pemisahan saat pembersihan data. |
| `L/P` | `patients.sex` | pilihan | Standarkan nilai lama menjadi `L`/`P`. |
| `ALAMAT LENGKAP` / `Alamat` | `patients.address` | text | Perlu konfirmasi apakah wajib. |
| `No. HP` | `patients.phone` | text | Simpan format nomor secara konsisten. |
| `Tanggal Periksa` | `examinations.examined_on` | date | Wajib. |
| `Jam Periksa` | `examinations.examined_at` | time | Digunakan pada narkoba bila masih diperlukan. |
| `status lab` | `examinations.status` | pilihan | Bedakan status kerja aplikasi dari hasil klinis. |
| `kondisi` | `examinations.condition_note` | text/pilihan | Definisi perlu dikonfirmasi. |
| `NO. SURAT` | `examinations.letter_number` | text | Opsional, tetapi harus dapat dicari. |
| `Pekerjaan` | `patients.occupation` | text | Saat ini muncul pada logbook narkoba. |
| `Keperluan` | `examinations.purpose` | text/pilihan | Saat ini muncul pada logbook narkoba. |

### 2.2 Parameter pemeriksaan MCU

| Kelompok | Kolom Excel yang ditemukan | Tabel aplikasi awal | Catatan |
| --- | --- | --- | --- |
| Tanda vital/fisik | `td`, `tb`, `bb`, `RR`, `BUTA WARNA`, `NADI`, `GOLDAR`, `Rhesus`, `suhu` | `vital_results` | Pecah `td` menjadi sistolik/diastolik bila formatnya konsisten. Satuan perlu konfirmasi. |
| Hematologi | `HGB`, `WBC`, `PLT`, `RBC`, `HCT` | `blood_results` | Nilai numerik; satuan dan rentang harus disahkan klinisi. |
| Kimia darah | `GDS`, `CHOL`, `UA`, `SGOT`, `SGPT` | `blood_results` | Nilai numerik; satuan dan rentang harus disahkan klinisi. |
| Urine fisik | `Warna Urine`, `Kejernihan`, `Berat Jenis`, `pH` | `urine_results` | Campuran nilai pilihan dan numerik. |
| Urine kimia | `Protein Urin`, `Glukosa/Reduksi`, `Urobilinogen`, `Bilirubin`, `Darah Samar`, `Keton`, `Nitrit`, `Lekosit` | `urine_results` | Daftar nilai pilihan perlu dikonfirmasi. |
| Urine sedimen | `Eritrosit`, `Lekosit2`, `Squamos`, `Silinder`, `Kristal`, `Bakteri` | `urine_results` | Perlu satuan/cara pelaporan yang disetujui. |
| Widal | `WIDAL O`, `WIDAL AO`, `WIDAL BO`, `WIDAL H` | `widal_results` | Ada di `LOGBOOK MCU`, tidak terlihat pada tabel `Laporan`. |

### 2.3 Pemeriksaan narkoba

| Kolom Excel | Tabel aplikasi awal | Jenis nilai |
| --- | --- | --- |
| `Amphetamine` | `drug_results.amphetamine` | pilihan terkontrol |
| `Marijuana` | `drug_results.marijuana` | pilihan terkontrol |
| `Morphine` | `drug_results.morphine` | pilihan terkontrol |
| `Benzodiazepine` | `drug_results.benzodiazepine` | pilihan terkontrol |
| `Metamphetamine` | `drug_results.metamphetamine` | pilihan terkontrol |
| `Cocaine` | `drug_results.cocaine` | pilihan terkontrol |

Nilai valid (misalnya `Negatif`, `Positif`, atau istilah lain) harus ditetapkan klinik, tidak diasumsikan dari data sumber.

## 3. Rancangan alur yang diajukan untuk disetujui

```text
Registrasi pasien
  -> buat/temukan pasien
  -> buat pemeriksaan + nomor lab
  -> isi hasil menurut jenis pemeriksaan
  -> pemeriksaan kelengkapan
  -> finalisasi oleh petugas berwenang
  -> preview/cetak A4
  -> rekap dari pemeriksaan final
```

Aturan yang diajukan:

1. Satu pasien dapat memiliki banyak pemeriksaan.
2. Satu pemeriksaan memiliki satu nomor lab yang unik dan tidak berubah.
3. Hasil `draft` boleh disunting oleh petugas berwenang.
4. Hasil `final` hanya dapat dikoreksi melalui revisi dengan alasan dan audit log.
5. Cetak ulang tidak mengubah hasil, tetapi dicatat dalam audit log.
6. Laporan hanya mengambil hasil berstatus `final`, kecuali admin memilih sebaliknya.

## 4. Konfigurasi klinis yang wajib disetujui

Sebelum fungsi normal/abnormal dibangun, penanggung jawab klinis perlu memberi atau menyetujui untuk setiap parameter:

- nama parameter dan satuannya;
- metode/pilihan hasil yang diterima;
- batas bawah dan batas atas bila numerik;
- perbedaan menurut jenis kelamin, usia, atau kelompok pasien;
- label yang ditampilkan (`normal`, `abnormal`, tinggi/rendah, atau lainnya);
- kebijakan jika nilai kosong, tidak dikerjakan, atau di luar rentang ukur.

Formula pada Excel hanya menjadi bahan pembanding, **bukan** sumber aturan klinis final.

## 5. Keputusan yang perlu dijawab oleh klinik

Tandai jawaban pada tabel ini saat wawancara kebutuhan.

| ID | Pertanyaan | Jawaban / keputusan | Pemilik |
| --- | --- | --- | --- |
| D-01 | Untuk rilis pertama, apakah semua modul (MCU, urine, narkoba, bayi) harus tersedia atau MCU umum didahulukan? | Belum dijawab | Klinik |
| D-02 | Siapa yang boleh mengisi, memeriksa, memfinalisasi, dan mencetak hasil? | Belum dijawab | Klinik |
| D-03 | Apakah nomor lab dibuat otomatis? Jika ya, tuliskan pola dan kapan nomor di-reset. | Belum dijawab | Klinik |
| D-04 | Apakah satu pasien boleh memiliki lebih dari satu pemeriksaan pada tanggal yang sama? | Belum dijawab | Klinik |
| D-05 | Apakah `Laporan` adalah duplikat `LOGBOOK MCU`, arsip periode tertentu, atau sumber terpisah? | Belum dijawab | Klinik |
| D-06 | Apakah `Sheet2` masih dipakai? Untuk proses apa? | Belum dijawab | Klinik |
| D-07 | Template hasil mana yang wajib sama persis dengan format sekarang (termasuk kop, tanda tangan, cap, dan nomor surat)? | Belum dijawab | Klinik |
| D-08 | Siapa yang menyetujui rentang referensi dan satuan tiap parameter? | Belum dijawab | Penanggung jawab klinis |
| D-09 | Nilai pilihan apa yang sah untuk hasil urine, buta warna, golongan darah, dan narkoba? | Belum dijawab | Penanggung jawab klinis |
| D-10 | Apakah hasil dapat diekspor/dikirim ke pasien atau hanya untuk cetak internal? | Belum dijawab | Klinik |
| D-11 | Berapa pengguna bersamaan dan dari lokasi/jaringan mana saja mereka mengakses aplikasi? | Belum dijawab | Klinik/IT |
| D-12 | Berapa lama data pasien, hasil, dan audit log harus disimpan? | Belum dijawab | Klinik |
| D-13 | Siapa yang menjalankan serta mengecek backup, dan di mana salinan backup disimpan? | Belum dijawab | Klinik/IT |

## 6. Bahan yang perlu dikumpulkan dari klinik

- Tiga sampai lima contoh hasil cetak **yang sudah dianonimkan** untuk setiap jenis pemeriksaan.
- Daftar staf dan peran; jangan kirim password.
- Rentang referensi/satuan yang telah disetujui penanggung jawab klinis.
- Contoh nomor lab dari beberapa tanggal/periode untuk memvalidasi pola.
- Sampel data Excel kecil yang sudah dianonimkan untuk latihan impor.
- Kebijakan penggunaan tanda tangan, cap, dan nomor surat pada hasil.
- Prosedur koreksi hasil dan penanganan hasil yang sudah terlanjur dicetak.

## 7. Kriteria Fase 0 selesai

- Semua keputusan D-01 sampai D-13 memiliki pemilik dan jawaban yang disetujui.
- Kamus data serta daftar nilai pilihan disetujui.
- Template cetak MVP ditetapkan.
- Aturan nomor lab, status kerja, finalisasi, dan koreksi disetujui.
- Sampel data uji yang aman tersedia.
- Ruang lingkup rilis pertama terkunci.

Setelah kriteria ini terpenuhi, pekerjaan dapat lanjut ke Fase 1 tanpa mengasumsikan aturan operasional atau klinis.
