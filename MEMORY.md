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
