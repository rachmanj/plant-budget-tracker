# Integrasi proc-app → PMB — Hasil Pengukuran Awal (Fase 0)

Diukur: 23 September 2026 (WITA), langsung dari basis data `procapp` di server 192.168.32.13
(MariaDB 10.4.32), akun baca-saja. Data masih aktif tumbuh — berkas lampiran terakhir masuk
23 September 2026 pukul 17:33.

## 1. Volume dokumen

| Tabel | Jumlah baris | Catatan |
|---|---|---|
| `purchase_orders` | **10.904** | 2025-06-05 → 2026-09-23 |
| `purchase_order_details` | 35.453 | nilai baris total **Rp 838.255.387.139** |
| `purchase_requests` | **14.147** | 2025-05-08 → 2026-09-23 |
| `purchase_request_details` | 44.670 | |
| `purchase_order_approvals` | 15.820 | jejak persetujuan PO |
| `po_attachments` | **3.141 berkas** | **639 MB**; 1.691 PO punya lampiran |
| `po_attachment_purchase_order` | 3.141 | satu lampiran ↔ satu PO |
| `item_prices` | 2.572 | |
| `item_price_histories` | 2.584 | |
| `item_price_imports` | 2.854 | |
| `suppliers` | 355 | |
| `sync_logs` | 10.255 | catatan sinkron SAP |
| `users` | 16 | akun nyata |

## 2. Temuan penting

1. **Nilai PO tidak ada di kepala dokumen.** Kolom `total_po_price` dan `po_with_vat` bernilai nol
   untuk **seluruh 10.904 PO**. Nilainya ada di baris: `purchase_order_details.item_amount`
   (total Rp 838,26 miliar). Register PMB harus menjumlahkan dari baris, atau mengambil dari SAP.
2. **Fitur kolaborasi tidak terpakai.** `comments`, `comment_mentions`, `comment_attachments` =
   **0 baris**; `po_follows` = 1 baris; `pr_follows` = 0. Fitur ini dibangun November 2025 tetapi
   tidak pernah dipakai — jadi tidak ada riwayat yang perlu dimigrasikan dari sisi komunikasi.
3. **Cakupan nyata condong ke Plant.** PO per proyek:
   022C 4.066 · 017C 2.159 · 021C 2.084 · 025C 1.007 · APS 829 · 000H 424 = **97% proyek Plant**;
   sisanya 026C 230 · 001H 78 · 023C 22 · 005P 4 · 006P 1.
   PO per departemen: **PLANT 7.130 (65%)** · LOGW 2.529 (23%) · HCS 588 · SHE 179 · IT 151 ·
   APS 135 · CORSEC 68 · OPR 67 · PROC 30 · ACC 8 · DNC 3.
   → Usul: PMB mengambil alih **PLANT + LOGW (± 88%)** lebih dulu; departemen lain menyusul atau
   tetap di proc-app.
4. **Pemakai sedikit.** Peran: buyer 8 akun · admin 1 · adminproc 1 (`procmgr`) · director 1
   (`yuwana`) · logistic 1 · superadmin 1. Pemetaan peran ke PMB kecil dan cepat.
5. **Jejak baris SAP sudah ada** di `purchase_order_details` (`sap_doc_entry`, `sap_line_num`,
   `sap_vis_order`, `line_identity`) — pola anti-ganda ini harus ditiru PMB.
6. **Lampiran tersimpan di storage aplikasi** dengan pola `po_attachments/<nama-acak>.pdf`
   (satu berkas PDF per lampiran, rata-rata ± 200 KB).

## 3. Yang masih diperlukan

- Kredensial FTP baca-saja (FileZilla Server 0.9.41 sudah jalan di .13, anonymous ditolak — benar)
  atau paket ZIP lewat Google Drive untuk menarik 639 MB berkas lampiran.
- Konfirmasi lokasi folder aplikasi di .13 dan apakah kode yang ter-deploy sama dengan repo
  (commit terakhir repo 30 Mar 2026, migrasi terakhir Nov 2025).

## 4. Verifikasi langsung di server .13 (via SSH, 24 Sep 2026)

Setelah akses SSH dibuka (akun `Administrator`, kunci `procapp13_ed25519`):

| Yang diperiksa | Hasil |
|---|---|
| Lokasi aplikasi | `C:\xampp\htdocs\proc-app` (Windows 10 22H2, XAMPP) |
| Commit ter-deploy | **`2c0614d` — "fix bug on sap sync when no data found"** = **persis sama dengan HEAD repo** |
| Skema basis data | 47 migrasi, identik dengan repo |
| Konfigurasi basis data | `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=procapp` (terkonfirmasi) |
| Lampiran yang **benar-benar ada di disk** | `po_attachments` **3.146 berkas / 639,3 MB**; `pr_attachments` **22.296 berkas / 7.080,8 MB** |

Temuan tambahan:

1. **Lampiran PR jauh lebih besar dan masih dipakai.** `pr_attachments` di basis data berisi
   **21.984 baris** (+ pivot `pr_attachment_purchase_request` 21.993 baris), dengan berkas nyata
   **7,1 GB** di disk. Jadi total lampiran yang perlu dimigrasikan bukan 639 MB, melainkan
   **± 7,7 GB** (PO + PR).
2. **Ada folder sisa berpenamaan berbeda**: `po-attachments` (9 berkas, 0,5 MB) dan
   `pr-attachments` (35 berkas, 5,1 MB) di samping folder resmi `po_attachments` / `pr_attachments`.
   Kemungkinan sisa versi lama — jangan ikut dimigrasikan tanpa pemeriksaan.
3. Path berkas di basis data relatif terhadap `storage/app/public` (mis. `po_attachments/xxxx.pdf`).
4. Server .13 bukan hanya host proc-app: ada 16 aplikasi lain di `C:\xampp\htdocs`
   (dashboard, dds, eld, gamma, gen-ledger, genaf, ild, moca, notulen-ai, p2h, sap-bridge,
   work-orders, dll.) — jangan menyentuh yang lain.

## 5. Keputusan lampiran & kapasitas server (25 Sep 2026)

Iwan menyetujui **lampiran PR ikut dimigrasikan**, sehingga total berkas yang dipindahkan
**± 7,7 GB** (PO 639 MB + PR 7,08 GB).

Pengukuran kapasitas server tujuan (saphire-two, 192.168.32.149 — juga host DDS):

| Item | Nilai |
|---|---|
| Disk total / terpakai / **kosong** | 246 GB / 30 GB / **206 GB (13%)** |
| DDS (pemakai terbesar) | 9,2 GB (folder `storage` 8,3 GB) |
| ESD | 410 MB |
| PMB sebelum migrasi | 524 MB (folder `storage` baru 712 KB) |
| Basis data MySQL `plant_budget_tracker` | 3 MB |
| Docker image / container / build cache | 5,5 GB / 332 MB / 2,7 GB (dapat dibersihkan) |
| **Sesudah migrasi** | PMB ± 8,2 GB; sisa disk tetap **± 198 GB** |

Kesimpulan: kapasitas bukan penghambat. Catatan pelaksanaan: berkas disimpan di folder aplikasi
(`storage/app/public/...`) — bukan di dalam layer container — dan backup folder PMB akan naik dari
0,5 GB menjadi ± 8 GB (dump basis data tidak terpengaruh, hanya 3 MB).

## 6. Hasil pemindahan lampiran (25 Sep 2026)

**Lampiran PO** — selesai dan terverifikasi:

- Arsip 608.278.959 byte (581 MB), SHA-256 `6261d9bcb1ca366455c6ddbc97386902a882a18748d3d79dc55164afd3db5ecf`
- Isi 3.146 berkas · **3.141 baris basis data semuanya ada** · **ukuran cocok untuk seluruh 3.141** (0 tidak cocok)
- 5 berkas "yatim" di disk (tidak tercatat di basis data), tidak dianggap data resmi

**Lampiran PR** — selesai dan terverifikasi:

- Arsip 7.445.995.520 byte (**6,93 GB**), SHA-256 `36fc06c9ebef184c1bc2…` (berkas `.sha256` tersimpan bersama arsip)
- Isi 22.314 berkas · **22.306 berkas pendaftarannya cocok dengan basis data dan ukurannya identik** (0 tidak cocok)
- **5 berkas** masuk ke server **saat penyalinan sedang berjalan** sehingga tidak ikut terekam (wajar; dapat diambil ulang kapan saja)
- **Temuan mutu data di proc-app sendiri** (bukan akibat pemindahan): **25 baris** `pr_attachments` **rujukannya menggantung** — baris ada di basis data tetapi berkasnya **tidak ada di disk**; dan **8 berkas yatim** (ada di disk, tidak tercatat di basis data).
- **Keputusan Iwan (26 Sep 2026): dibiarkan dengan penanda.** Baris menggantung tetap dimigrasikan sebagai baris arsip (riwayat tidak boleh bolong) namun saat ditampilkan diberi penanda berkas tidak tersedia, dan berkas yatim tidak diimpor. Larangan yang menyertainya: **jangan** menghapus baris menggantung atau berkas yatim dari basis data proc-app sumber, dan jangan mengarang berkas pengganti.

Letak arsip sementara di mesin Dea: `~/proc-app-legacy/` (po_attachments.zip 581 MB + pr_attachments.tar 7,0 GB, total 8,2 GB) — bahan mentah untuk Fase 5.
