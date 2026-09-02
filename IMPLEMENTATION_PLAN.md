# Rencana Implementasi — Aplikasi Operasional Laboratorium Klinik

Status pelaksanaan: **Implementasi teknis Fase 1–5 telah disiapkan menggunakan asumsi sementara; validasi Fase 0 dan pelaksanaan operasional Fase 5 masih menunggu pihak klinik.** Detail discovery dan daftar keputusan ada di [PHASE_0_DISCOVERY.md](PHASE_0_DISCOVERY.md). Rancangan pemilihan panel pemeriksaan tercatat pada [EXAMINATION_PANEL_IMPLEMENTATION_PLAN.md](EXAMINATION_PANEL_IMPLEMENTATION_PLAN.md). Runbook migrasi, UAT, dan cutover tersedia pada [MIGRATION_RUNBOOK.md](MIGRATION_RUNBOOK.md), [UAT_SCENARIOS.md](UAT_SCENARIOS.md), dan [GO_LIVE_CHECKLIST.md](GO_LIVE_CHECKLIST.md).

## 1. Tujuan

Mengganti alur Excel untuk input, pencarian, penilaian status, cetak, dan rekap hasil laboratorium dengan aplikasi web internal yang sederhana, mudah digunakan, dan dapat berjalan pada shared hosting Rumahweb paket Small.

Aplikasi **bukan** alat penetapan diagnosis. Penanda normal/abnormal hanya mengikuti rentang referensi yang disetujui penanggung jawab klinis.

## 2. Kondisi awal yang akan dimigrasikan

Workbook sumber memuat alur berikut:

1. `LOGBOOK MCU` sebagai data utama pemeriksaan MCU (54 kolom).
2. Template cetak hasil darah/umum (`DL DLL`), urine lengkap, narkoba, dan bayi.
3. `Logbook` khusus pemeriksaan narkoba.
4. `Laporan` dan `Pivot Table 1` untuk pelaporan/rekap.

Masalah utama saat ini adalah input sangat lebar, proses cetak bergantung pada pilihan nomor urut dan formula `VLOOKUP`, data berpotensi tersalin pada lebih dari satu sheet, serta terdapat beberapa hasil lookup yang sudah `#N/A`.

## 3. Ruang lingkup MVP

### Termasuk

- Login, logout, dan pengelolaan pengguna.
- Pencarian pasien berdasarkan nomor rekam medis, nomor lab, NIK, atau nama.
- Pendaftaran pasien dan pembuatan pemeriksaan baru.
- Penyuntingan identitas pasien langsung dari halaman detail pasien, termasuk alamat dan nomor telepon.
- Form hasil pemeriksaan yang dibagi per bagian agar tidak perlu menggeser puluhan kolom.
- Pemeriksaan MCU umum, urine lengkap, narkoba, dan bayi.
- Perhitungan status normal/abnormal berdasarkan rentang referensi terkonfigurasi.
- Penyimpanan hasil langsung sebagai data tersimpan; istilah status internal lama dipertahankan hanya untuk kompatibilitas data.
- Halaman cetak A4 per jenis hasil dan fitur cetak/PDF dari browser.
- Rekap dan filter berdasarkan tanggal, jenis pemeriksaan, serta status.
- Pagination server-side pada daftar rekap (15 pemeriksaan per halaman) agar tetap nyaman digunakan saat data berjumlah ribuan.
- Ekspor rekap ke CSV/Excel-kompatibel.
- Riwayat perubahan penting dan backup database.

### Belum termasuk pada MVP

- Integrasi alat laboratorium/LIS, BPJS, SATUSEHAT, WhatsApp, atau SMS.
- Pembayaran, antrean online, dan rekam medis klinis penuh.
- OCR dokumen atau penyimpanan scan dalam jumlah besar.
- Aplikasi mobile native.

## 4. Pengguna dan akses

| Peran | Akses utama |
| --- | --- |
| Perawat | Daftar/cari pasien, membuat nomor lab, mengisi hasil, dan mencetak sesuai kewenangannya. |
| Admin | Seluruh akses perawat, finalisasi, cetak ulang, pengelolaan pengguna, rentang referensi, serta rekap. |

Hak akses detail harus dikonfirmasi bersama klinik sebelum pembangunan. Data yang sudah difinalisasi tidak boleh diubah langsung; koreksi harus menyimpan siapa, kapan, alasan, serta nilai sebelum/sesudah.

## 5. Rekomendasi teknologi

### Stack

- PHP 8.2+ dengan pola MVC ringan dan tanpa framework berat.
- MariaDB melalui PDO dan prepared statements.
- HTML semantik, CSS sederhana responsif, dan JavaScript vanilla.
- CSS khusus cetak ukuran A4; cetak menggunakan browser agar tidak perlu layanan PDF terpisah.
- CSV untuk ekspor MVP; XLSX dapat ditambahkan bila benar-benar dibutuhkan.

### Batasan hosting

Desain harus hemat resource: halaman server-rendered, pagination, indeks database, unggahan dibatasi, dan tidak bergantung pada proses Node/Python/Redis. Paket Small cukup untuk pemakaian internal awal dengan beban input dan cetak yang wajar, tetapi bukan alasan untuk mengabaikan backup mandiri dan pembatasan akses.

## 6. Arsitektur aplikasi

```text
Browser staf
  -> PHP/HTML aplikasi internal
      -> MariaDB (data pasien, pemeriksaan, hasil, konfigurasi, audit)
      -> halaman cetak A4
      -> ekspor CSV
```

Prinsip rancangan:

- Satu sumber data: tidak ada salinan `LOGBOOK MCU` dan `Laporan` yang harus diinput terpisah.
- Nomor rekam medis, nomor lab, dan NIK diperlakukan sebagai identifier, bukan angka untuk perhitungan.
- Setiap hasil menyimpan satuan, nilai referensi, status, serta sumber/versi aturan yang dipakai saat finalisasi.
- Laporan selalu dibangun dari database, bukan dari salinan data manual.

## 7. Rancangan data awal

| Entitas | Isi utama |
| --- | --- |
| `users` | Nama, username, hash password, peran, status aktif. |
| `patients` | Nomor RM, NIK, nama, tanggal/tempat lahir, jenis kelamin, alamat, telepon. |
| `examinations` | Nomor lab, pasien, tanggal/jam, jenis pemeriksaan, status, kondisi, nomor surat, petugas. |
| `vital_results` | Tekanan darah, tinggi, berat, RR, nadi, suhu, buta warna, golongan/rhesus. |
| `blood_results` | HGB, WBC, PLT, RBC, HCT, GDS, ureum, kreatinin, kolesterol, asam urat, SGOT, SGPT. |
| `urine_results` | Parameter fisik, kimia, dan sedimen urine. |
| `widal_results` | Widal O/AO/BO/H bila pemeriksaan digunakan. |
| `drug_results` | Amphetamine, marijuana, morphine, benzodiazepine, methamphetamine, cocaine. |
| `reference_ranges` | Kode tes, satuan, batas bawah/atas, kondisi jenis kelamin/usia, versi/masa berlaku. |
| `audit_logs` | Pengguna, waktu, aksi, entitas, nilai sebelum/sesudah, alasan koreksi. |
| `import_logs` | Berkas sumber, waktu impor, jumlah berhasil/gagal, dan detail kesalahan. |

Struktur akhir disesuaikan setelah pemetaan lengkap seluruh kolom dan format cetak. Tidak semua hasil perlu dipaksa menjadi angka: hasil kualitatif seperti `negatif`, `jernih`, atau `kuning` harus disimpan sebagai nilai terkontrol.

## 8. Alur kerja target

1. Registrasi mencari pasien lama atau membuat pasien baru.
2. Sistem membuat pemeriksaan dan nomor lab yang unik.
3. Petugas memilih paket/jenis pemeriksaan, lalu mengisi bagian hasil yang relevan.
4. Sistem memvalidasi format, nilai wajib, dan menampilkan status referensi.
5. Petugas menekan **Simpan hasil**; data langsung tersimpan dan kembali ke halaman pasien.
6. Hasil tersimpan dapat dibuka dan dicetak sebagai dokumen A4; aktivitas cetak tetap tercatat.
7. Jika ada koreksi, sistem membuat jejak audit tanpa menghapus data pemeriksaan sebelumnya.
8. Rekap ditarik langsung dari data pemeriksaan berdasarkan filter.

## 9. Strategi migrasi Excel

1. Simpan workbook asli sebagai arsip read-only dan jangan jadikan sumber operasional setelah go-live.
2. Buat kamus data: setiap kolom Excel dipetakan ke tabel, kolom, tipe data, satuan, dan aturan validasi aplikasi.
3. Bersihkan data sebelum impor: format tanggal, nomor identitas, variasi jenis kelamin, nilai kosong, serta duplikasi nomor lab/RM/NIK.
4. Impor bertahap ke database staging; hasil yang gagal masuk dicatat di `import_logs`.
5. Rekonsiliasi jumlah baris dan sampel hasil dengan Excel sebelum data dipindah ke produksi.
6. Tentukan dengan klinik apakah `Laporan` adalah data historis/duplikat atau sumber terpisah sebelum diimpor.

Tidak ada rentang nilai klinis yang boleh disalin otomatis dari formula Excel tanpa tinjauan dan persetujuan tenaga kesehatan yang berwenang.

## 10. Keamanan dan keberlangsungan data

- HTTPS wajib; nonaktifkan akses HTTP bila domain sudah aktif.
- Password memakai `password_hash()`; tidak ada password yang disimpan sebagai teks biasa.
- Prepared statements, validasi input server-side, CSRF token, session aman, dan pembatasan percobaan login.
- Prinsip akses minimum: setiap peran hanya melihat dan mengubah data yang diperlukan.
- Audit log untuk finalisasi, koreksi, cetak ulang, ekspor, serta pengelolaan akun.
- Backup database otomatis harian ke lokasi terpisah dari hosting, dengan uji pemulihan berkala.
- Retensi data, siapa yang boleh melihat data pasien, dan prosedur jika akun staf keluar harus ditetapkan oleh klinik.

## 11. Tahapan implementasi

### Fase 0 — Validasi kebutuhan

- Konfirmasi peran pengguna, alur nomor lab, jenis pemeriksaan, format hasil yang wajib, dan siapa yang boleh finalisasi.
- Konfirmasi rentang referensi, satuan, dan template cetak dengan penanggung jawab klinis.
- Pilih satu set data Excel yang aman sebagai bahan uji migrasi.

**Selesai bila:** kebutuhan MVP, aturan bisnis, dan contoh output cetak disetujui.

### Fase 1 — Fondasi aplikasi

- Siapkan struktur PHP, konfigurasi environment, skema MariaDB, migrasi database, login, peran, dan audit log dasar.
- Siapkan layout sederhana yang nyaman di desktop dan tablet.

**Selesai bila:** pengguna dapat login dan admin dapat membuat akun staf.

### Fase 2 — Data pasien dan pemeriksaan MCU

- Bangun pencarian/registrasi pasien.
- Bangun pembuatan pemeriksaan dan form identitas, vital, darah, serta urine.
- Terapkan validasi dan status `draft`/`selesai`.

**Selesai bila:** satu pemeriksaan MCU dapat dicatat tanpa Excel.

### Fase 3 — Finalisasi dan cetak

- Bangun rentang referensi terkonfigurasi dan penanda hasil.
- Bangun halaman preview/cetak A4 untuk hasil darah/umum dan urine.
- Tambahkan finalisasi, cetak ulang, serta jejak audit.

**Selesai bila:** hasil dapat disimpan dan dicetak langsung tanpa langkah persetujuan tambahan.

### Fase 4 — Narkoba, bayi, dan rekap

- Tambahkan form serta template cetak narkoba dan bayi.
- Bangun filter, rekap, dan ekspor CSV.

**Selesai bila:** seluruh alur workbook yang masih dipakai telah tersedia dalam aplikasi.

### Fase 5 — Migrasi, uji penerimaan, dan go-live

- Impor data terverifikasi.
- Uji bersama pengguna klinik menggunakan skenario nyata.
- Latih staf, jalankan paralel dengan Excel secara terbatas, lalu aktifkan aplikasi sebagai sumber utama.

**Status teknis:** alat dry-run/impor CSV yang idempotent, tracking batch, verifikasi rekonsiliasi, preflight, runbook, skenario UAT, dan checklist go-live telah dibuat. Impor aktual sengaja menyimpan hasil sebagai `draft`.

**Status operasional:** belum selesai. Memerlukan database produksi, data yang telah dibersihkan/diizinkan, backup dan uji restore, persetujuan rentang referensi/cetakan, pelaksanaan UAT, pelatihan, pilot paralel, serta tanda tangan go-live pihak klinik.

**Selesai bila:** data, cetakan, akses, backup, dan prosedur operasional telah disetujui.

## 12. Pengujian minimum

- Nomor lab unik dan pasien dapat ditemukan dengan seluruh kunci pencarian yang didukung.
- Semua input wajib, tipe data, satuan, dan nilai pilihan tervalidasi.
- Status normal/abnormal mengikuti konfigurasi yang disetujui.
- Petugas tidak dapat mengakses fitur admin atau data di luar kewenangan.
- Hasil final tidak dapat berubah tanpa audit log.
- Cetakan A4 tidak terpotong dan memuat hasil pasien yang benar.
- Filter/ekspor menghasilkan jumlah yang sama dengan data pemeriksaan final.
- Backup dapat dipulihkan ke database uji.

### Tambahan implementasi — rujukan menurut jenis kelamin

- Admin membuat aturan `L` dan `P` terpisah bila nilai rujukan parameter berbeda menurut jenis kelamin; aturan `ALL` hanya digunakan bila memang sama.
- Evaluasi dan cetakan mengambil satu aturan yang cocok dengan jenis kelamin pasien, usia, dan versi aktif.
- Saat finalisasi, jenis rujukan yang terpilih ikut disimpan pada snapshot hasil sehingga cetak ulang tidak mencampur rujukan laki-laki/perempuan.
- Widal masuk ke katalog rentang kualitatif dan harus dikonfigurasi/ditinjau penanggung jawab klinis sebelum dapat difinalisasi.

### Tambahan implementasi — alur DL/DLL per pasien

- Hasil pencarian pasien membuka halaman data pasien yang menampilkan riwayat pemeriksaan milik pasien tersebut.
- Kartu identitas pada halaman pasien menyediakan tombol **Edit identitas** agar perubahan alamat/identitas tidak perlu melalui pencarian ulang.
- Pemeriksaan MCU umum memiliki tampilan khusus DL/DLL dengan identitas pasien, hematologi, dan kimia klinik yang tersedia pada data aplikasi.
- Ureum dan kreatinin ditambahkan sebagai parameter kimia klinik agar mengikuti kebutuhan pemeriksaan di klinik.
- Rujukan pada cetakan DL/DLL dipilih berdasarkan jenis kelamin pasien: aturan `L` untuk laki-laki dan `P` untuk perempuan; format legacy satu teks `L: ... P: ...` juga disaring saat ditampilkan.
- Preview DL/DLL menggunakan layout cetak A4; tombol cetak tersedia setelah data disimpan dan tetap mengikuti pencatatan audit/cetak.
- Data yang belum diisi ditampilkan sebagai tanda `—`; rentang referensi tetap harus diatur dan disetujui admin/penanggung jawab klinis.

## 13. Keputusan yang perlu dikonfirmasi sebelum coding

1. Jenis pemeriksaan mana yang wajib masuk MVP pertama: MCU umum saja, atau langsung termasuk urine, narkoba, dan bayi?
2. Siapa yang menjadi penanggung jawab klinis untuk menyetujui rentang referensi dan format hasil?
3. Apakah nomor lab dibuat otomatis, manual, atau mengikuti pola yang sudah ada?
4. Apakah pasien dapat memiliki beberapa pemeriksaan pada hari yang sama?
5. Berapa staf yang akan memakai aplikasi bersamaan dan apakah akses hanya dari jaringan klinik?
6. Apakah hasil boleh diekspor, dikirim ke pasien, atau hanya dicetak di internal klinik?
7. Berapa lama data dan audit log harus disimpan?

## 14. Kriteria aplikasi siap digunakan

- Semua alur MVP berjalan tanpa input ganda ke Excel.
- Pengguna klinik dapat menyelesaikan satu kasus dari registrasi sampai cetak tanpa bantuan teknis.
- Template cetak dan rentang referensi telah disetujui penanggung jawab klinis.
- Migrasi tervalidasi, backup teruji, dan hak akses telah diuji.
- Ada prosedur tertulis untuk koreksi hasil, cetak ulang, dan pemulihan data.
