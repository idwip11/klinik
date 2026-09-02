# Rencana Implementasi Hasil Pemeriksaan Kumulatif

## Tujuan

Menyediakan tampilan perbandingan hasil pemeriksaan pasien dari waktu ke waktu. Setiap pemeriksaan ditampilkan sebagai kolom berdasarkan tanggal sehingga hasil lama, hasil terbaru, dan perubahan nilainya dapat dibandingkan dengan mudah.

Tahap ini hanya mencakup tampilan baca-saja dan tidak mengubah hasil pemeriksaan yang sudah tersimpan.

## Rancangan tampilan

Pada bagian **Riwayat hasil pemeriksaan** di halaman detail pasien ditambahkan tombol:

**Lihat hasil kumulatif**

Tombol membuka halaman khusus agar tabel memiliki ruang yang cukup. Identitas pasien ditampilkan secara ringkas:

- Nama pasien
- Nomor RM
- Jenis kelamin
- Tanggal lahir atau usia

Identitas tidak dibuat dominan karena fokus utama halaman adalah perbandingan hasil pemeriksaan.

## Bentuk tabel

Struktur tabel yang disarankan:

| Parameter | Satuan | 30/06/2026 | 30/08/2026 | 15/09/2026 |
|---|---|---:|---:|---:|
| **HEMATOLOGI** |  |  |  |  |
| Hemoglobin | g/dL | 13,1 | 12,8 | 13,4 |
| Leukosit | sel/mm³ | 5.700 | 7.200 | 6.500 |
| Trombosit | sel/mm³ | 220.000 | 240.000 | 231.000 |
| **KIMIA KLINIK** |  |  |  |  |
| Gula Darah Sewaktu | mg/dL | — | 114 | 108 |

Ketentuan tabel:

- Parameter berada pada baris.
- Setiap pemeriksaan menjadi satu kolom berdasarkan tanggal.
- Nomor laboratorium ditampilkan di bawah tanggal.
- Urutan pemeriksaan dari paling lama di kiri menuju paling baru di kanan.
- Kolom pemeriksaan terbaru diberi penanda visual ringan.
- Parameter yang tidak diperiksa pada tanggal tertentu berisi `—`.
- Angka mengikuti format Indonesia, misalnya `220.000` dan `13,5`.
- Hasil teks seperti `Positif`, `Negatif`, `Reaktif`, atau `Nonreaktif` tetap ditampilkan.

## Pengelompokan kategori

Parameter dikelompokkan berdasarkan panel:

- DL / Darah Lengkap / Hematologi
- Kimia Klinik
- Urine Lengkap / Urinalisis
- Imunoserologi
- Skrining Narkoba
- Widal
- Panel lain yang tersedia

Setiap kelompok dibuat dalam bentuk bagian yang dapat dibuka dan ditutup. Kategori yang memiliki hasil dapat dibuka secara bawaan.

## Filter halaman

Untuk riwayat yang banyak, halaman menyediakan:

- Filter dari tanggal
- Filter sampai tanggal
- Filter kategori atau panel
- Pilihan jumlah pemeriksaan: 5 terbaru, 10 terbaru, atau semua

Bawaan halaman menampilkan lima pemeriksaan terakhir. Pengguna tetap dapat memilih **Semua pemeriksaan**.

## Perilaku tabel dan responsif

- Tabel dapat digeser secara horizontal.
- Kolom **Parameter** dan **Satuan** tetap menempel di kiri saat tabel digeser.
- Kepala tabel berisi tanggal tetap terlihat ketika halaman digulir ke bawah.
- Ditampilkan petunjuk singkat bahwa tabel dapat digeser.
- Pada layar kecil, tabel tetap menggunakan gulir horizontal agar urutan tanggal tidak rusak.

## Penanda hasil abnormal

Hasil abnormal dapat diberi warna merah seperti pada hasil cetak, dengan ketentuan:

- Penilaian menggunakan nilai rujukan aktif saat ini yang cocok dengan pasien.
- Nilai rujukan aktif saat ini juga diterapkan pada pemeriksaan lama agar seluruh riwayat dapat dibandingkan dengan acuan yang konsisten.
- Jika tidak ada aturan aktif yang cocok dengan parameter, usia, atau jenis kelamin, hasil tetap ditampilkan tanpa penanda abnormal.
- Hasil `Positif` dengan nilai rujukan `Negatif` dapat diberi warna merah.

## Pengolahan data

Sistem akan:

1. Mengambil seluruh pemeriksaan pasien sesuai filter.
2. Menggabungkan hasil panel baru dan data hasil dari sistem lama.
3. Mengenali parameter berdasarkan kode parameter, bukan hanya nama tampilan.
4. Menyusun data menjadi matriks parameter × tanggal pemeriksaan.
5. Menghindari data ganda ketika parameter yang sama tersimpan pada sumber modern dan legacy.
6. Membedakan nilai `0` dari kolom yang benar-benar kosong.

Kemungkinan besar tidak diperlukan perubahan struktur database karena data riwayat sudah tersedia.

## Struktur implementasi teknis

1. Tambahkan tombol **Lihat hasil kumulatif** pada halaman detail pasien.
2. Tambahkan halaman khusus, misalnya `/patients/{id}/cumulative-results`.
3. Tambahkan pengambil data kumulatif yang menggabungkan hasil modern dan legacy.
4. Susun parameter berdasarkan kategori dan urutan katalog laboratorium.
5. Buat tabel perbandingan dengan tanggal dan nomor lab sebagai kepala kolom.
6. Tambahkan filter tanggal, kategori, dan jumlah pemeriksaan.
7. Tambahkan penanda abnormal berdasarkan nilai rujukan aktif saat ini untuk seluruh riwayat pasien.
8. Sesuaikan tampilan desktop dan perangkat berlayar kecil.

## Pengujian yang diperlukan

Fitur diuji untuk kondisi berikut:

- Pasien belum pernah diperiksa.
- Pasien hanya memiliki satu pemeriksaan.
- Pasien memiliki beberapa pemeriksaan dengan panel yang sama.
- Panel pemeriksaan berbeda pada setiap tanggal.
- Data legacy, data panel baru, dan gabungan keduanya.
- Hasil numerik, hasil teks, serta nilai nol.
- Pasien aktif dan pasien yang sudah diarsipkan.
- Riwayat banyak sehingga tabel perlu digeser.
- Nilai rujukan berbeda berdasarkan usia atau jenis kelamin.
- Urutan tanggal dan nomor lab konsisten.
- Parameter yang tidak tersedia pada pemeriksaan tertentu menampilkan `—`.

## Kriteria penerimaan

Implementasi dianggap selesai jika:

- Tombol kumulatif dapat dibuka dari detail pasien.
- Semua pemeriksaan sesuai filter tampil sebagai kolom tanggal.
- Parameter dan hasil dapat dibandingkan secara horizontal.
- Data lama dan data baru tidak hilang atau tampil ganda.
- Format angka Indonesia tetap konsisten.
- Kategori dapat dibuka dan ditutup.
- Tampilan tetap terbaca pada desktop dan layar kecil.
- Fitur tidak mengubah data hasil pemeriksaan.

## Batas tahap pertama

Prioritas tahap pertama:

- Tabel kumulatif.
- Filter tanggal dan kategori.
- Pengelompokan panel.
- Penanda hasil abnormal yang valid.
- Tampilan responsif dan mudah dibandingkan.

Fitur cetak, ekspor Excel, grafik tren, dan bagian lain dari aplikasi contoh belum termasuk tahap pertama. Fitur tersebut dapat dipertimbangkan setelah tampilan kumulatif stabil.
