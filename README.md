# Aplikasi Operasional Laboratorium Klinik

Fondasi Fase 1–7 untuk aplikasi PHP + MariaDB yang ditujukan untuk shared hosting. Rilis ini menyediakan konfigurasi, skema basis data, login, dua peran (`admin` dan `perawat`), audit log, pengelolaan pengguna, registrasi/pencarian pasien, MCU umum, darah bayi, skrining narkoba, katalog panel pemeriksaan, rentang referensi dengan workflow review klinis, finalisasi, preview A4, rekap, ekspor CSV, jejak cetak, serta perangkat migrasi/UAT/go-live.

Rentang referensi belum disediakan secara bawaan. Supervisor harus memasukkan aturan yang telah disetujui klinik; hasil tanpa aturan akan ditandai “belum dinilai”. Data lama dapat dimigrasikan dari CSV UTF-8 setelah dry-run, tetapi pelaksanaan migrasi, UAT, backup/restore, pelatihan, dan persetujuan go-live tetap harus dilakukan bersama klinik.

## Kebutuhan

- PHP 8.2 atau lebih baru dengan ekstensi `pdo_mysql` dan `gd`.
- Composer 2 untuk memasang dependensi QR code.
- MariaDB 10.4+ atau MySQL 8+.
- Akses HTTPS pada lingkungan produksi.

## Konfigurasi lokal

1. Salin `.env.example` menjadi `.env`, lalu isi kredensial database.
2. Jalankan `composer install`.
3. Buat database kosong dengan charset `utf8mb4`.
4. Jalankan `php scripts/migrate.php`.
5. Jalankan `php scripts/create_admin.php` untuk membuat akun administrator pertama.
6. Jalankan server lokal dari folder `public`: `php -S localhost:8000 -t public`.

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
