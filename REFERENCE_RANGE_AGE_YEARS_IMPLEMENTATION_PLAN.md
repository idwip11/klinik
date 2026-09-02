# Rencana Implementasi — Rentang Usia Nilai Rujukan dalam Tahun

Status: **selesai diimplementasikan pada 30/08/2026.** Klinik menyetujui arti usia sebagai usia ulang tahun penuh pada tanggal pemeriksaan.

## Tujuan

Mengganti input usia pada menu **Nilai rujukan** dari hari menjadi tahun agar lebih mudah dipahami admin, tanpa mengubah keakuratan evaluasi berdasarkan tanggal lahir dan tanggal pemeriksaan.

## Keputusan teknis yang disarankan

- Tampilan input menggunakan **Usia minimum (tahun)** dan **Usia maksimum (tahun)**.
- Sistem menilai usia sebagai **usia ulang tahun penuh** pada tanggal pemeriksaan, bukan membagi jumlah hari dengan 365.
- Batas bersifat inklusif: rentang `0–17 tahun` berarti sejak lahir sampai sehari sebelum ulang tahun ke-18; `18 tahun` mulai berlaku pada tanggal ulang tahun ke-18.
- Kolom `min_age_days` dan `max_age_days` yang lama dipertahankan untuk aturan historis agar hasil lama tidak berubah.
- Aturan baru dalam tahun disimpan pada kolom baru `min_age_years` dan `max_age_years`; evaluator memilih aturan tahun secara tepat berdasarkan usia ulang tahun penuh.
- Aturan tanpa batas usia tetap menggunakan nilai kosong dan ditampilkan sebagai **Semua usia**.

## Perubahan data

1. Tambahkan `min_age_years` dan `max_age_years` bertipe bilangan bulat tak-negatif pada `reference_ranges`.
2. Tambahkan validasi bahwa maksimum tidak boleh lebih kecil dari minimum.
3. Tambahkan indeks cakupan usia tahun tanpa menghapus indeks hari yang dipakai data lama.
4. Aturan lama tetap dibaca dengan kolom hari; aturan baru memakai kolom tahun. Untuk satu parameter dan jenis kelamin yang sama, rentang hari dan tahun tidak dicampur demi menghindari batas yang ambigu; buat versi pengganti terlebih dahulu.

## Perubahan aplikasi

1. Form menampilkan input usia tahun serta keterangan contoh `0–17` atau `18–59`.
2. Halaman riwayat menampilkan `0–17 tahun`, bukan jumlah hari.
3. `ReferenceEvaluator` menghitung usia penuh dari tanggal lahir ke tanggal pemeriksaan dan mencocokkannya dengan aturan tahun.
4. Validasi overlap, versi pengganti, serta fallback semua usia tetap diterapkan.
5. Cetak A4 tetap memakai snapshot nilai rujukan yang sudah tersimpan sehingga hasil historis tidak berubah.

## Uji penerimaan

- Pasien tepat pada hari ulang tahun ke-18 berpindah dari aturan `0–17` ke aturan `18+`.
- Pasien sehari sebelum ulang tahun ke-18 masih memakai aturan `0–17`.
- Tahun kabisat tidak menghasilkan pergeseran batas.
- Aturan lama berbasis hari masih menghasilkan interpretasi yang sama.
- Dua aturan tahun yang overlap ditolak.
- Aturan tahun dan aturan hari tidak dapat dicampur pada parameter serta jenis kelamin yang sama tanpa versi pengganti.

## Catatan implementasi

Klinik telah mengonfirmasi bahwa istilah “usia dalam tahun” bermakna **usia ulang tahun penuh pada tanggal pemeriksaan**. Ini menghindari pembulatan 365 hari yang dapat keliru di sekitar ulang tahun dan tahun kabisat.
