# Validasi Konfigurasi Panel Pemeriksaan — Fase 1

Status: **selesai — 29/08/2026.** Dokumen ini adalah hasil validasi dari jawaban klinik dan screenshot referensi. Dokumen ini belum menambah atau mengaktifkan nilai rujukan klinis.

## Keputusan operasional

| Topik | Keputusan yang digunakan |
| --- | --- |
| Identitas pasien | Nomor RM adalah identitas unik pasien; pasien tidak dikunci pada satu kategori pemeriksaan. |
| Satu kunjungan | Satu nomor lab dapat memiliki satu atau lebih panel, misalnya DL + Urine Lengkap + Kimia Klinik. |
| Parameter wajib | Tidak ada parameter yang wajib diisi sebelum simpan maupun cetak. Ini mendukung pemeriksaan parsial dan menghindari hambatan operasional. |
| Nilai kosong | Pada form, parameter tetap tersedia. Pada cetakan, nilai kosong ditulis `—`. |
| Imunoserologi | Seluruh parameter Imunoserologi selalu muncul pada form dan selalu tercetak, apa pun panel yang dipilih. |
| Panel tambahan | Dibangun pada implementasi awal sebagai panel opsional; tidak ditampilkan kecuali dipilih. |
| Hak konfigurasi | Admin dapat mengelola katalog dan mengaktifkan nilai rujukan setelah peninjauan klinis. |

## Katalog panel tervalidasi

Kode panel digunakan oleh sistem; nama panel digunakan pada layar dan cetakan.

| Kode | Nama yang tampil | Parameter | Satuan / jenis nilai |
| --- | --- | --- | --- |
| `hematology_complete` | DL / Darah Lengkap / Hematologi | Hemoglobin, Leukosit, Trombosit, Eritrosit, Hematokrit; LYMPH%, MID%, GRAN%; MCV, MCH, MCHC; Eosinofil, Basofil, Neutrofil, Limfosit, Monosit | HGB `g/dL`; Leukosit & Trombosit `sel/mm³`; Eritrosit `juta/µL`; Hematokrit & diferensial `%`; MCV `fL`; MCH `pg`; MCHC `g/dL`. Semua numerik. |
| `urinalysis_complete` | Urine Lengkap / Urinalisis | Warna, kejernihan, berat jenis, pH, protein urine, glukosa/reduksi, urobilinogen, bilirubin, darah samar, keton, nitrit, lekosit, eritrosit sedimen, lekosit sedimen, squamos/sel epitel, silinder, kristal, bakteri | Warna/kejernihan/nitrit/silinder berupa teks; berat jenis & pH numerik; glukosa-reduksi, urobilinogen, bilirubin, darah samar, keton `mg/dL`; lekosit `sel/µL`; eritrosit & lekosit sedimen `/LPB`; squamos `/LPB`; kristal & bakteri `/LPK`. |
| `clinical_chemistry` | Kimia Klinik | Gula Darah Sewaktu, Gula Darah Puasa, Gula Darah 2 Jam PP, Asam Urat, Kolesterol, Trigliserida, HDL Kolesterol, LDL Kolesterol, HbA1c, Ureum, Kreatinin, AST (SGOT), ALT (SGPT) | Umumnya numerik: glukosa, asam urat, lipid, ureum, kreatinin `mg/dL`; AST/ALT `U/L`; HbA1c `%` (bukan `mg/dL`). |
| `widal` | Widal / Pemeriksaan Terkait Demam Tifoid | *Salmonella typhi* O, AO, BO, H | Teks/kualitatif atau titer, sesuai hasil alat/lab. |
| `immunoserology` | Imunoserologi | Golongan darah, rhesus, HBsAg Eclia, HBsAg Rapid, Anti-HIV Rapid, Sifilis Rapid, DHF Dengue IgG, DHF Dengue IgM, NS1 Dengue, *Salmonella Typhi* IgM, Tes Kehamilan | Golongan darah dan rhesus berupa pilihan; lainnya teks/kualitatif. Selalu tersedia dan tercetak. |
| `additional_rare` | Pemeriksaan Tambahan | Malaria Rapid, PAN (*P. vivax*, *P. ovale*, MAL), PF (*P. falciparum*), Preparat BTA, Preparat Malaria | Teks/kualitatif. Panel ditampilkan hanya bila dipilih. |

Catatan: Kimia Klinik menggabungkan kelompok pada gambar 3 dan 4. Pada layar/cetak, kelompok boleh dipecah menjadi subbagian **Glukosa & Lipid** dan **Fungsi Ginjal & Enzim Hati**, tetapi tetap satu panel yang dipilih.

## Aturan tampilan dan cetak A4

1. Pengguna memilih satu atau beberapa panel setelah menekan **Pemeriksaan baru** dan mengisi nomor lab serta tanggal pemeriksaan.
2. Form hanya menampilkan panel yang dipilih, ditambah Imunoserologi. Panel tidak terpilih tidak muncul sama sekali.
3. Cetak A4 menampilkan identitas pasien, nomor lab, tanggal periksa dalam format **DD/MM/YYYY**, lalu parameter seluruh panel yang dipilih dan Imunoserologi.
4. Setiap parameter terpilih dicetak meskipun nilainya kosong; nilainya ditulis `—`. Ini menjaga bentuk cetakan tetap konsisten dan menunjukkan bahwa parameter tersebut termasuk dalam panel.
5. Bagian yang tidak dipilih tidak dicetak. Untuk dokumen lebih dari satu halaman, baris tabel tidak boleh terpotong dan identitas ringkas pasien perlu muncul di halaman lanjutan.
6. Area tanda tangan tetap berupa jabatan **Penanggung jawab** dan garis kosong; teks “Nama dan tanda tangan” tidak dicetak, sehingga petugas dapat menuliskan nama secara manual.
7. Kolom nilai rujukan hanya menampilkan konfigurasi aktif yang telah disetujui admin. Sampai diaktifkan, aplikasi menampilkan `—` atau “Belum dikonfigurasi”, bukan menebak nilai klinis.

## Batasan Fase 1

- Validasi ini menetapkan struktur dan pengalaman kerja, bukan keputusan diagnosis atau referensi klinis.
- Nilai rujukan dari screenshot hanya menjadi bahan konfigurasi. Nilai tersebut tidak boleh diseed atau diaktifkan tanpa peninjauan admin/klinik.
- Daftar parameter tambahan dapat diperluas oleh admin pada fase katalog tanpa mengubah alur pemeriksaan yang telah disetujui.

## Hasil Fase 2

Katalog panel dan parameter telah dibuat melalui migrasi `012_examination_panel_catalog.sql`, diterapkan pada database lokal, dan diverifikasi berisi 6 panel aktif serta 67 parameter aktif. Katalog ini menjadi dasar pilihan beberapa panel pada Fase 3, form dinamis, dan cetak A4 pada fase sesudahnya.
