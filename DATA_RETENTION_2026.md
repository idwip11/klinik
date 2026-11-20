# Kebijakan Retensi Data 2026

Aplikasi hanya menerima, mengimpor, menampilkan riwayat, merekap, dan menganalisis pemeriksaan dengan tanggal **1 Januari 2026** atau setelahnya.

Sebelum pembersihan data produksi, buat backup database penuh dari cPanel/phpMyAdmin. Jalankan simulasi terlebih dahulu:

```bash
php scripts/purge_pre_2026_examinations.php --dry-run
```

Setelah jumlah yang tampil sudah diverifikasi, jalankan penghapusan permanen:

```bash
php scripts/purge_pre_2026_examinations.php --execute --confirm=HAPUS_DATA_SEBELUM_2026
```

Skrip menghapus pemeriksaan sebelum 2026 beserta hasil, pilihan panel, flag hasil, log cetak, dan nomor urut lab terkait dalam satu transaksi. Identitas pasien yang **hanya** mempunyai pemeriksaan sebelum 2026 ikut dihapus sehingga nomor RM-nya dapat digunakan kembali. Pasien yang memiliki sedikitnya satu pemeriksaan mulai 2026 tetap dipertahankan.
