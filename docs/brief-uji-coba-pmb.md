# Brief Uji Coba Menyeluruh PMB

**Versi 1.3 — 26 November 2026**
Untuk seluruh peserta uji coba · dokumen pendamping: *Manual Penggunaan untuk Tester (semua modul)*

## 1. Tujuan uji coba

Uji coba ini bukan presentasi dan bukan demo. Tujuannya **mencari bagian yang salah, membingungkan, atau belum lengkap** sebelum PMB dipakai sebagai tempat kerja harian menggantikan proc-app.

Yang ingin dibuktikan:

1. Seluruh modul bisa dijalankan dengan peran yang sebenarnya — bukan hanya oleh akun administrator.
2. Alur pengadaan berjalan utuh dari permintaan plant sampai Purchase Order tercatat di SAP.
3. Pagu, komitmen, dan pemakaian anggaran menghasilkan angka yang masuk akal dan bisa ditelusuri.
4. Persetujuan berjenjang dan batas nilai bekerja sebagaimana aturan perusahaan.
5. Riwayat pengadaan lama tetap bisa dibaca dan ditelusuri sampai lampirannya.

Sikap yang kami harapkan: **jujur dan kritis.** Temuan yang dilaporkan hari ini jauh lebih murah daripada kesalahan yang baru ketahuan setelah semua orang bekerja di dalamnya.

## 2. Lingkungan uji

| Hal | Keterangan |
|---|---|
| Alamat PMB | **`https://pmb.arka-acc.online`** — bisa dibuka dari jaringan kantor maupun dari luar |
| Alamat cadangan (jaringan kantor) | `http://192.168.32.149:86` |
| Peramban | Chrome atau Edge terbaru di komputer kantor |
| Basis data SAP | **DB LAB dari IT**: `LAB_SBO_20261124` — data per 23–24 November 2026 |
| Nama DB LAB | `LAB_SBO_20261124` (bila IT memotong ulang ke posisi 26 November, namanya akan diberitahukan sebelum uji dimulai) |
| Sifat data | dokumen yang dibuat saat uji coba hanya masuk ke DB LAB |
| Data yang sudah ada | PO 11.480 dokumen (Rp 876,2 miliar) dan PR 14.911 dokumen (Rp 14,7 miliar), dokumen terbaru 23 November 2026; lampiran 25.399, vendor 2.395, harga item 1.093 |
| Data di DB LAB | 24.088 Purchase Order dan 29.793 Purchase Request sampai 23 November 2026; PMB menarik yang termasuk lingkup Plant dan Logistik |

**Persiapan yang kami lakukan sebelum hari pertama** (supaya kamu tidak perlu menyiapkan apa pun selain akun):

1. Mengarahkan PMB ke DB LAB dari IT (`LAB_SBO_20261124`) dan menguji koneksinya.
2. Menjalankan penarikan data pertama — **sudah selesai**: register kini memuat dokumen sampai 23 November 2026 (PO 11.480 dokumen, PR 14.911 dokumen).
3. Membersihkan antrean pekerjaan yang gagal akibat penggantian DB LAB — **sudah selesai**: antrean kosong dan status sinkronisasi terakhir berhasil.
4. Memastikan seluruh 16 akun uji bisa masuk dengan kata sandi `password`.

**Penting:** uji coba **tidak menyentuh data perusahaan**. Semua dokumen yang kamu buat, setujui, atau batalkan saat pengujian tercatat di DB LAB. Karena itu **jangan ragu mencoba**, termasuk mencoba hal yang menurutmu salah — justru itu yang kami butuhkan.

## 3. Peserta dan cakupan modul

Nama peserta diisi oleh koordinator sebelum uji dimulai.

| Kelompok | Akun yang dipakai | Modul yang diuji | Nama tester |
|---|---|---|---|
| Plant (pemohon) | planner, mechanic, logistic.foreman, logistic.pic | Plant Requests, DMBD, Overbudget, Cancellation, Dashboard | |
| Plant (penyetuju) | project.manager, plant.manager | Plant Requests, Approvals, Interchange (tanda tangan teknis), Budget | |
| Pengadaan (pelaksana) | buyer | Tabulation Bids, Purchase Requests, Purchase Orders, Suppliers, Item Prices | |
| Pengadaan (administrasi) | adminproc | Purchase Orders, lampiran, komentar, Suppliers, Item Prices, Procurement Settings, Laporan, ekspor | |
| Pengadaan (penyetuju) | procurement.manager | Approvals, Tabulation Bids, Cancellation | |
| Direksi dan keuangan | finance.director, operation.director, director | Approvals, Overbudget, Budget, Laporan | |
| AML | aml.manager, aml.dept.head | Approvals (Interchange dan Cannibal) | |
| IT | admin | SAP Sync, Users, Role & Permission, Projects, Components | |

## 4. Akun uji

Kata sandi seluruh akun: **`password`**.

| Username | Email | Peran |
|---|---|---|
| planner | planner@pmb.demo | Planner |
| mechanic | mechanic@pmb.demo | Mechanic |
| logistic.foreman | logistic.foreman@pmb.demo | Logistic Foreman |
| logistic.pic | logistic.pic@pmb.demo | Logistic PIC |
| project.manager | project.manager@pmb.demo | Project Manager |
| plant.manager | plant.manager@pmb.demo | Plant Manager |
| buyer | buyer@pmb.demo | Buyer |
| adminproc | procurement.admin@pmb.demo | Procurement Admin |
| procurement.manager | procurement.manager@pmb.demo | Procurement Manager |
| finance.director | finance.director@pmb.demo | Finance Director |
| operation.director | operation.director@pmb.demo | Operation Director |
| director | president.director@pmb.demo | President Director |
| aml.manager | aml.manager@pmb.demo | AML Manager |
| aml.dept.head | aml.dept.head@pmb.demo | AML Dept Head |
| admin | it.manager@pmb.demo | IT Manager |
| rachmanj | rachmanj@gmail.com | akun koordinator (melihat semua) |

Cara masuk: buka alamat PMB, lalu isi kolom pertama dengan **username atau email** — keduanya diterima. Bila gagal masuk beberapa kali berturut-turut, tunggu satu menit: PMB membatasi 5 percobaan per menit.

## 5. Agenda uji coba

Tanggal diisi koordinator; urutan modul sudah disusun agar dokumen dari hari sebelumnya bisa dipakai pada hari berikutnya.

| Hari | Fokus modul | Keluaran yang diharapkan |
|---|---|---|
| Hari 1 — ______ | Masuk, Dashboard, Budget, Plant Requests, DMBD | permintaan plant tersimpan dan bisa disetujui berjenjang |
| Hari 2 — ______ | Overbudget, Cancellation, Interchange, Cannibal, Components, SAP Sync, Approvals | dokumen berjenjang berjalan sampai selesai; sinkronisasi SAP terbaca |
| Hari 3 — ______ | Tabulation Bids, Purchase Orders, Purchase Requests, Daily PR, Suppliers, Item Prices, Reports, Users/Role/Projects | PO terbentuk dari pembandingan vendor; laporan dan ekspor terbaca |

Satu modul dianggap sudah diuji bila **minimal satu putaran penuh** dikerjakan oleh peran yang berhak, termasuk mencoba satu tindakan yang seharusnya ditolak.

## 6. Cara melaporkan temuan

Setiap temuan ditulis **satu baris per temuan** pada lembar temuan (berkas CSV yang kami sediakan, atau kolom di bawah bila dicetak).

Kolom lembar temuan: **No · Tanggal · Nama tester · Modul · Langkah yang dilakukan · Yang diharapkan · Yang terjadi · Tingkat · Lampiran/tangkapan layar**

Contoh pengisian:

| No | Modul | Langkah yang dilakukan | Yang diharapkan | Yang terjadi | Tingkat |
|---|---|---|---|---|---|
| 1 | Purchase Orders | Menekan Follow pada PO, lalu mengaktifkan "Only POs I follow" | PO itu muncul di daftar | Daftar kosong sampai halaman dimuat ulang | Minor |
| 2 | Tabulation Bids | Menekan Create PO sebelum persetujuan selesai | Muncul penjelasan apa yang masih ditunggu | Muncul pesan galat tanpa penjelasan | Major |

Tingkat temuan:

| Tingkat | Arti | Contoh |
|---|---|---|
| Blocker | pekerjaan tidak bisa dilanjutkan sama sekali | tidak bisa masuk, dokumen tidak bisa disimpan, angka pagu salah besar |
| Major | pekerjaan bisa dilanjutkan tetapi hasilnya salah atau berbelit | persetujuan tidak muncul untuk peran yang berhak, nilai dokumen salah hitung |
| Minor | mengganggu, ada jalan lain | penyaring tidak mengingat pilihan terakhir, teks berbahasa campur |
| Cosmetic | hanya soal tampilan | jarak, warna, salah ketik pada label |

Aturan pelaporan: sertakan **tangkapan layar** untuk setiap temuan Major dan Blocker; tulis apa adanya tanpa perlu menyimpulkan penyebabnya; jangan mengubah atau menghapus data untuk "menutupi" temuan.

## 7. Aturan main

1. **Gunakan akun sesuai peranmu.** Jangan memakai akun peran lain untuk melihat hasilnya — justru perbedaan hak akses yang ingin diuji.
2. **Jangan mengubah pengaturan bersama** (batas nilai persetujuan, pagu, peran dan izin) tanpa izin koordinator, karena mengubah dasar pengujian peserta lain.
3. **Jangan mengunggah berkas rahasia** ke lampiran uji; gunakan berkas contoh.
4. **Catat, jangan perbaiki sendiri.** Bila ada angka atau dokumen yang mencurigakan, laporkan; jangan menghapusnya.
5. **Satu temuan, satu baris.** Jangan menggabungkan beberapa masalah dalam satu baris.
6. **Laporkan juga yang membingungkan**, walaupun bukan kesalahan teknis — misalnya label yang tidak dimengerti atau langkah yang terasa berputar.
7. **Uji juga penolakan.** Pastikan tindakan yang seharusnya tidak boleh (misalnya pengguna plant membuka dokumen proyek lain, atau pembuatan PO sebelum persetujuan) memang ditolak dengan penjelasan yang jelas.
8. **Jangan menguji di luar jam yang ditetapkan** bila modul sedang dipakai peserta lain untuk alur berantai.
9. **Simpan bukti**: nomor dokumen yang kamu buat, dan jam kejadian.
10. **Bila macet lebih dari 10 menit**, hubungi koordinator; jangan menghabiskan waktu sendiri.

## 8. Kriteria selesai

Uji coba dinyatakan selesai dan aplikasi layak dilanjutkan bila:

1. Setiap modul sudah diuji minimal satu putaran oleh peran yang berhak.
2. Seluruh alur berantai (permintaan → PR → pembandingan vendor → persetujuan → PO) berhasil dijalankan ujung ke ujung.
3. Tidak ada temuan **Blocker** yang masih terbuka.
4. Temuan **Major** sudah terdokumentasi lengkap dengan langkah pengulangan, dan sudah ada keputusan untuk masing-masing (diperbaiki / ditunda / bukan temuan).
5. Laporan uji diterima koordinator, termasuk daftar hal yang dianggap membingungkan.

## 9. Yang sudah kami ketahui (bukan temuan baru)

Hal-hal berikut sudah tercatat sebelum uji coba; tetap boleh kamu laporkan bila terasa mengganggu, tetapi tidak dihitung sebagai temuan baru:

1. **58 lampiran riwayat** dari aplikasi lama ditandai "berkas tidak tersedia" karena berkasnya memang sudah tidak ada — barisnya tetap tampil, berkasnya tidak bisa diunduh.
2. **Harga item** baru berisi 1.093 harga; daftar yang lebih lengkap menyusul dari tim.
3. **Angka aktual** pada pagu baru terisi setelah dokumen dari sistem lain terbaca (penarikan berkala setiap 15 menit) — bukan kesalahan hitung.
4. **Interchange dan Cannibal** masih tahap terbatas: hanya sebagian peran melihatnya.
5. **Akses dari luar jaringan kantor** memakai alamat resmi `https://pmb.arka-acc.online`; bila suatu saat tidak bisa dibuka, gunakan alamat dalam jaringan kantor `http://192.168.32.149:86`.
6. **proc-app masih hidup** selama masa berdampingan; pekerjaan yang sudah lazim masih bisa dilakukan di sana.

## 10. Koordinator dan jalur bantuan

| Keperluan | Hubungi |
|---|---|
| **Help desk aplikasi** (pertanyaan, temuan, permintaan perbaikan) | **dea.hermes@adikaramulia.co.id** |
| Akun tidak bisa dipakai, lupa kata sandi, akses modul | koordinator uji coba, atau help desk `dea.hermes@adikaramulia.co.id` |
| Temuan Blocker (pekerjaan berhenti) | koordinator uji coba, segera — sertakan nomor dokumen dan tangkapan layar |
| Pertanyaan arti angka atau alur bisnis | koordinator uji coba, diteruskan ke tim aplikasi |

Lembar temuan dikumpulkan setiap akhir hari pengujian, bukan di akhir minggu, supaya perbaikan bisa berjalan sambil pengujian hari berikutnya berlangsung. Temuan juga boleh dikirim langsung ke help desk **dea.hermes@adikaramulia.co.id** dengan menyebutkan modul, langkah, dan tangkapan layar.
