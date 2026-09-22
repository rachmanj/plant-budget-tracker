**Purpose**: AI's persistent knowledge base for project context and learnings
**Last Updated**: [Auto-updated by AI]

## Memory Maintenance Guidelines

### Structure Standards

- Entry Format: ### [ID] [Title (YYYY-MM-DD)] ✅ STATUS
- Required Fields: Date, Challenge/Decision, Solution, Key Learning
- Length Limit: 3-6 lines per entry (excluding sub-bullets)
- Status Indicators: ✅ COMPLETE, ⚠️ PARTIAL, ❌ BLOCKED

### Content Guidelines

- Focus: Architecture decisions, critical bugs, security fixes, major technical challenges
- Exclude: Routine features, minor bug fixes, documentation updates
- Learning: Each entry must include actionable learning or decision rationale
- Redundancy: Remove duplicate information, consolidate similar issues

### File Management

- Archive Trigger: When file exceeds 500 lines or 6 months old
- Archive Format: `memory-YYYY-MM.md` (e.g., `memory-2025-01.md`)
- New File: Start fresh with current date and carry forward only active decisions

---

## Project Memory Entries

### [M001] Implementation action plan created (2026-08-04) ✅ COMPLETE

- **Decision:** Created `docs/plan.md` as the file-by-file, phase-by-phase implementation companion to `docs/concept.md`, covering Project Setup, Phases 0-8, full route table, frontend tree, testing strategy, conventions, and pitfalls.
- **Key learning:** Decided against `spatie/laravel-permission` — the concept ERD's project-scoped `role_user.project_code` pivot isn't supported by that package out of the box, so Phase 0 uses hand-rolled `hasRole()`/`hasPermission()` model methods instead to keep schema exactly matching the ERD.
- **Key learning:** Plan.md adds two schema details beyond the base ERD (with rationale documented inline): `interchange_maps.technical_signoff_by`/`technical_signoff_at` (Open Question #2's secondary sign-off recommendation) and a `cannibal_request_component` pivot table (concrete implementation of the ERD's `CANNIBAL_REQUEST ||--o{ COMPONENT` many-to-many).
- **Key learning:** Cannibal (Beta, Phase 8) is designed behind a `FEATURE_CANNIBAL_BETA` flag — build it but ship disabled until Directors sign off, per concept.md's Beta scope note.

### [M003] Cached budget balances double-counted after GRPO / never released after cancellation (fixed 2026-09-22)

- **Bug:** `BudgetEngine::recomputeCachedBalances()` summed only `commitment`, `actual`, and `carry_forward` ledger entries, ignoring `reversal` entirely. Measured impact on a 10 jt allocation: after `reverseCommitment` (cancellation) the ledger net was 0 but `committed_amount` stayed 1 jt (variance 9 jt instead of 10 jt); after `postActual` (GRPO) committed 2 jt + actual 2 jt = 4 jt spend shown while the ledger net was only 2 jt.
- **Ledger sign convention (memorise this):** `allocation`, `carry_forward`, `overbudget`, `reversal` are stored POSITIVE; `commitment` and `actual` are stored NEGATIVE. So a reversal is a *credit that nets off* a commitment, and `overbudget` increases the ceiling rather than being spend.
- **Fix:** `committed_amount = max(0, -(SUM commitment + SUM reversal where ref_type is not 'allocation'))`; `actual_amount = max(0, -SUM actual)`. `overbudget` deliberately excluded from both. `variance`/`utilization_pct`/`tolerance_cap` accessors were already correct and did not change.
- **Key learning:** anything that displays or validates budget must go through the `BudgetAllocation` accessors (`variance`, `utilization_pct`) or these cached columns — never a hand-written ledger CASE expression, which is how the dashboard first drifted from the official definition.

### [M002] Post-approval lifecycle (PR/PO/GRPO) closed — gap B-8 (2026-09-22) ✅ COMPLETE

- **Decision:** `plant_requests` gained `sap_po_id`, `sap_grpo_no`, `received_at`, `received_by` columns. `CreateSapPurchaseOrder` now advances the linked `PlantRequest` from `approved`/`pr_created` to `po_created` (never downgrades, e.g. a `received` request stays `received`). New `POST /plant-requests/{id}/receive` (permission `plant_request.receive`: plant_manager, project_manager, logistic_foreman, logistic_pic) sets status `received`; new `POST /plant-requests/{id}/create-pr` (role procurement_admin/it_manager only) dispatches `CreateSapPurchaseRequest`.
- **Key learning (gotcha):** `tabulation_bids` has **no `plant_request_id` FK**. The only link back to a plant request is `plant_requests.sap_pr_no === tabulation_bids.sap_pr_id` (both strings). Any code that needs to go from a bid/PO back to its plant request must join on that string, not an ID.
- **Key learning:** `receive()` intentionally does **not** call `BudgetEngine::postActual()` — the actual ledger entry is posted by `ReconcileGrpoToLedger` from the real SAP GRPO document. Posting again here would double-count the actual spend. This is documented inline in `PlantRequestController::receive()`.
