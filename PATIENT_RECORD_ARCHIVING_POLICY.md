# Penghapusan Permanen Data Pasien

## Kebijakan saat ini

Aplikasi menggunakan penghapusan permanen untuk membersihkan data pasien. Penghapusan memerlukan konfirmasi nomor RM dan menghapus identitas pasien, seluruh pemeriksaan, hasil pemeriksaan, serta log cetak dalam satu transaksi database.

Nomor RM otomatis tidak lagi mempertimbangkan pasien yang sudah dihapus. Nomor Lab untuk tanggal yang pemeriksaannya sudah dihapus juga akan menghitung ulang urutan dari data yang masih tersisa.

Kolom `archived_at` dan halaman data lama tetap dipertahankan sementara untuk menangani pasien yang dibuat pada versi aplikasi sebelumnya. Data lama tersebut perlu dihapus permanen oleh administrator melalui halaman pasien terkait.

## Data yang dihapus

- Hanya pengguna dengan peran `admin` yang dapat menghapus permanen.
- Admin harus mengetik nomor RM yang tepat sebelum tindakan diproses.
- Penghapusan pasien dengan riwayat pemeriksaan tetap diizinkan dan ikut menghapus seluruh data pemeriksaan terkait.
- Penghapusan juga menghapus hasil pemeriksaan, hasil panel, flag, serta log cetak yang terkait.
- Setiap penghapusan dicatat pada audit log, tetapi data klinis pasien yang dihapus tidak dapat dipulihkan.

## Data lama

Pasien yang masih berstatus arsip dari versi sebelumnya tidak lagi menerima opsi pulihkan. Administrator dapat membukanya melalui halaman data pasien lama dan memilih penghapusan permanen.
