# MineOps → arkfleet-next API Gap Analysis & Implementation Plan

> **For:** Athena (arkfleet-next API implementation)
> **Date:** 30 July 2026
> **Scope:** API endpoints arkfleet-next needs to expose for MineOps (ARKA daily-production) to consume

---

## 1. Current State

### 1.1 arkfleet-next API (13 routes, all under `auth:sanctum` + `abilities:api:read`)

| # | Endpoint | Filters | Status |
|---|----------|---------|--------|
| 1 | `GET /api/v1/equipment` | search, is_active, project_code, plant_type | Exists |
| 2 | `GET /api/v1/equipment/{id}` | — | Exists |
| 3 | `GET /api/v1/equipment/{id}/hm-km-readings` | reading_type, date_from, date_to | Exists |
| 4 | `GET /api/v1/projects` | selectable_only, active_only, search | Exists |
| 5 | `GET /api/v1/projects/{code}` | — | Exists |
| 6 | `GET /api/v1/fixed-assets` | status | Exists |
| 7 | `GET /api/v1/fixed-assets/{id}` | — | Exists |
| 8 | `GET /api/v1/depreciation/runs` | year, month | Exists |
| 9 | `GET /api/v1/depreciation/runs/{id}` | — | Exists |
| 10 | `GET /api/v1/depreciation/entries` | book_type, fixed_asset_id, period_from, period_to | Exists |

### 1.2 MineOps EquipmentApiService — Consumption (currently broken)

| Method | Calls | Expected Response | Issue |
|--------|-------|-------------------|-------|
| `search()` | `GET /api/v1/equipment` | `{ data: [...] }` | **BROKEN**: index() returns paginator directly, not wrapped in `data` |
| `find()` | `GET /api/v1/equipment/{id}` | `{ data: {...} }` | Works (show() wraps in data) |
| `hmKmReadings()` | `GET /api/v1/equipment/{id}/hm-km-readings` | `{ data: [...] }` | **BROKEN**: hmKmReadings() returns paginator directly |

### 1.3 MineOps Local Cache (EquipmentAssignment model)

Fields cached locally:
- `equipment_id` (FK to arkfleet_next.equipment.id)
- `unit_code`, `description`, `plant_type_name`, `project_code`
- `site_id`, `pit_id`, `material_type`, `equipment_role`, `display_order`
- `is_active_for_tracking`, `synced_at`

---

## 2. What MineOps NEEDS from arkfleet-next

Per concept.md §2.4, §9.6 and existing code analysis:

### A. Equipment Discovery & Assignment (§4 MO-Master)
- Search equipment by project_code (site filter), plant_type, status, keyword
- View equipment detail: unit_code, description, plant_type, unitstatus, is_rfu
- **Reference data**: list of plant types, unit statuses, asset categories (for dropdowns)
- **Stats**: equipment count by status per site (Active/Breakdown/Standby for dashboard widget)

### B. Daily Operations — Fuel & Deployment (§4 MO-Entry)
- Equipment identity: unit_code + description (cached locally after assignment)
- **HM/KM readings**: latest and historical for FCR calculation (§3.1, §9.6)
- Filter equipment that is active and available (is_active + unitstatus)

### C. Dashboard — Equipment Utilization (§4 MO-Dashboard)
- Counts: Active / Standby / Breakdown per site
- Equipment activity: working hours, fuel consumption per equipment
- HM/KM trends for fleet health

### D. Multi-site Support (§6)
- All endpoints MUST support `project_code` filter for site-scoping
- Response must include `project_code` field for MineOps to map to sites

---

## 3. Gaps & Required Actions

### 🔴 CRITICAL: Response Format Standardization

**Problem:** arkfleet-next API controllers return inconsistently:
- `index()` → `response()->json($paginator)` (no `data` wrapper)
- `show()` → `response()->json(['data' => $equipment])` (wrapped)
- `hmKmReadings()` → `response()->json($paginator)` (no wrapper)

MineOps EquipmentApiService calls `$response->json('data', [])` everywhere → **search() and hmKmReadings() are broken**.

**Fix:** Standardize ALL list/detail endpoints to return `{ data: ... }`:
```php
// index → response()->json(['data' => $equipment]);
// show  → response()->json(['data' => $equipment]); // already correct
// hmKm  → response()->json(['data' => $readings]);
```

> **NOTE:** When switching to `data` wrapper, pagination metadata must also be included. Use Laravel API Resources or manually:
> ```php
> return response()->json([
>     'data' => $equipment->items(),
>     'meta' => [
>         'current_page' => $equipment->currentPage(),
>         'last_page' => $equipment->lastPage(),
>         'per_page' => $equipment->perPage(),
>         'total' => $equipment->total(),
>     ],
> ]);
> ```

---

### 🟠 HIGH: Equipment Detail Enrichment

**Problem:** `EquipmentController.show()` eager loads `unitModel`, `department`, `fixedAsset` but NOT `plantType` or `unitstatus`.

MineOps needs:
- `plant_type` → `plantType.name` (e.g., "Digger", "Hauler")
- `unitstatus` → `unitstatus.name` (e.g., "Active", "Inactive")
- `asset_category` → `assetCategory.name` (e.g., "Mayor", "Minor")

**Fix in EquipmentController.show():**
```php
$equipment->load([
    'unitModel:id,name',
    'department:id,department_name',
    'fixedAsset:id,equipment_id,status',
    'plantType:id,name',       // ADD
    'unitstatus:id,name,color', // ADD
    'assetCategory:id,name,code', // ADD
]);
```

**Also include in equipment index response:**
Equipment index already loads `plantType` via the filter query, but the response doesn't include computed `latest_hm`/`latest_km` which would be useful for MineOps dashboard.

**Suggested:** Add `latest_hm` and `latest_km` as appended attributes or load them via the API resource.

---

### 🟡 MEDIUM: New Endpoint — Reference Data

MineOps needs dropdown reference data that arkfleet-next already has in its DB. These are simple list endpoints:

#### **NEW: `GET /api/v1/plant-types`**
```json
// Response
{
  "data": [
    { "id": 1, "name": "Digger" },
    { "id": 2, "name": "Hauler" },
    { "id": 3, "name": "Support" },
    { "id": 4, "name": "Heavy Equip" }
  ]
}
```
- Model: `PlantType` (id, name, is_active)
- Controller: New `Api/V1/PlantTypeController`
- Filter: `?is_active=true` (default: all)

#### **NEW: `GET /api/v1/unit-statuses`**
```json
// Response
{
  "data": [
    { "id": 1, "name": "Active", "color": "green" },
    { "id": 2, "name": "Inactive", "color": "gray" },
    { "id": 3, "name": "Scrap", "color": "red" },
    { "id": 4, "name": "Sold", "color": "orange" }
  ]
}
```
- Model: `Unitstatus` (id, name, color, is_active)
- Controller: New `Api/V1/UnitStatusController`

#### **NEW: `GET /api/v1/asset-categories`**
```json
// Response
{
  "data": [
    { "id": 1, "name": "Mayor", "code": "MAYOR" },
    { "id": 2, "name": "Minor", "code": "MINOR" }
  ]
}
```
- Model: `AssetCategory` (id, name, code, is_active)
- Controller: New `Api/V1/AssetCategoryController`

---

### 🟡 MEDIUM: Equipment Stats Endpoint

**Problem:** MineOps DashboardService.utilization() currently reads from local `EquipmentAssignment` table, which lacks real-time status from arkfleet-next (it only has `is_active_for_tracking` flag, no `unitstatus` or `is_rfu`).

Dashboard wireframe shows:
```
STATUS ALAT
● Active     34
● Breakdown   4
● Standby     2
```

**NEW: `GET /api/v1/equipment/stats?project_code=022C`**
```json
// Response
{
  "data": {
    "project_code": "022C",
    "total": 40,
    "by_status": [
      { "status_id": 1, "status_name": "Active", "count": 34, "color": "green" },
      { "status_id": 3, "status_name": "Scrap", "count": 4, "color": "red" },
      { "status_id": 2, "status_name": "Inactive", "count": 2, "color": "gray" }
    ],
    "by_plant_type": [
      { "plant_type_id": 1, "plant_type_name": "Digger", "count": 12 },
      { "plant_type_id": 2, "plant_type_name": "Hauler", "count": 22 },
      { "plant_type_id": 3, "plant_type_name": "Support", "count": 6 }
    ],
    "rfu_count": 2
  }
}
```
- Groups equipment by `unitstatus` and `plant_type` for the given project_code
- MineOps uses this instead of its local EquipmentAssignment aggregation

---

### 🟢 LOW: Equipment Index — Add latest HM/KM fields

**Problem:** MineOps needs to show current HM/KM on equipment lists (assignment page). Currently would need N+1 API calls to get readings for each equipment.

Equipment model already has `latestHmReading()` and `latestKmReading()` HasOne relationships.

**Option A:** Add as computed fields to equipment index response via API Resource:
```json
{
  "data": [
    {
      "id": 1,
      "unit_code": "E 071",
      "description": "Excavator Hitachi EX1200-6",
      "plant_type_name": "Digger",
      "unitstatus_name": "Active",
      "project_code": "022C",
      "latest_hm": 15420.5,
      "latest_km": null,
      "latest_reading_date": "2026-07-29"
    }
  ]
}
```

**Option B:** Add query parameter `?with_readings=true` to equipment index (avoids loading HM/KM for every list call).

**Recommendation:** Start with Option B (opt-in). Most list queries don't need readings; only the Assignment page does.

---

### 🟢 NICE-TO-HAVE: Equipment filter by unitstatus

Equipment index already filters by `plant_type` and `project_code`, but NOT by `unitstatus_id`.

**Add:** `?unitstatus_id=X` filter to `GET /api/v1/equipment`

MineOps Equipment Assignment page would filter to only `unitstatus_id=1` (Active) by default.

---

## 4. Summary — What Athena Must Build

### Priority 1 — Fixes (blocking MineOps)
| # | Action | Effort |
|---|--------|--------|
| 1 | **Standardize all API responses** to `{ data: ..., meta: ... }` format | 1 hour |
| 2 | **Fix `show()` eager load** — add `plantType`, `unitstatus`, `assetCategory` | 15 min |

### Priority 2 — New Endpoints (needed for Phase 1)
| # | Endpoint | Route | Effort |
|---|----------|-------|--------|
| 3 | Plant Types list | `GET /api/v1/plant-types` | 30 min |
| 4 | Unit Statuses list | `GET /api/v1/unit-statuses` | 30 min |
| 5 | Asset Categories list | `GET /api/v1/asset-categories` | 30 min |
| 6 | Equipment Stats | `GET /api/v1/equipment/stats` | 1 hour |

### Priority 3 — Enhancements (dashboard quality)
| # | Action | Effort |
|---|--------|--------|
| 7 | Add `unitstatus_id` filter to equipment index | 15 min |
| 8 | Add `?with_readings=true` to equipment index (show latest HM/KM) | 30 min |

### Priority 4 — Nice to Have
| # | Action | Effort |
|---|--------|--------|
| 9 | Equipment index: add `unitstatus_name` to response | 15 min |
| 10 | Ensure all list endpoints support `per_page` param (many already do) | Verify |

---

## 5. Response Format Contract (the "API Contract")

All arkfleet-next API endpoints MUST return:

### List endpoints (paginated)
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 94
  }
}
```

### List endpoints (non-paginated, like reference data)
```json
{
  "data": [...]
}
```

### Detail endpoints
```json
{
  "data": { ... }
}
```

### Stats/aggregation endpoints
```json
{
  "data": { ... }
}
```

---

## 6. Dependency Map

```
MineOps EquipmentApiService
  ├── search()        → GET /api/v1/equipment         [BROKEN — needs data wrapper]
  ├── find()          → GET /api/v1/equipment/{id}     [WORKS — needs plantType/unitstatus eager load]
  └── hmKmReadings()  → GET /api/v1/equipment/{id}/hm-km-readings  [BROKEN — needs data wrapper]

MineOps EquipmentAssignment page (future)
  ├── Plant Type dropdown    → GET /api/v1/plant-types       [NEW]
  ├── Status filter          → GET /api/v1/unit-statuses    [NEW]
  └── Category filter        → GET /api/v1/asset-categories  [NEW]

MineOps Dashboard utilization widget
  └── Equipment counts       → GET /api/v1/equipment/stats  [NEW]
```

---

## 7. What NOT to Build

- ❌ **ARK-GS procurement KPI endpoints** — those are ARK-GS responsibility, not arkfleet-next (see concept.md §9.7)
- ❌ **CRUD endpoints** — MineOps only needs read-only access; all write operations stay in arkfleet-next web UI
- ❌ **Depreciation/fixed-asset endpoints** — already exist and MineOps doesn't consume them (fleet accounting domain, not operational)
- ❌ **Projects CRUD** — already exists; MineOps uses it only if site↔project mapping is useful (low priority)

---

## 8. Estimated Total Effort

| Priority | Items | Hours |
|----------|-------|-------|
| P1 — Fixes | #1-2 | 1.25h |
| P2 — New endpoints | #3-6 | 2.5h |
| P3 — Enhancements | #7-8 | 0.75h |
| P4 — Polish | #9-10 | 0.25h |
| **Total** | | **~4.75 hours** |

All work is within `arkfleet-next/app/Http/Controllers/Api/V1/` and `arkfleet-next/routes/api.php`. No database migrations needed — all reference data models already exist.
