# Skenario Uji Penerimaan Pengguna (UAT)

UAT dilakukan pada database uji dengan data sintetis atau data yang sudah diizinkan klinik. Penguji mencatat tanggal, nama/role, hasil aktual, status `Lulus/Gagal`, dan bukti secukupnya tanpa menyebarkan data pasien.

| ID | Role | Skenario | Hasil yang diharapkan |
| --- | --- | --- | --- |
| UAT-01 | Semua | Login dengan akun aktif dan logout | Sesi dibuat dan diakhiri dengan benar. |
| UAT-02 | Semua | Login salah berulang | Akses ditolak tanpa membocorkan akun/password. |
| UAT-03 | Registrasi | Cari pasien via RM, NIK, nama | Pasien yang tepat ditemukan. |
| UAT-04 | Registrasi | Daftarkan pasien baru | Identifier tersimpan sebagai teks; duplikasi RM/NIK ditolak. |
| UAT-05 | Registrasi | Buat MCU dengan nomor lab baru | Pemeriksaan `draft` dibuat untuk pasien yang benar. |
| UAT-06 | Registrasi | Gunakan nomor lab yang sudah ada | Sistem menolak duplikasi. |
| UAT-07 | Petugas lab | Isi vital, darah, urine, Widal | Nilai tersimpan dan muncul kembali tanpa perubahan format bermakna. |
| UAT-08 | Petugas lab | Isi angka/tanggal tidak valid | Sistem menolak dan menunjukkan field yang perlu diperbaiki. |
| UAT-09 | Petugas lab | Isi skrining narkoba | Enam parameter dan tujuan pemeriksaan tersimpan benar. |
| UAT-10 | Petugas lab | Isi darah bayi | Jenis pemeriksaan dan hasil darah tersimpan benar. |
| UAT-11 | Supervisor | Tinjau flag rentang referensi | Flag memakai versi aturan aktif yang disetujui; tanpa aturan tampil “belum dinilai”. |
| UAT-12 | Petugas non-supervisor | Coba finalisasi | Akses ditolak. |
| UAT-13 | Supervisor | Finalisasi pemeriksaan lengkap | Snapshot hasil/aturan dan audit finalisasi tersimpan. |
| UAT-14 | Semua | Coba ubah hasil final | Perubahan langsung ditolak; jejak hasil final tetap utuh. |
| UAT-15 | Supervisor | Preview/cetak darah dan urine | Identitas/hasil benar, A4 tidak terpotong, cetak tercatat. |
| UAT-16 | Supervisor | Preview/cetak narkoba dan bayi | Template sesuai jenis dan tidak mencampur pasien. |
| UAT-17 | Supervisor | Cetak ulang | Cetak ulang berhasil dan audit/catatan cetak bertambah. |
| UAT-18 | Berwenang | Filter laporan tanggal/jenis/status | Jumlah baris sama dengan pemeriksaan yang memenuhi filter. |
| UAT-19 | Berwenang | Ekspor CSV | Kolom/jumlah sesuai filter dan formula injection dinetralisasi. |
| UAT-20 | Admin | Buat/nonaktifkan user dan uji role | Hak akses berubah sesuai role; akun nonaktif tidak dapat login. |
| UAT-21 | Admin teknis | Dry-run CSV dengan baris salah | Tidak ada data ditulis; nomor baris/field masalah dilaporkan tanpa isi pasien. |
| UAT-22 | Admin teknis | Impor dan jalankan ulang CSV yang sama | Impor pertama membuat `draft`; impor ulang tidak menggandakan nomor lab. |
| UAT-23 | Admin teknis | Jalankan verifikasi batch | Rekonsiliasi otomatis lulus dan sampling manual cocok dengan sumber. |
| UAT-24 | Admin teknis | Restore backup ke database uji | Aplikasi dapat login, mencari, membuka, dan mencetak data hasil restore. |
| UAT-25 | Petugas lab | Buat MCU dengan kombinasi DL + Urine | Form hanya menampilkan DL dan Urine, serta Imunoserologi otomatis. Panel Kimia Klinik/Widal tidak muncul. |
| UAT-26 | Petugas lab | Buat MCU hanya dengan Kimia Klinik | Semua parameter Kimia Klinik dan Imunoserologi tampil; parameter DL/Urine/Widal tidak tampil. |
| UAT-27 | Petugas lab | Simpan hasil panel secara parsial | Simpan berhasil tanpa parameter wajib; kembali ke riwayat pasien dan hasil dapat dibuka kembali. |
| UAT-28 | Petugas lab | Masukkan parameter dari panel yang tidak dipilih melalui request/form | Sistem menolak parameter tersebut dan tidak menyimpannya. |
| UAT-29 | Petugas lab | Buka Preview cetak A4 pemeriksaan panel | Header, identitas, panel terpilih, Imunoserologi, kolom kosong `—`, footer, dan garis tanda tangan tampil; panel lain tidak tercetak. |
| UAT-30 | Admin | Buat draft nilai rujukan dengan jenis kelamin dan usia hari | Draft tersimpan nonaktif; sumber/catatan persetujuan wajib dicatat. |
| UAT-31 | Admin + penanggung jawab klinis | Setujui draft setelah review angka/satuan | Tanpa konfirmasi checkbox aktivasi gagal; dengan konfirmasi versi aktif dapat dipakai evaluasi. |
| UAT-32 | Admin + penanggung jawab klinis | Uji pasien L/P pada batas umur | Aturan jenis kelamin yang cocok dipilih; aturan `Semua` hanya menjadi fallback; batas minimum/maksimum inklusif. |
| UAT-33 | Admin | Buat rentang usia aktif yang overlap | Sistem menolak overlap pada parameter dan jenis kelamin yang sama, tetapi mengizinkan fallback semua usia. |
| UAT-34 | Admin + penanggung jawab klinis | Aktifkan versi baru pada scope sama | Versi lama menjadi arsip, snapshot pemeriksaan lama tidak berubah, dan versi baru dipakai pemeriksaan berikutnya. |

## Kriteria penerimaan

- Semua skenario kritis UAT-03 sampai UAT-17, UAT-21 sampai UAT-24, dan UAT-25 sampai UAT-34 lulus.
- Tidak ada masalah keamanan, pencampuran identitas pasien, kehilangan nilai, atau cetakan salah pasien yang terbuka.
- Masalah minor memiliki pemilik, target perbaikan, dan keputusan tertulis apakah menghambat go-live.
- Template cetak dan rentang referensi disetujui penanggung jawab klinis.
- Supervisor klinik, perwakilan pengguna, dan penanggung jawab teknis memberi persetujuan tertulis.

## Bukti otomatis Fase 7

Uji sintetis yang dapat dijalankan ulang:

```text
php scripts/verify_panel_uat.php
php scripts/verify_reference_range_workflow.php
php scripts/verify_panel_catalog.php
php scripts/verify_panel_results.php
php scripts/verify_panel_print.php
```

Uji otomatis memverifikasi struktur, pembatasan panel, penyimpanan hasil, evaluasi, dan render cetak tanpa meninggalkan data. Persetujuan angka klinis, keterbacaan hasil cetak di perangkat nyata, dan tanda tangan go-live tetap harus diisi manual oleh klinik.
