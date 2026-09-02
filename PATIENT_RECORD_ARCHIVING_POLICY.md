# Pengarsipan dan Penghapusan Data Pasien

## Tujuan

Menjaga riwayat pemeriksaan tetap terlacak, sambil memberi administrator cara aman untuk menyingkirkan data pasien yang tidak lagi aktif atau data uji.

## Aturan aplikasi

| Kondisi pasien | Tindakan yang tersedia | Dampak |
| --- | --- | --- |
| Belum memiliki pemeriksaan | Hapus permanen | Data pasien dihapus dan tidak dapat dipulihkan. |
| Memiliki satu atau lebih pemeriksaan | Arsipkan | Pasien tidak tampil pada pencarian aktif dan tidak dapat dibuatkan pemeriksaan baru; identitas serta riwayat pemeriksaan tetap tersimpan. |
| Sudah diarsipkan | Pulihkan | Pasien kembali ke pencarian aktif dan dapat dibuatkan pemeriksaan baru. |

## Pengamanan

- Hanya pengguna dengan peran `admin` yang dapat mengarsipkan, memulihkan, atau menghapus permanen.
- Admin harus mengetik nomor RM yang tepat sebelum tindakan arsip atau hapus diproses.
- Penghapusan permanen ditolak kembali oleh backend bila pasien mempunyai riwayat pemeriksaan, termasuk jika permintaan dibuat langsung tanpa memakai tombol aplikasi.
- Setiap pengarsipan, pemulihan, dan penghapusan dicatat pada audit log.

## Catatan operasional

Penghapusan permanen dimaksudkan untuk data yang dibuat keliru atau data uji tanpa riwayat pemeriksaan. Untuk pasien nyata yang sudah mempunyai hasil laboratorium, gunakan pengarsipan.

Pemeriksaan milik pasien yang sudah diarsipkan tidak ditampilkan pada menu **Rekap pemeriksaan** maupun hasil ekspornya. Riwayatnya hanya dapat dilihat dari **Arsip pasien** sampai pasien dipulihkan.

## Lokasi menu

Administrator dapat membuka menu **Arsip pasien** pada navigasi utama atau tautan di halaman **Pasien**. Daftar ini mendukung pencarian berdasarkan nomor RM, NIK, atau nama. Pilih **Lihat data & pulihkan** untuk membuka riwayat pasien dan memulihkannya.
