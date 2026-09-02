# Rencana Implementasi Menu Analisis Laboratorium

## Status dan tujuan

Dokumen ini merencanakan menu baru **Analisis laboratorium** untuk membantu pemantauan operasional laboratorium, penggunaan pemeriksaan, pola hasil, demografi pasien, dan asuransi/penjamin.

Menu ini adalah alat analisis data agregat. Menu ini **bukan** alat diagnosis, tidak menggantikan penilaian klinis, dan tidak membuat rekomendasi terapi.

Tujuan utamanya adalah agar administrator dan analis kesehatan dapat menjawab pertanyaan seperti:

- Berapa pemeriksaan yang dilakukan minggu atau bulan ini?
- Pemeriksaan dan parameter apa yang paling sering digunakan?
- Parameter apa yang paling banyak menghasilkan nilai di luar rujukan aktif?
- Bagaimana sebaran pasien berdasarkan usia, jenis kelamin, dan asuransi?
- Apakah terdapat data pemeriksaan yang belum lengkap atau masih berstatus draft?

## Prinsip desain

1. **Agregat lebih dahulu.** Halaman awal berisi ringkasan, grafik, dan tabel kelompok; nama pasien tidak ditampilkan sebagai bagian dari dashboard.
2. **Filter konsisten.** Semua kartu, grafik, dan tabel menggunakan periode dan filter yang sama.
3. **Dapat ditelusuri.** Setiap angka memiliki definisi yang jelas dan dapat diturunkan dari data pemeriksaan aplikasi.
4. **Aman terhadap data klinis.** Nilai abnormal hanya menunjukkan hasil di luar nilai rujukan aktif yang cocok; bukan diagnosis atau tingkat kegawatan.
5. **Tidak mengubah data.** Menu bersifat baca-saja dan tidak mengubah pasien, pemeriksaan, atau hasil.
6. **Responsif.** Grafik dan tabel tetap terbaca pada desktop, tablet, serta ponsel.

## Cakupan data saat ini

Fitur dapat memanfaatkan data yang sudah ada tanpa perubahan besar pada struktur data:

| Sumber data | Informasi yang dipakai |
| --- | --- |
| `patients` | Nomor RM, tanggal lahir, jenis kelamin, alamat, status arsip, asuransi/penjamin |
| `examinations` | Nomor lab, tanggal dan waktu pemeriksaan, jenis pemeriksaan, status |
| Hasil legacy | Hasil pemeriksaan darah/urine/narkoba/widal yang tersimpan pada tabel hasil lama |
| `examination_parameter_results` | Hasil dari panel pemeriksaan yang baru, termasuk kode dan satuan parameter |
| `reference_ranges` | Nilai rujukan aktif yang menjadi acuan penilaian agregat hasil abnormal |
| `examination_result_flags` | Snapshot penilaian hasil yang tersimpan saat finalisasi, bila tersedia |

Kebijakan saat ini untuk halaman kumulatif adalah memakai nilai rujukan aktif saat ini pada seluruh riwayat. Dashboard analisis mengikuti kebijakan tersebut agar angka abnormal konsisten dengan tampilan pasien. Teks penjelasan dashboard harus menyatakan bahwa penanda abnormal memakai acuan aktif saat ini.

## Batasan data yang perlu diketahui

Beberapa analitik belum dapat dibuat secara valid tanpa sumber data baru:

| Analitik | Status | Alasan |
| --- | --- | --- |
| Waktu tunggu dari pendaftaran sampai hasil final | Tahap lanjutan | Perlu timestamp alur pendaftaran, pengambilan sampel, pemeriksaan, dan finalisasi yang lengkap |
| Kontrol mutu alat/reagen | Tahap lanjutan | Perlu data QC, lot reagen, alat, kalibrasi, dan hasil kontrol |
| Pendapatan/biaya per pemeriksaan | Di luar cakupan awal | Aplikasi belum menyimpan tarif, transaksi, dan status pembayaran |
| Tren epidemiologis resmi | Di luar cakupan awal | Memerlukan definisi kasus, validasi klinis, dan tata kelola pelaporan yang lebih ketat |

## Pengguna dan hak akses

### Tahap pertama

Menu **Analisis** hanya tersedia untuk peran `admin`, sama seperti menu Rekap. Hal ini sesuai dengan peran yang tersedia saat ini dan membatasi akses data agregat sensitif.

### Tahap lanjutan

Jika analis kesehatan perlu akses tanpa hak administrasi penuh, tambahkan peran khusus, misalnya `analis_kesehatan`, dengan izin:

- Melihat dashboard dan ekspor agregat.
- Tidak dapat mengubah nilai rujukan, pasien, pengguna, atau pemeriksaan.
- Tidak melihat daftar identitas pasien dari halaman Analisis secara bawaan.

Perubahan peran bukan bagian dari MVP agar cakupan pertama tetap terukur.

## Rancangan menu dan rute

Navigasi utama ditambahkan item:

```text
Beranda | Pasien | Nilai rujukan | Rekap | Analisis | Pengguna | Keluar
```

Rute utama yang disarankan:

| Rute | Fungsi |
| --- | --- |
| `/analytics` | Dashboard analisis laboratorium dengan seluruh ringkasan dan filter |
| `/analytics/export` | Ekspor data agregat sesuai filter (tahap lanjutan setelah dashboard stabil) |

Halaman tidak perlu dipecah menjadi banyak submenu pada MVP. Bagian-bagian dashboard cukup diberi anchor/link internal agar mudah dilompat.

## Filter global

Filter ditempatkan pada bagian atas dan diterapkan ke semua komponen halaman.

| Filter | Nilai awal | Keterangan |
| --- | --- | --- |
| Dari tanggal | Hari pertama bulan berjalan | Berdasarkan `examined_on` |
| Sampai tanggal | Hari ini | Inklusif |
| Jenis pemeriksaan | Semua | Menggunakan jenis pemeriksaan yang tersedia di aplikasi |
| Kategori/panel | Semua kategori | Contoh: hematologi, kimia klinik, urinalisis |
| Asuransi/penjamin | Semua | Umum, BPJS Kesehatan, Pemerintah Daerah Kota, dan nilai yang tersedia di masa depan |
| Jenis kelamin | Semua | Laki-laki, Perempuan, atau semua |
| Kelompok usia | Semua | Lihat definisi kelompok usia di bawah |
| Status pemeriksaan | Semua data tersimpan | Bawaan mencakup `draft`, `completed`, dan `finalized`; pilihan khusus tetap tersedia untuk menyaring satu status |

Pasien arsip tidak ditampilkan secara bawaan, mengikuti kebijakan Rekap. Filter internal opsional `Sertakan pasien arsip` hanya dapat dipertimbangkan pada tahap lanjutan dan harus diberi penanda jelas.

### Definisi kelompok usia

Usia dihitung dari tanggal lahir ke tanggal pemeriksaan. Bawaan yang sederhana dan mudah dibaca:

| Kelompok | Rentang |
| --- | --- |
| Anak | 0–17 tahun |
| Dewasa muda | 18–39 tahun |
| Dewasa | 40–59 tahun |
| Lansia | 60 tahun ke atas |
| Tidak diketahui | Tanggal lahir kosong/tidak valid |

Kelompok tersebut dipakai hanya untuk agregasi. Nilai rujukan abnormal tetap memakai aturan usia aktif yang lebih spesifik bila tersedia.

## Struktur halaman

```text
Analisis laboratorium
Deskripsi singkat + waktu pembaruan data

[ Filter global ] [ Terapkan filter ] [ Reset ]

[ Total pemeriksaan ] [ Pasien unik ] [ Hasil abnormal ] [ Persentase kelengkapan ]

Tren operasional              Pemeriksaan menurut asuransi
[ grafik garis/batang ]       [ grafik batang/donut ]

Pemeriksaan dan panel teratas Hasil abnormal per parameter
[ tabel/grafik batang ]       [ tabel urut terbesar ]

Demografi pasien              Kualitas/konsistensi data
[ usia + jenis kelamin ]      [ status/pelengkapan ]

Catatan metode dan batasan data
```

Tabel pada halaman harus dapat digeser horizontal di layar kecil. Grafik memiliki tabel ringkas atau daftar angka sebagai alternatif, sehingga informasi tidak hanya bergantung pada warna atau interaksi grafik.

## Keputusan Fase 0 — Definisi metrik

**Status: selesai; definisi status diperbarui setelah konfirmasi klinik pada 30/08/2026.** Definisi di bagian ini menjadi kontrak untuk implementasi dashboard. Perubahan berikutnya harus dicatat sebagai perubahan versi metrik agar angka antarrilis tetap dapat dijelaskan.

### Populasi dan periode analisis

| Aspek | Keputusan |
| --- | --- |
| Satuan waktu utama | `examined_on` (tanggal pemeriksaan), bersifat inklusif untuk tanggal awal dan akhir |
| Zona waktu tampilan | Asia/Makassar; timestamp teknis database yang UTC dikonversi hanya ketika perlu ditampilkan |
| Periode bawaan | Bulan kalender berjalan: tanggal 1 sampai hari ini |
| Pemeriksaan yang masuk metrik hasil | Seluruh status tersimpan (`draft`, `completed`, dan `finalized`) selama pasien aktif; klinik mengonfirmasi data legacy berstatus `draft` sebagai hasil nyata yang boleh dianalisis |
| Pemeriksaan draft | Data legacy berstatus `draft` tetap masuk analisis. Status `draft` masih tersedia sebagai filter tersendiri dan perlu ditinjau pada modul kualitas data bila ada draft baru yang belum terverifikasi |
| Pasien arsip | Dikecualikan dari seluruh perhitungan bawaan dan ekspor, sama seperti Rekap |
| Pasien terhapus permanen | Tidak dapat masuk karena data pemeriksaannya memang tidak lagi tersedia |
| Nilai kosong | Tidak dihitung sebagai hasil, tidak dihitung normal, dan tidak dihitung abnormal |
| Nilai nol (`0`) | Tetap dihitung sebagai hasil yang sah bila tersimpan sebagai nilai numerik |

### Aturan penyatuan hasil parameter

Satu hasil parameter agregat didefinisikan sebagai satu pasangan **pemeriksaan × kode parameter**. Bila pemeriksaan yang sama memuat parameter yang sama dari sumber modern dan legacy, hasil modern diprioritaskan karena menyimpan snapshot kode, label, dan satuan yang lebih eksplisit.

| Kondisi | Perlakuan |
| --- | --- |
| Hasil panel modern saja | Dihitung satu kali |
| Hasil legacy saja | Dihitung satu kali |
| Kode sama pada sumber modern dan legacy untuk pemeriksaan sama | Modern dipakai, legacy tidak dihitung ulang |
| Kode parameter kosong/tidak dikenal | Tidak masuk analitik parameter; dicatat sebagai isu kualitas data bila perlu |
| Hasil teks/kualitatif | Dihitung sebagai hasil bila nilainya tidak kosong |

### Definisi KPI utama

| KPI yang ditampilkan | Numerator | Denominator | Definisi akhir |
| --- | --- | --- | --- |
| Total pemeriksaan | — | — | Jumlah pemeriksaan aktif dengan status tersimpan (`draft`, `completed`, atau `finalized`) yang lolos filter |
| Pasien unik | — | — | Jumlah `patient_id` berbeda pada kumpulan pemeriksaan yang sama |
| Hasil abnormal | Jumlah hasil parameter berinterpretasi `low`, `high`, atau `abnormal` | — | Jumlah hasil yang dinilai di luar nilai rujukan aktif yang cocok |
| Persentase abnormal | Hasil abnormal | Hasil yang berhasil dinilai | `hasil abnormal / (normal + low + high + abnormal) × 100`; hasil tanpa rujukan atau nilai tidak valid tidak masuk denominator |
| Pemeriksaan memiliki hasil | Pemeriksaan dengan minimal satu hasil parameter tidak kosong | Total pemeriksaan | `pemeriksaan berisi hasil / total pemeriksaan × 100`; ini menggantikan istilah “hasil terisi” agar tidak menyiratkan seluruh panel telah lengkap |

Kartu keempat dashboard diberi label **Pemeriksaan memiliki hasil**, bukan “kelengkapan hasil”. Kelengkapan panel yang sebenarnya hanya boleh dihitung bila daftar parameter yang diharapkan untuk suatu panel tersedia dan konsisten; indikator tersebut ditempatkan pada modul kualitas data, bukan KPI utama MVP.

### Definisi hasil abnormal dan belum dinilai

| Kategori | Definisi |
| --- | --- |
| Normal | Nilai berada dalam batas atau daftar nilai normal dari aturan rujukan aktif yang cocok |
| Rendah | Nilai numerik berada di bawah batas bawah aktif, dengan memperhatikan inklusivitas batas |
| Tinggi | Nilai numerik berada di atas batas atas aktif, dengan memperhatikan inklusivitas batas |
| Abnormal | Nilai kualitatif tidak termasuk nilai normal aktif |
| Belum dinilai | Tidak ada nilai rujukan aktif yang cocok, nilai tidak dapat diinterpretasi, atau identitas/usia yang diperlukan tidak tersedia |

Nilai rujukan aktif saat ini dipakai untuk seluruh riwayat, sama seperti halaman hasil kumulatif. Angka abnormal harus selalu disertai catatan metode tersebut di halaman Analisis.

### Definisi pemakaian layanan

| Metrik | Definisi |
| --- | --- |
| Pemakaian jenis pemeriksaan | Jumlah pemeriksaan yang memiliki `examination_type` tertentu |
| Pemakaian kategori/panel | Jumlah pasangan unik pemeriksaan × kategori yang mempunyai minimal satu hasil pada kategori tersebut |
| Pemakaian parameter | Jumlah pasangan unik pemeriksaan × kode parameter yang memiliki nilai tidak kosong |
| Persentase pemakaian parameter | Pemakaian parameter dibagi total pemeriksaan dalam filter × 100; satu pemeriksaan dapat menyumbang pada banyak parameter |

Karena satu pemeriksaan dapat mencakup lebih dari satu kategori dan parameter, jumlah pemakaian kategori/parameter **tidak** harus sama dengan total pemeriksaan. Keterangan ini ditampilkan di dekat tabel pemakaian.

### Definisi demografi dan asuransi

| Metrik | Definisi |
| --- | --- |
| Usia pasien | Usia penuh pada tanggal pemeriksaan terbaru pasien di dalam periode filter |
| Kelompok usia/jenis kelamin | Setiap pasien unik ditempatkan satu kali menurut pemeriksaan terbarunya dalam periode, agar total kategori sama dengan KPI Pasien unik |
| Jenis kelamin kosong/tidak valid | Kategori `Tidak diketahui` |
| Asuransi/penjamin | Nilai asuransi yang tersimpan pada profil pasien saat dashboard dijalankan |
| Asuransi kosong | Kategori `Belum diisi`; tidak disamakan dengan Umum |

Asuransi pada model data saat ini berada di profil pasien, bukan snapshot per pemeriksaan. Karena itu analisis asuransi MVP menjelaskan **profil penjamin pasien saat ini**, bukan bukti penjamin yang digunakan pada setiap kunjungan historis. Analisis kunjungan berdasarkan penjamin historis memerlukan snapshot asuransi pada pemeriksaan dan menjadi pekerjaan lanjutan.

### Definisi kualitas data

| Indikator | Definisi akhir |
| --- | --- |
| Pemeriksaan draft | Pemeriksaan aktif berstatus `draft` yang tanggalnya berada dalam filter |
| Pemeriksaan tanpa hasil | Pemeriksaan `completed`/`finalized` yang tidak memiliki hasil parameter tidak kosong dari sumber mana pun |
| Panel belum lengkap | Hanya dihitung untuk panel modern yang memiliki daftar parameter harapan; parameter kosong dibanding daftar tersebut |
| Identitas belum lengkap | Pasien tanpa tanggal lahir, jenis kelamin, atau asuransi pada pemeriksaan terfilter; setiap kekosongan dilaporkan terpisah |
| Parameter tanpa rujukan aktif | Hasil tidak kosong yang tidak dapat dipasangkan dengan aturan rujukan aktif yang cocok; tidak berarti hasil normal |

## KPI ringkasan

Empat kartu utama pada MVP:

| KPI | Definisi | Catatan |
| --- | --- | --- |
| Total pemeriksaan | Jumlah pemeriksaan sesuai filter dan status yang dipilih | Satu pemeriksaan = satu nomor lab/record pemeriksaan |
| Pasien unik | Jumlah `patient_id` berbeda yang memiliki pemeriksaan sesuai filter | Satu pasien dihitung satu kali pada periode yang sama |
| Hasil abnormal | Jumlah hasil parameter yang dinilai rendah, tinggi, atau abnormal | Hanya hasil dengan nilai rujukan aktif yang cocok dan nilai valid |
| Pemeriksaan memiliki hasil | Persentase pemeriksaan yang memiliki minimal satu hasil tidak kosong | Tidak menyatakan seluruh panel telah lengkap |

Kartu abnormal harus memuat keterangan singkat, misalnya: *Menggunakan nilai rujukan aktif saat ini; bukan diagnosis.*

## Modul analisis MVP

### 1. Tren operasional pemeriksaan

**Tujuan:** memahami beban kerja dan perubahan jumlah pemeriksaan.

Tampilan:

- Grafik garis jumlah pemeriksaan per hari untuk periode sampai 31 hari.
- Grafik batang per minggu atau bulan untuk periode lebih panjang.
- Tabel ringkas: tanggal/periode, total pemeriksaan, pasien unik.

Aturan pengelompokan otomatis:

| Panjang periode | Kelompok waktu |
| --- | --- |
| 1–31 hari | Hari |
| 32–180 hari | Minggu |
| Lebih dari 180 hari | Bulan |

### 2. Pemeriksaan dan panel paling sering digunakan

**Tujuan:** mengetahui pemakaian layanan dan parameter yang paling dominan.

Tampilan:

- Top 10 jenis pemeriksaan.
- Top 10 kategori/panel.
- Top 10 parameter yang memiliki nilai hasil.
- Tabel lengkap berisi jumlah, persentase dari total, dan perubahan dibanding periode sebelumnya bila tahap pembanding sudah tersedia.

Data harus menggabungkan hasil legacy dan hasil panel baru berdasarkan **kode parameter**, bukan nama tampilan, untuk menghindari perhitungan ganda.

### 3. Ringkasan hasil abnormal

**Tujuan:** membantu analis kesehatan memantau parameter yang paling sering berada di luar rujukan.

Tampilan utama berupa tabel:

| Parameter | Kategori | Jumlah hasil | Rendah | Tinggi/abnormal | Persentase abnormal |
| --- | --- | ---: | ---: | ---: | ---: |

Aturan:

- Parameter numerik: rendah/tinggi ditentukan terhadap nilai rujukan aktif yang cocok dengan usia dan jenis kelamin pasien pada tanggal pemeriksaan.
- Parameter kualitatif: abnormal bila nilainya tidak termasuk daftar nilai normal pada aturan aktif.
- Hasil tanpa aturan aktif yang cocok tidak dihitung sebagai normal maupun abnormal; tampilkan sebagai **Belum dinilai** dalam detail bila diperlukan.
- Hasil kosong tidak dihitung sebagai hasil abnormal.
- Persentase abnormal = `(rendah + tinggi/abnormal) / hasil yang berhasil dinilai × 100`.

Warna merah hanya sebagai bantuan visual. Tabel wajib tetap menuliskan angka rendah, tinggi, dan abnormal secara eksplisit.

### 4. Demografi pasien

**Tujuan:** memahami karakteristik pasien yang diperiksa, bukan menetapkan diagnosis populasi.

Tampilan:

- Jumlah pasien unik menurut kelompok usia.
- Jumlah pasien unik menurut jenis kelamin.
- Tabel silang sederhana usia × jenis kelamin.
- Opsional tahap berikutnya: wilayah/alamat, hanya bila format alamat sudah distandardisasi.

Pasien yang tidak mempunyai tanggal lahir atau jenis kelamin tetap masuk dalam kategori **Tidak diketahui** agar total tidak hilang.

### 5. Asuransi/penjamin

**Tujuan:** mengetahui penggunaan layanan menurut sumber penjaminan.

Tampilan:

- Jumlah pemeriksaan menurut asuransi/penjamin.
- Jumlah pasien unik menurut asuransi/penjamin.
- Tren per bulan menurut asuransi untuk rentang lebih dari satu bulan.
- Tabel yang menampilkan nilai kosong sebagai **Belum diisi**; jangan otomatis mengubahnya menjadi Umum.

### 6. Kualitas dan kelengkapan data

**Tujuan:** menemukan area operasional yang perlu diperbaiki tanpa mengubah data dari dashboard.

Indikator awal:

| Indikator | Definisi |
| --- | --- |
| Pemeriksaan draft | Jumlah pemeriksaan berstatus draft pada periode terpilih |
| Hasil belum lengkap | Record panel modern yang memiliki parameter kosong, bila definisi parameter yang diharapkan tersedia |
| Identitas belum lengkap | Pemeriksaan dengan pasien tanpa tanggal lahir, jenis kelamin, atau asuransi |
| Parameter tanpa rujukan aktif | Parameter hasil yang tidak mempunyai aturan aktif yang cocok |

Indikator kualitas harus bersifat informasi. Aksi perbaikan tetap dilakukan dari halaman pasien, pemeriksaan, atau nilai rujukan yang sesuai.

## Analisis per pasien

Halaman **Hasil pemeriksaan kumulatif** yang sudah tersedia tetap menjadi analisis per pasien. Menu Analisis tidak menggantikannya.

Pembagian tanggung jawab:

| Kebutuhan | Halaman |
| --- | --- |
| Membandingkan Hemoglobin pasien A dari beberapa tanggal | Hasil kumulatif pasien |
| Mengetahui parameter abnormal terbanyak bulan ini | Analisis laboratorium |
| Membuka riwayat dan identitas pasien tertentu | Detail pasien / Arsip pasien |

## Arsitektur backend

### Repository baru

Tambahkan `app/AnalyticsRepository.php` dengan kontrak awal:

```php
AnalyticsRepository::dashboard(array $filters): array
```

Keluaran repository dibagi per bagian, misalnya:

```php
[
    'filters' => [...],
    'kpis' => [...],
    'trend' => [...],
    'utilization' => [...],
    'abnormal_summary' => [...],
    'demographics' => [...],
    'insurance' => [...],
    'data_quality' => [...],
    'generated_at' => '...',
]
```

Repository bertanggung jawab untuk:

1. Menormalisasi dan memvalidasi filter.
2. Menerapkan batas data pasien aktif (`patients.archived_at IS NULL`) secara bawaan.
3. Menentukan daftar pemeriksaan dasar yang memenuhi filter.
4. Menggabungkan hasil legacy dan panel modern tanpa menghitung parameter ganda.
5. Menilai hasil terhadap nilai rujukan aktif dengan logika yang sama seperti halaman kumulatif.
6. Mengembalikan angka agregat siap pakai untuk view.

### Satu sumber filter

Seluruh modul harus berangkat dari kumpulan pemeriksaan dasar yang sama. Jangan membuat setiap grafik memiliki filter SQL yang berbeda-beda karena berisiko menghasilkan total yang tidak konsisten.

Alur yang disarankan:

```text
Request filter
    -> normalisasi filter
    -> query pemeriksaan aktif yang sesuai
    -> kumpulan patient_id dan examination_id
    -> agregasi KPI / tren / demografi / asuransi
    -> normalisasi hasil parameter legacy + modern
    -> agregasi abnormal dan kualitas data
    -> view dashboard
```

### Penilaian abnormal

Gunakan kembali logika evaluasi nilai rujukan yang telah ada, bukan menulis ulang aturan perbandingan angka di repository analitik. Bila diperlukan, ekstrak bagian pemilihan nilai rujukan aktif menjadi layanan kecil yang dapat digunakan bersama oleh:

- Finalisasi pemeriksaan.
- Hasil pemeriksaan kumulatif.
- Dashboard Analisis.

Hal ini menjaga definisi rendah/tinggi/abnormal tetap sama di seluruh aplikasi.

## Kinerja dan query

MVP diharapkan masih aman untuk jumlah data yang ada saat ini, tetapi implementasi harus siap bertumbuh.

Prinsip query:

- Agregasi dilakukan di SQL sebanyak mungkin untuk KPI, tren, demografi, dan asuransi.
- Hasil parameter dinormalisasi hanya untuk `examination_id` yang lolos filter, bukan seluruh database.
- Hindari query per pemeriksaan atau per parameter (N+1 query).
- Batasi tabel detail ke top 10 atau top 20 secara bawaan; sediakan tombol lihat seluruh bila diperlukan.

Indeks yang perlu ditinjau setelah implementasi:

| Tabel | Indeks yang relevan |
| --- | --- |
| `examinations` | `(examined_on, status)`, `patient_id`, `examination_type` |
| `patients` | `archived_at`, `insurance_type`, `sex`, `birth_date` |
| `examination_parameter_results` | `examination_id`, `parameter_code_snapshot` |
| `examination_result_flags` | `(examination_id, test_code)` |
| `reference_ranges` | `(test_code, is_active, approval_status, sex)` |

Indeks baru hanya ditambahkan setelah memeriksa indeks yang sudah tersedia dan profil query nyata, agar tidak membuat migrasi yang tidak diperlukan.

## Frontend dan visualisasi

Gunakan komponen HTML/CSS yang ringan. Grafik dapat dibuat dengan library kecil yang dibundel lokal atau dengan SVG/Canvas sederhana; keputusan library dibuat sebelum fase implementasi frontend.

Kriteria grafik:

- Tidak bergantung hanya pada warna.
- Memiliki label, tooltip, dan tabel/angka ringkas pendamping.
- Warna konsisten: biru untuk volume, hijau untuk hasil dalam rujukan, merah untuk abnormal, abu-abu untuk belum dinilai.
- Tidak menggunakan pie/donut bila kategori lebih dari lima; gunakan batang horizontal agar label tetap terbaca.
- Menampilkan keadaan kosong dengan pesan yang jelas, bukan grafik kosong.

Pada layar kecil:

- Kartu KPI menjadi satu kolom atau dua kolom.
- Grafik memakai lebar penuh dan boleh digeser horizontal bila perlu.
- Tabel detail dapat digeser ke samping.
- Filter ditumpuk vertikal dengan tombol Terapkan tetap mudah dijangkau.

## Privasi dan keamanan

1. Akses dibatasi sesuai peran.
2. Dashboard tidak menampilkan NIK, nomor telepon, alamat lengkap, atau daftar nama pasien secara bawaan.
3. Tabel agregat dengan angka kecil tetap boleh ditampilkan untuk kebutuhan operasional internal; bila aplikasi dipakai untuk laporan eksternal, terapkan ambang penyembunyian sel kecil setelah kebijakan klinik disetujui.
4. Ekspor agregat harus dicatat di audit log, seperti ekspor Rekap saat ini.
5. Filter dan parameter URL divalidasi; tidak boleh langsung disambungkan ke SQL.
6. Semua tampilan tetap melakukan escaping HTML melalui helper view yang sudah digunakan aplikasi.

## Tahapan implementasi

### Fase 0 — Finalisasi definisi metrik

**Status: selesai pada 30/08/2026; definisi status diperbarui setelah konfirmasi klinik pada 30/08/2026.**

Keluaran yang sudah ditetapkan:

- Definisi tertulis KPI, numerator, denominator, nilai kosong, dan nilai nol pada bagian **Keputusan Fase 0**.
- Seluruh status tersimpan (`draft`, `completed`, dan `finalized`) masuk dashboard. Klinik mengonfirmasi 2.517 record legacy berstatus `draft` sebagai data nyata yang dapat dianalisis; filter khusus `draft` tetap tersedia untuk peninjauan.
- Rentang bawaan adalah bulan kalender berjalan.
- Pasien arsip dikecualikan dari dashboard dan ekspor, mengikuti Rekap.
- Hasil modern diprioritaskan apabila kode parameter yang sama juga ada pada sumber legacy.
- Demografi memakai pemeriksaan terakhir setiap pasien pada periode terpilih agar satu pasien tidak dihitung di beberapa kelompok.
- Asuransi diperlakukan sebagai profil pasien saat ini, bukan snapshot kunjungan historis.

### Fase 1 — Fondasi data dan filter

**Status: implementasi selesai pada 30/08/2026; verifikasi browser dengan sesi admin masih menunggu.**

1. [x] Tambahkan `AnalyticsRepository`.
2. [x] Buat normalisasi filter dan query pemeriksaan dasar.
3. [x] Tambahkan rute `/analytics` serta proteksi `admin`.
4. [x] Tambahkan item navigasi Analisis untuk admin.
5. [x] Buat halaman filter dan keadaan kosong.
6. [x] Uji filter tanggal, jenis pemeriksaan, asuransi, jenis kelamin, usia, dan status.

Kriteria selesai:

- [x] Filter memengaruhi satu angka total yang benar.
- [x] Pasien arsip tidak ikut hitung.
- [x] Tidak ada query dengan input pengguna yang disisipkan langsung.

Catatan implementasi Fase 1:

- `app/AnalyticsRepository.php` menjadi satu pintu normalisasi filter dan penyusunan kumpulan pemeriksaan dasar. Semua agregasi fase berikutnya wajib memakai klausa dasar yang sama agar total antarmodul tetap konsisten.
- Periode bawaan adalah tanggal pertama bulan berjalan sampai hari ini. Kedua tanggal inklusif dan format selain `YYYY-MM-DD` ditolak.
- Populasi bawaan hanya pasien aktif dengan pemeriksaan berstatus tersimpan (`draft`, `completed`, atau `finalized`). Status dapat dipersempit menjadi `draft`, `completed`, atau `finalized` melalui filter.
- Pilihan filter dinormalisasi terhadap daftar nilai yang diizinkan. Nilai tak dikenal, array pada parameter skalar, rentang tanggal terbalik, dan pola input menyerupai injeksi ditolak sebelum query dijalankan.
- Filter kategori memeriksa keberadaan hasil yang benar-benar terisi pada panel tersebut. Hasil panel modern dan tabel legacy didukung; nilai numerik `0` tetap dianggap hasil sah.
- Asuransi menggunakan nilai profil pasien saat ini. Nilai kosong disediakan sebagai pilihan eksplisit **Belum diisi** dan tidak digabungkan dengan Umum.
- Usia dihitung sebagai usia ulang tahun penuh pada `examined_on`. Tanggal lahir kosong atau berada setelah tanggal pemeriksaan masuk kategori **Tidak diketahui**.
- Rute `GET /analytics` hanya dapat diakses peran `admin`; peran `perawat` menerima HTTP 403 dan pengguna tanpa sesi diarahkan ke halaman masuk.
- Halaman awal menampilkan seluruh filter global, satu angka pemeriksaan sesuai filter untuk memvalidasi fondasi, pesan kesalahan filter yang aman, serta keadaan kosong. KPI dan grafik lengkap tetap dikerjakan pada Fase 2. Nilai bawaan akan tetap nol bila periode berjalan memang tidak memiliki record; gunakan filter tanggal untuk melihat periode historis.
- Tata letak filter menggunakan empat kolom pada desktop, dua kolom pada tablet, dan satu kolom pada ponsel tanpa overflow horizontal.

Verifikasi Fase 1:

- Seluruh berkas PHP lulus `php -l`.
- `tests/analytics_repository_integration.php` lulus dengan tabel sementara sehingga data klinik tidak diubah. Skenario mencakup tanggal inklusif, jenis pemeriksaan, asuransi terisi/kosong, jenis kelamin, kelompok usia, status, pasien arsip, panel modern, panel legacy, dan nilai numerik nol.
- Pengujian HTTP lokal menghasilkan `200` untuk admin, `403` untuk perawat, dan pesan validasi aman untuk filter URL tidak dikenal.
- Smoke test rentang historis `1990-01-01`–`2026-08-31` pada database lokal menampilkan 2.517 pemeriksaan legacy yang sebelumnya berstatus `draft`, sesuai konfirmasi klinik. Periode bawaan bulan berjalan tetap dapat bernilai nol karena data terbaru berada pada Juni 2026.
- Pemeriksaan browser pada lebar 1280 px dan 390 px memastikan label, kontrol, keadaan kosong, dan navigasi dapat dibaca tanpa overflow horizontal.

### Fase 2 — Dashboard operasional dan demografi

**Status: selesai pada 30/08/2026.**

1. [x] Implementasi kartu Total pemeriksaan dan Pasien unik.
2. [x] Implementasi tren waktu.
3. [x] Implementasi penggunaan jenis pemeriksaan/panel.
4. [x] Implementasi sebaran asuransi.
5. [x] Implementasi usia dan jenis kelamin.
6. [x] Tambahkan tabel angka pendamping setiap grafik.

Kriteria selesai:

- [x] Jumlah pada grafik konsisten dengan KPI sesuai filter yang sama.
- [x] Nilai kosong ditampilkan sebagai kategori yang jelas.
- [ ] Verifikasi browser desktop dan mobile dengan sesi admin.

Catatan implementasi Fase 2:

- Semua agregasi memakai klausa dasar `AnalyticsRepository` yang sama dengan KPI, sehingga periode, pasien arsip, status, dan filter lain berlaku konsisten pada seluruh dashboard.
- Tren otomatis dikelompokkan per hari hingga 31 hari, per minggu pada 32–180 hari, dan per bulan di atas 180 hari.
- Pemakaian kategori menghitung pemeriksaan yang memiliki minimal satu hasil pada kategori tersebut, mencakup hasil panel modern maupun tabel legacy. Satu pemeriksaan dapat masuk lebih dari satu kategori.
- Demografi hanya menghitung pasien unik dan menempatkan setiap pasien pada usia serta jenis kelamin dari pemeriksaan terakhirnya dalam periode terfilter.
- Asuransi menampilkan profil penjamin pasien saat dashboard dibuka; kategori kosong diberi label **Belum diisi**.
- Grafik batang CSS selalu disertai tabel angka untuk aksesibilitas dan penggunaan layar kecil.

Verifikasi Fase 2:

- `app/AnalyticsRepository.php`, `app/views/analytics/index.php`, `public/index.php`, dan pengujian integrasi lulus `php -l`.
- `php tests/analytics_repository_integration.php` lulus terhadap MariaDB lokal dengan tabel sementara. Skenario mencakup KPI/tren, kategori modern dan legacy, asuransi kosong, demografi, serta aturan pengelompokan mingguan.
- Browser lokal mencapai halaman masuk, tetapi tidak ada sesi admin aktif untuk memeriksa tampilan dashboard tanpa memasukkan kredensial.

### Fase 3 — Hasil abnormal dan kualitas data

**Status: implementasi selesai pada 30/08/2026; verifikasi browser dengan sesi admin masih menunggu.**

1. [x] Normalisasi hasil legacy dan modern untuk kumpulan pemeriksaan terfilter.
2. [x] Gunakan evaluasi nilai rujukan aktif bersama.
3. [x] Tambahkan tabel ringkasan rendah/tinggi/abnormal per parameter.
4. [x] Tambahkan indikator parameter tanpa rujukan aktif.
5. [x] Tambahkan indikator draft dan kelengkapan data.
6. [x] Bandingkan sampel hasil dashboard dengan halaman hasil kumulatif pasien.

Kriteria selesai:

- [x] Hasil abnormal dashboard konsisten dengan penanda hasil kumulatif untuk sampel yang sama.
- [x] Hasil tanpa rujukan tidak dihitung sebagai normal.
- [x] Parameter legacy dan modern tidak dihitung ganda.

Catatan implementasi Fase 3:

- Hasil modern dan legacy dinormalisasi menjadi pasangan pemeriksaan × kode parameter. Jika kode yang sama tersedia dari kedua sumber pada pemeriksaan yang sama, hasil modern dipakai.
- `HistoricalReferenceEvaluator::annotatePopulation()` memperluas evaluator historis yang juga dipakai halaman kumulatif. Pemilihan aturan aktif, pencocokan jenis kelamin/usia, dan interpretasi nilai tetap memakai `ReferenceEvaluator` yang sama.
- KPI **Hasil abnormal** memakai denominator hasil yang benar-benar berhasil dinilai. Hasil tanpa aturan aktif atau nilai yang tidak dapat diinterpretasi masuk **Belum dinilai**, bukan normal.
- KPI **Pemeriksaan memiliki hasil** menghitung pemeriksaan dengan minimal satu hasil tidak kosong dari sumber modern atau legacy.
- Ringkasan parameter menampilkan jumlah hasil, rendah, tinggi/abnormal, belum dinilai, dan persentase abnormal.
- Indikator kualitas mencakup draft, completed/finalized tanpa hasil, panel modern belum lengkap, identitas kosong, dan parameter tanpa rujukan aktif. Seluruh indikator bersifat baca-saja.

Verifikasi Fase 3:

- Seluruh berkas PHP yang diubah lulus `php -l`.
- `php tests/analytics_repository_integration.php` lulus dengan tabel sementara. Skenario mencakup prioritas modern atas legacy, nilai rendah/tinggi/kualitatif, hasil tanpa rujukan, denominator abnormal, panel belum lengkap, pemeriksaan tanpa hasil, identitas kosong, dan filter kategori.
- Sampel HGB pada fixture dinilai `low` baik oleh dashboard maupun `CumulativeResultRepository`, membuktikan konsistensi evaluator pada aturan aktif yang sama.
- Smoke test baca-saja pada 2.517 pemeriksaan lokal selesai sekitar 0,4 detik tanpa menampilkan identitas pasien.

### Fase 4 — Penyempurnaan dan ekspor agregat

**Status: selesai pada 31/08/2026.**

1. [x] Tambahkan ekspor Excel/CSV khusus agregat sesuai filter.
2. [x] Audit log untuk ekspor.
3. [x] Optimasi indeks berdasarkan data dan query nyata.
4. [x] Pembanding periode sebelumnya dinyatakan opsional dan ditunda hingga kebutuhan operasionalnya ditetapkan.
5. [x] Dokumentasi pengguna dan definisi metrik di halaman bantuan.

Catatan implementasi Fase 4:

- Tombol **Ekspor CSV** dan **Ekspor Excel** pada Analisis memuat angka agregat yang sama dengan dashboard serta filter yang sedang aktif. Tidak ada nama, nomor rekam medis, NIK, alamat, atau identitas pasien dalam kedua format tersebut.
- Setiap ekspor mencatat event audit `analytics.exported`, format, filter ternormalisasi, ringkasan angka, dan jumlah baris agregat yang diekspor.
- Migrasi `022_analytics_filter_indexes.sql` menambahkan indeks gabungan untuk filter tanggal/status/jenis pemeriksaan serta profil pasien aktif/penjamin/jenis kelamin. Keduanya ditentukan dari klausa query Analytics yang digunakan pada database lokal.
- Bantuan definisi metrik tersedia di `/analytics/help` dan dapat dibuka dari tombol **Bantuan metrik** pada dashboard.
- Pembanding periode sebelumnya tidak ditampilkan lebih dahulu agar tidak menambah interpretasi baru tanpa definisi periode pembanding yang disetujui klinik.

Verifikasi Fase 4:

- Lint seluruh berkas PHP Fase 4 lulus.
- `php tests/analytics_repository_integration.php` membandingkan total, penjamin, serta demografi ekspor dengan angka dashboard dan lulus.
- CSV agregat berhasil dibuat tanpa peringatan PHP; XLSX agregat lulus pemeriksaan struktur ZIP/XML.
- Audit ekspor diuji memakai tabel sementara sehingga tidak menulis jejak uji ke audit log operasional.

## Pengujian yang diperlukan

### Validasi data dan filter

- Rentang tanggal kosong, satu hari, satu bulan, lintas tahun, dan tanggal terbalik.
- Semua jenis pemeriksaan serta satu jenis pemeriksaan tertentu.
- Setiap jenis asuransi dan data asuransi kosong.
- Laki-laki, perempuan, dan identitas yang tidak lengkap.
- Masing-masing kelompok usia dan tanggal lahir kosong.
- Pemeriksaan draft, completed, finalized, dan pasien arsip.

### Validasi hasil parameter

- Pemeriksaan legacy saja.
- Panel modern saja.
- Riwayat gabungan legacy dan modern.
- Parameter numerik normal, rendah, tinggi, nilai nol, serta nilai teks/kualitatif.
- Parameter tanpa nilai rujukan aktif.
- Nilai rujukan berbeda untuk laki-laki/perempuan dan usia tertentu.
- Parameter yang sama tersimpan dalam sumber legacy dan modern; hanya dihitung satu kali sesuai aturan prioritas.

### Validasi tampilan

- Dashboard tanpa data pada filter tertentu.
- Dataset kecil dan dataset besar.
- Desktop, tablet, dan ponsel.
- Tabel panjang dan label parameter panjang.
- Pembaca layar dasar: judul grafik, tabel pendamping, fokus keyboard pada filter.

### Validasi konsistensi

- Total pemeriksaan dashboard = total Rekap dengan filter setara dan pasien aktif.
- Jumlah per kategori jika dijumlahkan = total yang relevan, dengan perbedaan dijelaskan bila satu pemeriksaan memiliki lebih dari satu panel.
- Sampel abnormal dashboard = hasil kumulatif pasien pada nilai rujukan aktif yang sama.
- Ekspor agregat = angka yang terlihat pada dashboard untuk filter yang sama.

## Kriteria penerimaan MVP

MVP dianggap siap digunakan bila:

- Menu Analisis hanya dapat diakses oleh admin.
- Filter global bekerja dan konsisten pada seluruh bagian.
- Pasien arsip tidak masuk perhitungan bawaan.
- Kartu total pemeriksaan dan pasien unik benar.
- Tren waktu, penggunaan pemeriksaan/panel, asuransi, dan demografi tampil dengan data nyata.
- Tabel hasil abnormal menjelaskan numerator, denominator, dan hasil belum dinilai.
- Tidak ada identitas pasien sensitif pada dashboard agregat.
- Halaman dapat dipakai pada desktop dan perangkat kecil.
- Semua skenario pengujian inti di atas lulus.

## Di luar cakupan MVP

- Diagnosis otomatis atau rekomendasi klinis.
- Grafik tren personal tambahan di luar halaman kumulatif pasien.
- Analisis biaya, pendapatan, klaim, dan pembayaran.
- Integrasi alat laboratorium/QC/reagen.
- Pelaporan epidemiologi eksternal.
- Peran `analis_kesehatan` baru dan pengaturan izin granular.

## Keputusan yang sudah ditetapkan untuk rencana ini

- Nama menu: **Analisis laboratorium**.
- Fokus awal: operasional, pemakaian parameter, hasil abnormal, demografi, asuransi, dan kualitas data.
- Halaman kumulatif pasien tetap menjadi alat perbandingan per pasien.
- Analitik populasi bersifat agregat dan baca-saja.
- Nilai rujukan aktif saat ini digunakan untuk ringkasan abnormal agar konsisten dengan halaman kumulatif.
- Pasien yang diarsipkan dikecualikan dari perhitungan bawaan, sama seperti Rekap.
