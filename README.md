# Aplikasi Operasional Laboratorium Klinik

Fondasi Fase 1–7 untuk aplikasi PHP + MariaDB yang ditujukan untuk shared hosting. Rilis ini menyediakan konfigurasi, skema basis data, login, dua peran (`admin` dan `perawat`), audit log, pengelolaan pengguna, registrasi/pencarian pasien, MCU umum, darah bayi, skrining narkoba, katalog panel pemeriksaan, rentang referensi dengan workflow review klinis, finalisasi, preview A4, rekap, ekspor CSV, jejak cetak, serta perangkat migrasi/UAT/go-live.

Rentang referensi belum disediakan secara bawaan. Supervisor harus memasukkan aturan yang telah disetujui klinik; hasil tanpa aturan akan ditandai “belum dinilai”. Data lama dapat dimigrasikan dari CSV UTF-8 setelah dry-run, tetapi pelaksanaan migrasi, UAT, backup/restore, pelatihan, dan persetujuan go-live tetap harus dilakukan bersama klinik.

## Kebutuhan

- PHP 8.2 atau lebih baru dengan ekstensi `pdo_mysql`, `gd`, `dom`, dan `mbstring`.
- Composer 2 untuk memasang dependensi QR code dan PDF.
- MariaDB 10.4+ atau MySQL 8+.
- Akses HTTPS pada lingkungan produksi.

## Konfigurasi lokal

1. Salin `.env.example` menjadi `.env`, lalu isi kredensial database.
2. Jalankan `composer install`.
3. Buat database kosong dengan charset `utf8mb4`.
4. Jalankan `php scripts/migrate.php`.
5. Jalankan `php scripts/create_admin.php` untuk membuat akun administrator pertama.
6. Jalankan server lokal dari folder `public`: `php -S localhost:8000 -t public`.

Untuk pemeriksaan MCU baru, Widal selalu disertakan. Imunoserologi tersedia sebagai checklist opsional. Pilihan panel pemeriksaan lama tetap mengikuti pilihan yang tersimpan.

Semua dokumen hasil menggunakan identitas 13 px (9,75 pt pada PDF/Excel) dan menampilkan **Umur** dalam tahun, bulan, hari berdasarkan tanggal periksa. Kolom Dokter tidak digunakan pada form pemeriksaan, riwayat pasien, maupun identitas hasil cetak/PDF/Excel. Tanggal lahir asli tetap disimpan di data pasien; umur yang tidak bisa dihitung ditampilkan sebagai `—`.

Rujukan Urine Lengkap untuk semua jenis kelamin dan usia: Urobilinogen **Normal**; Bilirubin, Darah Samar, Keton, Protein Urine, dan Glukosa/Reduksi **Negatif**. Parameter kualitatif tersebut tidak memakai satuan konsentrasi. Perubahan rujukan disimpan sebagai versi baru tanpa mengubah hasil atau snapshot rujukan lama.

Semua format hasil pemeriksaan menyediakan **Download PDF**, selain Excel dan cetak A4: DL/DLL, hasil panel (termasuk ANC), darah/umum, urine lengkap, darah bayi, dan skrining narkoba. PDF dibuat di server menggunakan Dompdf dari template cetak yang sama, tanpa layanan eksternal atau penyimpanan permanen file pasien. Akses tetap memerlukan login; unduhan dicatat terpisah di audit log dan tidak dihitung sebagai cetak fisik. Setelah deploy perubahan ini, jalankan `composer install --no-dev` untuk memasang dependensi PDF sesuai lock file.

Panel opsional **ANC ibu hamil** tersedia khusus pasien dengan jenis kelamin perempuan. Delapan parameternya adalah Hemoglobin, Golongan Darah/Rhesus, HBsAg, HIV/AIDS, Sifilis, Gula Darah Sewaktu, Albumin Urine, dan Reduksi Urine. Nilai normal ANC berasal dari lembar contoh klinik: Hemoglobin 11,4–15,1 g/dL, HBsAg/HIV Non Reaktif, serta Sifilis/Albumin Urine/Reduksi Urine Negatif. Gula Darah Sewaktu ANC memakai rujukan yang sama dengan GDS umum, yaitu < 200 mg/dL. Golongan darah/Rhesus tidak memiliki nilai normal. Semua hasil diisi petugas; nilai rujukan ANC tersimpan terpisah dari rujukan panel lain dan hanya berlaku untuk perempuan.

Rekap menyediakan filter panel dan jenis kelamin serta rincian hasil panel per pemeriksaan. Excel Rekap memuat sheet **Logbook**, **LOGBOOK MCU**, dan **Hasil Panel** (termasuk ANC, Widal, serta Imunoserologi dengan rujukan aktif). Analisis dan hasil kumulatif menggunakan rujukan aktif khusus populasi yang sesuai. Golongan darah/Rhesus dicatat tanpa penilaian normal/abnormal; panel otomatis seperti Widal yang masih seluruhnya kosong tidak dianggap belum lengkap. Filter panel Rekap mencakup panel yang dipilih, sedangkan kategori Analisis menggunakan panel yang sudah mempunyai hasil.

## Deploy shared hosting

1. Atur document root domain/subdomain ke folder `public`.
2. Simpan `.env`, folder `app`, `database`, dan `storage` di luar document root bila panel hosting mengizinkan.
3. Pastikan `storage/logs` dapat ditulis oleh PHP, namun tidak dapat diakses melalui web.
4. Impor/eksekusi migrasi, buat administrator pertama, kemudian uji login melalui HTTPS.
5. Buat backup MariaDB harian ke lokasi terpisah dari hosting dan uji pemulihan secara berkala.

## Fase 5

- Jalankan pemeriksaan kesiapan dengan `php scripts/preflight.php`.
- Ikuti [MIGRATION_RUNBOOK.md](MIGRATION_RUNBOOK.md) untuk dry-run, impor CSV, dan rekonsiliasi.
- Gunakan [UAT_SCENARIOS.md](UAT_SCENARIOS.md) untuk uji penerimaan pengguna.
- Selesaikan [GO_LIVE_CHECKLIST.md](GO_LIVE_CHECKLIST.md) sebelum menjadikan aplikasi sumber utama.

Jangan unggah workbook sumber atau data pasien ke repository. Sebelum dipakai operasional, selesaikan keputusan Fase 0, terutama aturan klinis, hak akses, dan retensi data.
