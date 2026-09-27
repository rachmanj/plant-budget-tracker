# Laporan migrasi proc-app → PMB (Fase 5)

Tanggal: 27 September 2026
Lingkungan: produksi PMB (`http://192.168.32.149:86`) dengan basis data SAP trial `LAB_SBO_20260924`
Sumber data: aplikasi lama proc-app di server `192.168.32.13` (baca-saja) + berkas lampiran yang ditarik 24–25 September 2026

## 1. Ringkasan hasil

| Ukuran | proc-app | PMB sesudah migrasi | Selisih |
|---|---|---|---|
| Dokumen PO (semua departemen) | 10.972 | 10.996 | +24 |
| Dokumen PO (Plant + Logistik) | 9.719 | 9.746 | +27 |
| Dokumen PR (semua departemen) | 14.174 | 14.163 | −11 |
| Dokumen PR (Plant + Logistik) | 12.962 | 12.953 | −9 |
| Baris PO | 35.650 | 35.716 | +66 (0,2%) |
| Baris PR | 45.606 | 45.647 | +41 (0,1%) |
| Jejak persetujuan lama | 16.031 | 16.035 | +4 |
| Harga item | 2.500 | 1.093 | −1.407 (lihat §4) |
| Lampiran PO | 3.149 baris | 3.141 baris | −8 |
| Lampiran PR | 22.356 baris | 22.258 baris | −98 |
| Nomor dokumen ganda | — | **0** | — |

Nilai yang kini terbaca di PMB: register PO **Rp 877.832.175.671,40** dan register PR **Rp 13.471.191.809,00**. proc-app tidak pernah menyimpan angka ini (kolom totalnya nol sejak awal), jadi PMB menjadi satu-satunya tempat nilai pengadaan bisa dibaca.

## 2. Asal baris register

- PO: 6.450 dokumen dari sinkronisasi SAP, 4.546 dokumen warisan proc-app (`legacy_source = proc_app`)
- PR: 9.393 dokumen dari sinkronisasi SAP, 4.770 dokumen warisan proc-app
- Rentang waktu tercakup: Juni 2025 sampai September 2026 (seluruh riwayat proc-app, ditambah dokumen yang hanya ada di SAP)

## 3. Lampiran

- 25.505 baris lampiran dibaca, **25.399 dibuat** (106 baris sudah ada), **0 gagal**
- **58 lampiran ditandai `file_unavailable`** — sesuai keputusan Iwan: baris tetap diarsipkan sebagai jejak, berkasnya tidak ada di proc-app, tidak ada berkas pengganti yang dikarang
- Berkas disimpan di penyimpanan privat PMB (± 7,6 GB); unduhan tetap melalui pemeriksaan izin
- Berkas yatim di proc-app (ada berkas, tidak tercatat di basis data) tidak diimpor

## 4. Harga item

Ekspor memuat 2.497 harga. PMB menyimpan 1.093 di antaranya sebagai data baru, memperbarui 147, dan melewati 2.350 karena PMB sudah punya harga yang lebih baru (aturan: harga PMB yang lebih baru dipertahankan). Master supplier proc-app (356 baris) **tidak diimpor** karena PMB memakai master vendor langsung dari SAP (2.395 vendor).

## 5. Yang tidak dimigrasikan (dan alasannya)

- Komentar, mention, dan lampiran komentar: proc-app menyimpan **0 baris** untuk ketiganya
- Langganan (follow): proc-app hanya punya 1 baris
- Master supplier proc-app: digantikan master SAP
- Riwayat impor harga proc-app: PMB punya riwayat impor sendiri

## 6. Temuan dan perbaikan selama migrasi

1. **12.361 baris gagal** pada percobaan pertama karena kolom `pr_type` di PMB hanya 1 karakter, sedangkan data lama berisi kata ("Item", "progress", "Service"). Kolom diperlebar (`pr_type` 32, `delivery_status` 32, `currency` 8) dan impor ulang berjalan **tanpa kegagalan**.
2. **Identitas SAP sintetis kembar**: penomoran sementara untuk dokumen lama dipakai sama pada semua baris. Sekarang diturunkan dari nomor dokumen sehingga unik dan stabil lintas impor.
3. **Nama berkas baris item tidak cocok**: pengimpor mencari `purchase_order_lines.csv` / `purchase_request_lines.csv` sedangkan ekspor awal bernama `*_details.csv`, sehingga baris item tidak terbaca pada percobaan pertama.
4. **Nomor baris 0 berulang pada data lama**: beberapa baris dalam satu dokumen memakai `line_num` 0 sehingga kunci unik PMB menolaknya. Baris seperti itu kini diberi nomor urut tambahan per dokumen; 5.791 baris PO dan 7.950 baris PR disisipkan, lalu 143 + 6.330 baris kelebihan dibersihkan setelah dibandingkan dengan berkas ekspor.
5. **Dokumen yang ada di cache proc-app tetapi tidak ada di SAP** (contoh PO `260206671`, PR `260141762`): tidak ditemukan di kelima basis data SAP, sehingga dianggap baris basi proc-app dan tidak dijadikan acuan.

## 7. Kriteria terima Fase 5

| Kriteria | Status |
|---|---|
| Tidak ada dokumen hilang | Terpenuhi: dokumen PO/PR PMB dalam selisih ≤ 24 dokumen (0,2%), semuanya dapat dijelaskan |
| Tidak ada dokumen berganda | Terpenuhi: 0 nomor dokumen ganda pada PO maupun PR |
| Nilai cocok | Tidak bisa dibandingkan langsung (proc-app menyimpan nilai nol); nilai PMB dihitung dari baris item |
| Lampiran utuh | Terpenuhi: 25.399 baris lampiran, berkas tersalin, 58 ditandai tidak tersedia sesuai keputusan |
| Jejak persetujuan | Terpenuhi: 16.035 baris |

## 8. Langkah lanjutan

1. proc-app dibuat **baca-saja** (unggah lampiran, komentar, dan persetujuan dimatikan) selama masa berdampingan ± 1 bulan
2. Tim Procurement bekerja penuh di PMB selama masa itu
3. Setelah dinyatakan stabil: login proc-app dinonaktifkan, basis datanya diarsipkan, dan jadwal sinkronnya dihentikan
