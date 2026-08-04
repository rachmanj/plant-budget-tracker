Keep your task management simple and focused on what you're actually working on:

```markdown
**Purpose**: Track current work and immediate priorities
**Last Updated**: [Auto-updated by AI]

## Task Management Guidelines

### Entry Format

Each task entry must follow this format:
[status] priority: task description [context] (completed: YYYY-MM-DD)

### Context Information

Include relevant context in brackets to help with future AI-assisted coding:

- **Files**: `[src/components/Search.tsx:45]` - specific file and line numbers
- **Functions**: `[handleSearch(), validateInput()]` - relevant function names
- **APIs**: `[/api/jobs/search, POST /api/profile]` - API endpoints
- **Database**: `[job_results table, profiles.skills column]` - tables/columns
- **Error Messages**: `["Unexpected token '<'", "404 Page Not Found"]` - exact errors
- **Dependencies**: `[blocked by auth system, needs API key]` - blockers

### Status Options

- `[ ]` - pending/not started
- `[WIP]` - work in progress
- `[blocked]` - blocked by dependency
- `[testing]` - testing in progress
- `[done]` - completed (add completion date)

### Priority Levels

- `P0` - Critical (app won't work without this)
- `P1` - Important (significantly impacts user experience)
- `P2` - Nice to have (improvements and polish)
- `P3` - Future (ideas for later)

--- Example

# Current Tasks

## Working On Now

- `[WIP] P1: Implement user authentication [src/auth/login.tsx, Firebase Auth]`

## Up Next (This Week)

- `[ ] P0: Fix database connection timeout [src/db/connection.ts, line 23]`
- `[ ] P1: Add error handling to API calls [API endpoints: /users, /profile]`

## Blocked/Waiting

- `[blocked] P2: Add payment integration [waiting for Stripe API keys]`

## Recently Completed

- `[done] P0: Set up database schema [users table, profiles table] (completed: 2025-01-15)`
- `[done] P1: Create basic routing [React Router setup] (completed: 2025-01-14)`

## Recently Completed

- `[done] P0: Phase 0 — Laravel 11 scaffold, Sanctum SPA auth, Inertia+React+AntD, spatie RBAC (Teams/project_code), ArkfleetClient+EquipmentCache, admin UI, tests [docs/plan.md §Phase 0] (completed: 2026-08-04)`
- `[done] P0: Create detailed implementation action plan [docs/plan.md — Project Setup Guide, Phases 0-8, Route Table, Frontend Tree, Testing Strategy, Conventions, Pitfalls] (completed: 2026-08-04)`

## Up Next

- `[ ] P1: Phase 1 — Budget Management (ledger-based) [docs/plan.md §Phase 1]`
- `[ ] P1: Confirm ARKFLEET API token + network reachability for live ArkfleetClient smoke test`
- `[ ] P1: Confirm SAP Service Layer + sqlsrv direct SQL credentials/reachability before Phase 2 (blocker for MR read)`

## Quick Notes

- `docs/plan.md` (v1.0, 4 Aug 2026) is the file-by-file, phase-by-phase implementation companion to `docs/concept.md`. Read both before starting any phase — concept.md is the source of truth for architecture/rationale; plan.md is the concrete build guide (migrations, models, jobs, routes, tests).
- Plan.md flags a few deliberate additions beyond the base ERD (interchange `technical_signoff_by`/`technical_signoff_at` columns, `cannibal_request_component` pivot table) — these implement Open Questions #2 and the ERD's many-to-many `moves` relationship respectively; documented inline in the Phase 6/8 sections with rationale.
```
