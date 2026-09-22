# Lampiran Teknis Simulasi PMB (internal — jangan dibagikan ke user non-IT)

Pendamping `docs/manual-simulasi-pmb.md` (versi untuk user bisnis). Isinya checklist teknis yang
dipakai Dea/IT saat memandu atau memverifikasi simulasi.

> **Kredensial tidak ditulis di dokumen ini** (repo ini publik). Password MySQL/SSH ada di
> `~/.hermes/skills/hermes-server-ops/dds-server` (mesin Dea) dan di catatan internal Iwan.

## 1. Konteks

| Item | Nilai |
|------|-------|
| Aplikasi | `http://192.168.32.149:86` (saphire-two, LAN kantor) |
| Host path | `/home/ark-adm/docker-apps/www/php82/plant-budget-tracker` |
| Container | `php82` (PHP 8.2.30), MySQL container `mysql`, DB `plant_budget_tracker` |
| Worker | `queue-plantbudget` (`--queue=sap-writes,budget,default`), `scheduler-plantbudget` (60 s) |
| Versi terpasang saat manual dibuat | `5d40793` (22 Sep 2026); docs v1.4 |
| Proyek aktif (DB, dikelola Admin → Proyek) | `021C`, `022C`, `025C`, `APS` — sinkronisasi ARKFLEET tidak menimpa `is_active` (hanya proyek baru memakai default `ARKFLEET_ACTIVE_PROJECTS`) |

## 2. Sebelum simulasi (baseline)

```sql
-- ringkasan anggaran
SELECT p.project_code, p.period_month, p.status, COUNT(a.id) AS alokasi,
       SUM(a.allocated_amount) AS total
FROM budget_periods p LEFT JOIN budget_allocations a ON a.budget_period_id = p.id
GROUP BY p.id ORDER BY p.project_code, p.period_month;

-- jumlah transaksi (0 sebelum mulai pada data bersih)
SELECT (SELECT COUNT(*) FROM plant_requests)   AS plant_requests,
       (SELECT COUNT(*) FROM tabulation_bids)  AS bids,
       (SELECT COUNT(*) FROM dmbd_entries)     AS dmbd,
       (SELECT COUNT(*) FROM budget_ledgers)   AS ledger;
```

Query dari mesin Dea (kredensial: lihat skill/kredensial internal):

```bash
mysql -h 192.168.32.149 -P 3306 -u <user> -p plant_budget_tracker
```

## 3. Verifikasi setelah setiap tahap

```sql
-- 1. plant request
SELECT request_no, status, estimated_total, budget_utilization_pct FROM plant_requests ORDER BY id;

-- 2. ledger — inti kontrol anggaran
SELECT l.id, l.entry_type, l.amount, l.ref_type, l.ref_id, l.memo, u.name AS oleh
FROM budget_ledgers l LEFT JOIN users u ON u.id = l.posted_by ORDER BY l.id DESC LIMIT 20;

-- 3. approval
SELECT approvable_type, approvable_id, step_order, required_role, decision, approver_id
FROM request_approvals ORDER BY id;

-- 4. bid & award
SELECT b.bid_no, b.status, a.tabulation_bid_vendor_id, v.vendor_name, v.price
FROM tabulation_bids b
LEFT JOIN tabulation_bid_awards a ON a.tabulation_bid_id = b.id
LEFT JOIN tabulation_bid_vendors v ON v.id = a.tabulation_bid_vendor_id;

-- 5. overbudget & cancellation
SELECT request_no, status, requested_amount, over_pct FROM overbudget_requests ORDER BY id;
SELECT plant_request_id, initiated_by, po_stage, status, budget_reversal_amount FROM cancellation_requests;

-- 6. DMBD harian
SELECT unit_code_cache, report_date, operational_status, reported_by FROM dmbd_entries ORDER BY id DESC LIMIT 10;

-- 7. integrasi SAP
SELECT operation, correlation_key, status, error_message FROM sap_sync_logs ORDER BY id DESC LIMIT 10;
```

## 4. Antrean, worker, dan log

```bash
ssh ark-adm@192.168.32.149
docker ps --format '{{.Names}} | {{.Status}}' | grep plantbudget
docker exec mysql mysql -u<user> -p plant_budget_tracker \
  -e "SELECT COUNT(*) AS sisa_job FROM jobs; SELECT COUNT(*) AS gagal FROM failed_jobs;"
tail -n 100 /home/ark-adm/docker-apps/www/php82/plant-budget-tracker/storage/logs/laravel.log
```

## 5. Smoke test per peran tanpa kredensial di dokumen

Login programatik (pakai akun demo `<role>@pmb.demo` / `password`) lalu periksa props Inertia:
`can.*`, `nav.isApprover`, `nav.pendingApprovals`, `nav.viewSapDashboard`, `showForm`, `prefill`.
Ingat throttle login **5/menit** — beri jeda 20 detik antar akun.

Verifikasi tombol frontend (React tidak dirender server) dengan mencari label di bundle:

```bash
cd public/build/assets
grep -l "Buat Bid" *.js; grep -l "Create PO" *.js; grep -l "Ubah Draft" *.js
grep -l "Ajukan Pembatalan" *.js; grep -l "Sign-off Teknis" *.js; grep -l "Ajukan Overbudget" *.js
```

## 6. Deploy ulang (setelah commit + push)

```bash
cd /home/ark-adm/docker-apps/www/php82/plant-budget-tracker
git pull --ff-only origin main
npm ci --legacy-peer-deps && npm run build          # build DI HOST (node v20)
docker exec -w /var/www/html/php82/plant-budget-tracker php82 php artisan migrate --force
docker exec -w /var/www/html/php82/plant-budget-tracker php82 php artisan optimize
docker restart queue-plantbudget scheduler-plantbudget
for p in 81 83 84 85 86; do curl -s -o /dev/null -w "$p:%{http_code}\n" http://127.0.0.1:$p/; done
```

## 7. Catatan kredensial (penting)

Credential untuk server ini pernah tertulis di dokumen versi awal (commit `ec6a870`) dan repo ini
**publik** — sudah dibersihkan dari berkas, tetapi masih ada di riwayat git dan di PDF lama.
Tindakan yang disarankan: **rotasi password** akun yang pernah tertulis (user MySQL integrasi DDS
dan root container MySQL), lalu update catatan internal. Riwayat git bisa ditulis ulang bila Iwan
menginginkan (perlu force-push).
