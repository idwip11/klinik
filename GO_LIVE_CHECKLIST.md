# Checklist Go-Live

Checklist ini harus diisi oleh klinik. Tanda `[ ]` tidak boleh dianggap selesai hanya karena fitur aplikasinya sudah dibuat.

## A. Keputusan klinis dan operasional

- [ ] Penanggung jawab klinis menyetujui satuan, rentang referensi, dan format cetak.
- [ ] Role registrasi, petugas lab, dan supervisor beserta kewenangannya disetujui.
- [ ] Prosedur finalisasi, koreksi hasil, cetak ulang, dan insiden terdokumentasi.
- [ ] Keputusan status sheet `Laporan` sebagai data unik/duplikat sudah dibuat.
- [ ] Kebijakan retensi, ekspor, akses pasien, dan penghentian akun staf ditetapkan.

## B. Infrastruktur dan keamanan

- [ ] Domain produksi menggunakan HTTPS valid dan HTTP dialihkan ke HTTPS.
- [ ] `.env` produksi tidak berada di document root dan memakai kredensial unik.
- [ ] `APP_ENV=production`; nama/alamat/telepon klinik sudah benar.
- [ ] Document root menunjuk ke `public`; direktori `app`, `database`, `scripts`, dan `storage` tidak dapat diakses publik.
- [ ] MariaDB hanya dapat diakses dari aplikasi/host yang diperlukan.
- [ ] Semua akun uji/nonaktif ditutup dan tiap staf memakai akun sendiri.
- [ ] `php scripts/preflight.php` tidak menghasilkan `FAIL`.

## C. Data dan migrasi

- [ ] Workbook asli diarsipkan read-only; checksum dan lokasi arsip tercatat.
- [ ] Dry-run final tidak memiliki kegagalan yang belum diputuskan.
- [ ] Impor aktual selesai dan batch ID dicatat.
- [ ] `verify_import.php` lulus untuk setiap batch.
- [ ] Rekonsiliasi jumlah dan sampling manual disetujui klinik.
- [ ] Data migrasi ditinjau sebelum finalisasi; tidak ada hasil yang otomatis dianggap tervalidasi.
- [ ] CSV sementara ditangani/dihapus sesuai kebijakan klinik.

## D. Pengujian dan pelatihan

- [ ] Semua skenario kritis pada `UAT_SCENARIOS.md` lulus.
- [ ] Cetak A4 diuji pada printer dan browser yang benar-benar dipakai klinik.
- [ ] Registrasi, petugas lab, supervisor, dan admin telah menjalani latihan per role.
- [ ] Ada panduan kontak dan eskalasi bila aplikasi/database/Internet bermasalah.
- [ ] Pilot paralel dengan Excel telah dijalankan untuk periode yang disepakati.
- [ ] Selisih pilot sudah direkonsiliasi dan ditutup.

## E. Backup, cutover, dan rollback

- [ ] Backup penuh pra-cutover dibuat di lokasi terpisah dari hosting.
- [ ] Satu restore berhasil diuji pada database non-produksi.
- [ ] Jadwal backup harian serta pemilik pemeriksaan backup ditetapkan.
- [ ] Waktu cut-off, jendela maintenance, dan staf yang bertugas diumumkan.
- [ ] Kriteria pembatalan go-live dan prosedur rollback pada `MIGRATION_RUNBOOK.md` dipahami.
- [ ] Setelah cutover, aplikasi ditetapkan sebagai sumber utama dan Excel lama menjadi read-only.
- [ ] Pemeriksaan pasca-cutover hari 1, 3, 7, dan 30 dijadwalkan.

## Persetujuan

| Peran | Nama | Tanggal | Keputusan/catatan |
| --- | --- | --- | --- |
| Penanggung jawab klinis |  |  |  |
| Supervisor operasional |  |  |  |
| Perwakilan pengguna |  |  |  |
| Penanggung jawab teknis |  |  |  |

Go-live hanya boleh dilakukan bila tidak ada item kritis terbuka dan seluruh pemberi persetujuan menerima risiko yang tersisa.
