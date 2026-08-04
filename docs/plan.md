# Plant Budget Tracker (PMB) — Implementation Action Plan

> **Status:** Ready for development kickoff · **Version:** 1.0 · **Date:** 4 Aug 2026
> **Companion document to:** `docs/concept.md` (v1.0, 3 Aug 2026) — read that first for architecture rationale, ERD, approval workflows, and SAP/ARKFLEET integration specs. This plan translates the concept into concrete, file-by-file, phase-by-phase implementation steps.
> **Audience:** Developers implementing PMB from a greenfield repository.
> **Consistency rule:** Every table name, column, endpoint, and connection name in this document matches `docs/concept.md` exactly. Where this plan adds detail (e.g. exact migration code) it must not contradict the concept doc — if a conflict is found, `docs/concept.md` wins and this file must be corrected.

---

## Table of Contents

1. [Project Setup Guide](#1-project-setup-guide)
2. [Phase-by-Phase Implementation](#2-phase-by-phase-implementation)
   - [Phase 0 — Scaffold, Auth, Roles, ARKFLEET Client](#phase-0--scaffold-auth-roles-arkfleet-client)
   - [Phase 1 — Budget Management (Ledger-Based)](#phase-1--budget-management-ledger-based)
   - [Phase 2 — Plant Request + Approval Workflow](#phase-2--plant-request--approval-workflow)
   - [Phase 3 — Tabulation Bid + Auto PO](#phase-3--tabulation-bid--auto-po)
   - [Phase 4 — SAP Integration (Read + Write + Sync)](#phase-4--sap-integration-read--write--sync)
   - [Phase 5 — DMBD Integration](#phase-5--dmbd-integration)
   - [Phase 6 — Overbudget + Cancellation + Interchange](#phase-6--overbudget--cancellation--interchange)
   - [Phase 7 — Reporting & Analytics](#phase-7--reporting--analytics)
   - [Phase 8 — Component Database + Cannibal (Beta)](#phase-8--component-database--cannibal-beta)
3. [Full Route Table](#3-full-route-table)
4. [Frontend Component Tree](#4-frontend-component-tree)
5. [Testing Strategy](#5-testing-strategy)
6. [Conventions & Code Organization](#6-conventions--code-organization)
7. [Critical Pitfalls to Avoid](#7-critical-pitfalls-to-avoid)

---

## 1. Project Setup Guide

### 1.1 System Requirements

| Requirement | Version / Detail |
|---|---|
| PHP | **8.3 or 8.4** (NOT 8.5 — ARKFLEET runs 8.5, PMB deliberately targets 8.3/8.4 for stability; see `docs/concept.md` §11 PHP 8.5 compatibility notes) |
| Composer | 2.7+ |
| Node.js | 20 LTS+ (for Vite + React 18) |
| MySQL | 8.4 (PMB schema `plant_budgeting`, same server as ARKFLEET) |
| SQL Server driver | Microsoft ODBC Driver 18 for SQL Server + PHP `sqlsrv`/`pdo_sqlsrv` extensions (for SAP direct SQL access, host `arkasrv2:1433`) |
| Redis | 7.x (cache, queue via Horizon, Reverb broadcasting) |
| Web server | Nginx or Apache (on-prem Linux) |
| Process manager | Supervisor (for `queue:work` / Horizon / Reverb daemons) |

**Required PHP extensions:**

```
ext-sqlsrv       # SAP SQL Server direct access (config/database.php: sap_sql connection)
ext-pdo_sqlsrv   # PDO driver backing sqlsrv
ext-redis        # Redis cache/queue (phpredis, preferred over predis for perf)
ext-pdo_mysql    # PMB's own MySQL schema (plant_budgeting)
ext-mbstring
ext-bcmath       # precise DECIMAL(18,2) arithmetic for budget ledger
ext-intl         # Rp / Bahasa Indonesia locale formatting
ext-gd or ext-imagick   # dompdf image rendering (PDF exports)
```

Install on Ubuntu/Debian (adjust for the actual on-prem distro):

```bash
sudo apt-get update
sudo apt-get install -y php8.3-cli php8.3-fpm php8.3-mbstring php8.3-bcmath \
  php8.3-intl php8.3-redis php8.3-gd php8.3-xml php8.3-curl php8.3-zip

# Microsoft ODBC Driver 18 + sqlsrv/pdo_sqlsrv (Microsoft's official repo)
curl https://packages.microsoft.com/keys/microsoft.asc | sudo apt-key add -
curl https://packages.microsoft.com/config/ubuntu/22.04/prod.list | sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev
sudo pecl install sqlsrv pdo_sqlsrv
echo "extension=sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/pdo_sqlsrv.ini
sudo phpenmod sqlsrv pdo_sqlsrv
```

Verify:

```bash
php -m | grep -i sqlsrv
php -v   # must report 8.3.x or 8.4.x
```

### 1.2 Project Bootstrap

```bash
composer create-project laravel/laravel plant-budget-tracker "^11.0"
cd plant-budget-tracker

# Core packages
composer require laravel/sanctum laravel/horizon laravel/reverb
composer require inertiajs/inertia-laravel
composer require barryvdh/laravel-dompdf
composer require guzzlehttp/guzzle
composer require spatie/laravel-permission   # optional convenience layer; PMB still owns its own roles/permissions tables per concept.md ERD — evaluate before adding, see §6.1 below
composer require --dev laravel/pint pestphp/pest pestphp/pest-plugin-laravel

# Laravel Boost (per user rule — always install for AI-assisted dev workflows)
composer require laravel/boost --dev
php artisan boost:install

# RBAC — spatie/laravel-permission with Teams for project scoping
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"
php artisan vendor:publish --tag="permission-config"
# Then edit config/permission.php: set 'team_foreign_key' => 'project_code'
```

> **Do NOT run** `composer require maatwebsite/excel` — it hard-caps PHP <8.5's spreadsheet dependency chain and is explicitly banned (see `docs/concept.md` §11 and Pitfall P1 below). All exports use `barryvdh/laravel-dompdf` (PDF) or native `fputcsv` (CSV).

Frontend:

```bash
npm install --legacy-peer-deps
npm install --legacy-peer-deps react react-dom @inertiajs/react
npm install --legacy-peer-deps antd @ant-design/icons @ant-design/pro-components
npm install --legacy-peer-deps @ant-design/cssinjs dayjs
npm install --legacy-peer-deps -D @vitejs/plugin-react laravel-vite-plugin typescript
```

> **Every** `npm install` / `npm ci` for the lifetime of this project MUST include `--legacy-peer-deps`. AntD Pro packages declare strict peer ranges that npm 9+ rejects otherwise (Pitfall P2/P9).

### 1.3 `.env` Template

```env
APP_NAME="Plant Budget Tracker"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Asia/Makassar
APP_URL=http://localhost:8000
APP_LOCALE=id

# --- PMB own MySQL schema ---
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=plant_budgeting
DB_USERNAME=pmb_app
DB_PASSWORD=

# --- Redis (cache, queue, broadcasting) ---
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb

# --- Sanctum (SPA auth) ---
SANCTUM_STATEFUL_DOMAINS=localhost:8000
SESSION_DOMAIN=localhost

# --- Laravel Reverb (WebSockets) ---
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# --- ARKFLEET (REST API, Sanctum bearer token, Tailscale-reachable) ---
ARKFLEET_API_URL=http://arkfleet-next.local/api/v1
ARKFLEET_API_TOKEN=
ARKFLEET_TIMEOUT=10
ARKFLEET_RETRIES=2
ARKFLEET_CACHE_TTL_LIST=3600
ARKFLEET_CACHE_TTL_DETAIL=21600
ARKFLEET_CACHE_TTL_STATS=1800

# --- SAP B1 Service Layer (REST/OData, cookie-based session) ---
SAP_SERVER_URL=https://arkasrv2:50000
SAP_DB_NAME=
SAP_USER=
SAP_PASSWORD=
SAP_SERVICE_LAYER_VERIFY_SSL=false

# --- SAP B1 Direct SQL Server (sqlsrv, complex joins / UDF fields) ---
SAP_SQL_HOST=arkasrv2
SAP_SQL_PORT=1433
SAP_SQL_DATABASE=
SAP_SQL_USERNAME=
SAP_SQL_PASSWORD=

# --- Budget engine defaults ---
BUDGET_DEFAULT_TOLERANCE_PCT=10.00
BUDGET_ROLLING_MONTHS=6

# --- Mail / notifications (director alerts, SAP sync failures) ---
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="pmb@company.local"
MAIL_FROM_NAME="${APP_NAME}"
```

### 1.4 `config/services.php` Entries

```php
'arkfleet' => [
    'base_url' => env('ARKFLEET_API_URL', 'http://arkfleet-next.local/api/v1'),
    'token'    => env('ARKFLEET_API_TOKEN'),
    'timeout'  => env('ARKFLEET_TIMEOUT', 10),
    'retries'  => env('ARKFLEET_RETRIES', 2),
    'cache_ttl' => [
        'list'   => env('ARKFLEET_CACHE_TTL_LIST', 3600),
        'detail' => env('ARKFLEET_CACHE_TTL_DETAIL', 21600),
        'stats'  => env('ARKFLEET_CACHE_TTL_STATS', 1800),
    ],
],

'sap' => [
    'server_url' => env('SAP_SERVER_URL', 'https://arkasrv2:50000'),
    'db_name'    => env('SAP_DB_NAME'),
    'user'       => env('SAP_USER'),
    'password'   => env('SAP_PASSWORD'),
    'verify_ssl' => env('SAP_SERVICE_LAYER_VERIFY_SSL', false),
],
```

### 1.5 `config/database.php` — `sap_sql` Connection

Add alongside the default `mysql` connection (do **not** remove or rename `mysql` — that remains PMB's own schema):

```php
'sap_sql' => [
    'driver'   => 'sqlsrv',
    'host'     => env('SAP_SQL_HOST', 'arkasrv2'),
    'port'     => env('SAP_SQL_PORT', '1433'),
    'database' => env('SAP_SQL_DATABASE'),
    'username' => env('SAP_SQL_USERNAME'),
    'password' => env('SAP_SQL_PASSWORD'),
    'charset'  => 'utf8',
    'prefix'   => '',
    'prefix_indexes' => true,
    'options'  => [
        'TrustServerCertificate' => true,
    ],
],
```

> This connection is **read-only in practice** — no migrations ever run against it, and the repository layer (`app/Services/Sap/SapReadRepository.php`, Phase 4) is the only code path allowed to query it. SAP models under `App\Models\Sap\` all declare `protected $connection = 'sap_sql';` and must never call `save()`/`create()`/`update()`.

### 1.6 Inertia + React 18 + Ant Design 5 Setup

```bash
php artisan install:inertia --stack=react --typescript=false
```

Since the concept doc keeps `.jsx`/`.tsx` split for type safety without violating Pitfall P10 (no TS syntax inside `.jsx`), the frontend uses **`.tsx` for pages/components** (full TS) rather than mixing `.jsx` + inline TS types. `vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
});
```

AntD 5 theme + Inertia bootstrap in `resources/js/app.tsx` (created fully in Phase 0 §Deliverables below).

### 1.7 Laravel Boost Install

Per workspace rules, Laravel Boost (MCP-based Laravel dev tools: `database-schema`, `database-query`, `tinker`, `browser-logs`, etc.) must be installed for AI-assisted development:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Confirm the MCP server registers in the IDE's MCP config and that `database-schema` / `database-query` tools return the `plant_budgeting` schema before starting Phase 0 migrations.

### 1.8 First-Run Checklist

```bash
php artisan key:generate
php artisan storage:link
touch database/database.sqlite   # not used — PMB uses MySQL; remove default sqlite connection ref in config/database.php default
php artisan migrate
php artisan db:seed               # roles, permissions, sample projects — Phase 0 seeders
npm install --legacy-peer-deps
npm run build                     # or `npm run dev` — tell the user to run this themselves per user rule
```

Verify SAP and ARKFLEET connectivity before Phase 2 (which reads from both):

```bash
php artisan tinker
>>> DB::connection('sap_sql')->select('SELECT TOP 1 * FROM OCRD'); // SAP vendor master smoke test
>>> app(\App\Services\Arkfleet\ArkfleetClient::class)->getProjects(); // ARKFLEET smoke test
```

---

## 2. Phase-by-Phase Implementation

### Phase 0 — Scaffold, Auth, Roles, ARKFLEET Client

**Goal:** A running Laravel 11 + Inertia/React/AntD shell with Sanctum SPA auth, a project-scoped role/permission system, and a working ARKFLEET client with Redis caching and a response normalizer that handles the `{data}` wrapper inconsistency.

**Dependencies:** None (first phase). Requires ARKFLEET API token and network reachability confirmed before starting.

#### A. Deliverables Checklist

- [ ] `composer create-project` scaffold complete, `.env` configured (§1.3)
- [ ] `config/services.php` (`arkfleet`, `sap` entries — §1.4)
- [ ] `config/database.php` (`sap_sql` connection — §1.5)
- [ ] Migrations: `create_roles_table`, `create_permissions_table`, `create_permission_role_table`, `create_role_user_table`, `create_projects_cache_table` (optional local project selector cache), `add_division_and_project_scope_to_users_table`
- [ ] Models: `app/Models/User.php` (extended), `app/Models/Role.php`, `app/Models/Permission.php`
- [ ] `app/Services/Arkfleet/ArkfleetClient.php`
- [ ] `app/Services/Arkfleet/EquipmentCache.php`
- [ ] `app/Services/Arkfleet/ArkfleetResponseNormalizer.php`
- [ ] `app/Http/Middleware/EnsureProjectScope.php` (registered in `bootstrap/app.php`)
- [ ] `app/Http/Middleware/EnsureRole.php` + `EnsurePermission.php` (or a single `EnsureAbility.php`) registered as route middleware aliases in `bootstrap/app.php`
- [ ] `app/Policies/RolePolicy.php`
- [ ] `app/Http/Controllers/Auth/*` (Sanctum SPA login/logout, leveraging Laravel's `laravel/breeze`-style controllers if scaffolded, or hand-rolled per Inertia starter kit)
- [ ] `app/Http/Controllers/Admin/{ProjectController,UserController,RoleController}.php`
- [ ] `database/seeders/{RoleSeeder,PermissionSeeder,ProjectSeeder,DemoUserSeeder}.php` + `DatabaseSeeder.php` wiring
- [ ] `resources/js/app.tsx`, `resources/js/ssr.tsx` (optional SSR, skip for on-prem internal app unless required)
- [ ] `resources/js/Layouts/AppLayout.tsx` (role-aware sidebar)
- [ ] `resources/js/Hooks/useArkfleet.ts`
- [ ] `resources/js/Pages/Auth/Login.tsx`
- [ ] `resources/js/Pages/Dashboard.tsx` (placeholder landing, role-aware widgets added incrementally per phase)
- [ ] `routes/web.php`, `routes/api.php` skeleton (auth + admin routes only at this phase)
- [ ] `bootstrap/app.php` middleware registration
- [ ] Horizon installed (`php artisan horizon:install`) even though no jobs exist yet — needed by Phase 1+
- [ ] Reverb installed (`php artisan install:broadcasting --reverb`)

#### B. Migration Definitions

**Spatie permission tables** (published from package):

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
```

This publishes spatie's standard migrations: `create_permission_tables.php` (handles `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` in one file).

**One additional migration** — extend `model_has_roles` with project scoping:

```php
// database/migrations/xxxx_add_project_code_to_model_has_roles_table.php
Schema::table('model_has_roles', function (Blueprint $table) {
    $table->string('project_code', 20)->nullable()->after('model_type');
    $table->index('project_code');
});
```

> This adds a nullable `project_code` column to spatie's `model_has_roles` pivot. A user can have the same role in multiple projects (different `project_code` values per row), or a null scope for global roles (Finance Director, President Director). spatie's `setPermissionsTeamId($projectCode)` uses this via the `team_foreign_key` config.

**Users table extension:**

```php
// database/migrations/xxxx_add_division_and_scope_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->string('employee_no')->nullable()->unique()->after('email');
    $table->enum('division', ['plant', 'aml', 'procurement', 'finance', 'directorate', 'it'])->nullable()->after('employee_no');
    $table->string('project_code_scope', 20)->nullable()->after('division');
    $table->boolean('is_active')->default(true)->after('project_code_scope');
});
```

#### C. Model Definitions

Spatie provides `Role` and `Permission` models via `Spatie\Permission\Models\Role` and `Spatie\Permission\Models\Permission`. No custom Role/Permission model files needed.

**User model** — add spatie's `HasRoles` trait, remove hand-rolled methods:

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'employee_no',
        'division', 'project_code_scope', 'is_active',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

**No custom `hasRole()` / `hasPermission()` methods** — spatie provides `$user->hasRole('plant_manager')`, `$user->can('submit plant-request')`, and `$user->getAllPermissions()` natively.

**`config/permission.php`** — configure Teams for project scoping:
```php
// config/permission.php
'team_foreign_key' => 'project_code',
```

> spatie's `setPermissionsTeamId($projectCode)` uses this key to scope `hasRole()` / `can()` / `assignRole()` to a specific project. The `EnsureProjectScope` middleware (see §D) sets the team ID per request.

#### D. Controller Methods

| Controller | Method | Purpose |
|---|---|---|
| `Auth\AuthenticatedSessionController` | `create()`, `store()`, `destroy()` | Sanctum SPA session login/logout (standard Breeze-style, hand-rolled or via `laravel/fortify` if preferred — Fortify not required, keep it minimal) |
| `Admin\ProjectController` | `index()`, `sync()` | List projects pulled live from ARKFLEET (`GET /projects`), `sync()` refreshes the local `projects_cache` table used for dropdowns/scoping |
| `Admin\UserController` | `index()`, `store()`, `update()`, `destroy()`, `assignRole()` | User CRUD + role/project-scope assignment (IT Manager only) |
| `Admin\RoleController` | `index()`, `store()`, `update()`, `syncPermissions()` | Role/permission matrix maintenance |
| `DashboardController` | `index()` | Role-aware landing page redirect logic per `docs/concept.md` §9.1 (Planner → request dashboard, Finance Director → budget console, etc.) |

#### E. Service Classes

**`app/Services/Arkfleet/ArkfleetClient.php`**
- Purpose: single Guzzle-backed client for all ARKFLEET REST calls.
- Public methods: `getEquipment(array $filters = []): array`, `getEquipmentById(int $id): array`, `getEquipmentStats(?string $projectCode = null): array`, `getHmKmReadings(int $equipmentId, array $filters = []): array`, `getProjects(array $filters = []): array`, `getProject(string $code): array`, `getPlantTypes(): array`, `getUnitStatuses(): array`, `getAssetCategories(): array`.
- Dependencies: `GuzzleHttp\Client` (constructed from `config('services.arkfleet')`), `ArkfleetResponseNormalizer`.
- Every method wraps the Guzzle call in try/catch for `ConnectException`/`RequestException`, logs on failure, and lets the caller (`EquipmentCache`) decide the cache-fallback behavior.

**`app/Services/Arkfleet/ArkfleetResponseNormalizer.php`**
- Purpose: solve the confirmed inconsistency in `docs/concept.md` §8.3 — `projects`, `fixed-assets`, `depreciation/runs`, `depreciation/entries` **index** endpoints return a **raw paginator** (no `{data}` wrapper), while everything else (`equipment`, `plant-types`, `unit-statuses`, `asset-categories`, and all **show/detail** endpoints) returns `{data: ...}`.
- Public method: `normalize(ResponseInterface $response): array` — returns `['data' => ..., 'meta' => ...|null]` regardless of input shape.

```php
public function normalize(ResponseInterface $response): array
{
    $body = json_decode((string) $response->getBody(), true) ?? [];

    if (is_array($body) && array_key_exists('data', $body)) {
        return ['data' => $body['data'], 'meta' => $body['meta'] ?? null];
    }

    // Raw paginator shape: {current_page, data, last_page, per_page, total, ...}
    if (is_array($body) && array_key_exists('current_page', $body)) {
        return [
            'data' => $body['data'] ?? [],
            'meta' => [
                'current_page' => $body['current_page'],
                'last_page'    => $body['last_page'],
                'per_page'     => $body['per_page'],
                'total'        => $body['total'],
            ],
        ];
    }

    return ['data' => $body, 'meta' => null];
}
```

**`app/Services/Arkfleet/EquipmentCache.php`**
- Purpose: Redis-backed caching layer per `docs/concept.md` §8.4.
- Public methods: `list(array $filters = []): array` (cache key `ark:equipment:{project_code}:{md5(filters)}`, TTL 1h), `find(int $id): array` (`ark:equipment:id:{id}`, TTL 6h), `stats(?string $projectCode): array` (`ark:equipment:stats:{project_code}`, TTL 30min), `bust(int $id): void`, `bustProject(string $projectCode): void`.
- On ARKFLEET failure: serve last-known Redis value with a `stale: true` flag in the returned array (consumed by the frontend to render a staleness banner per §8.9).
- A scheduled command `app/Console/Commands/WarmArkfleetCache.php` runs daily at 06:00 WITA (registered in `routes/console.php` via `Schedule::command(...)->dailyAt('06:00')->timezone('Asia/Makassar')`) to pre-warm active equipment per active project.

#### F. Jobs

None required in Phase 0 (cache warm-up runs as a scheduled Artisan command, not a queued job, since it's a simple read-through operation). Horizon and Reverb are installed now so Phase 1+ jobs have infrastructure ready.

#### G. Policies

- No Policy classes needed in Phase 0 — spatie handles role/permission authorization via its built-in `role:` and `permission:` middleware and `Gate::before()` callback. If a custom `RolePolicy` is needed later for UI-level role-management gating, add it then.
- No domain policies yet (added per-phase as models are introduced).

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/app.tsx` | Inertia + React root, AntD `ConfigProvider` with `id_ID` locale + custom theme tokens | `ConfigProvider`, `App` (AntD static message/notification holder) |
| `resources/js/Layouts/AppLayout.tsx` | Role-aware sidebar (menu items filtered via `usePage().props.auth.can` array of permission names), header with notification bell (Reverb) | `Layout`, `Menu`, `Avatar`, `Badge`, `Dropdown` |
| `resources/js/Pages/Auth/Login.tsx` | Login form | `Form`, `Input`, `Button`, `Card` |
| `resources/js/Pages/Dashboard.tsx` | Placeholder role-aware landing widgets | `Row`, `Col`, `Statistic`, `Card` |
| `resources/js/Pages/Admin/Projects.tsx` | Project list (from ARKFLEET) + sync button | `ProTable` |
| `resources/js/Pages/Admin/Users.tsx` | User list + role/project-scope assignment modal (using spatie's `$user->assignRole()`, `$user->syncRoles()`, `$user->givePermissionTo()`) | `ProTable`, `Modal`, `Form`, `Select` (multi, tag mode for roles) |
| `resources/js/Pages/Admin/Roles.tsx` | Role list + permission assignment matrix | `ProTable`, `Transfer` (shuttle for permissions), `Modal` |
| `resources/js/Hooks/useArkfleet.ts` | Client-side hook wrapping Inertia-fetched or API-fetched equipment/project lists with loading/stale state | — (hook, no UI) |

#### I. Middleware Registration

In `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\EnsureProjectScope::class,  // sets permissionsTeamId
    ]);

    $middleware->alias([
        'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);
})
```

**`app/Http/Middleware/EnsureProjectScope.php`:**

```php
class EnsureProjectScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $projectCode = $request->route('project_code')
                ?? $request->input('project_code')
                ?? session('current_project')
                ?? $user->project_code_scope;

            if ($projectCode) {
                setPermissionsTeamId($projectCode);
            }
        }
        return $next($request);
    }
}
```

> This makes all `$user->hasRole('plant_manager')` and `$user->can('submit plant-request')` calls automatically scoped to the current project. Routes use spatie's built-in `->middleware('role:plant_manager')` and `->middleware('permission:submit plant-request')`.

#### J. Routes

#### I. Routes

See consolidated [Full Route Table](#3-full-route-table) §3.1 (Auth & Admin block).

#### K. Tests

- `tests/Feature/Auth/AuthenticationTest.php` — login success/failure, inactive user blocked.
- `tests/Feature/Admin/RoleAssignmentTest.php` — IT Manager can assign roles via spatie's `assignRole()` / `syncRoles()`; non-IT-Manager forbidden (403 via `permission:manage roles` middleware).
- `tests/Feature/Admin/ProjectScopedRoleTest.php` — a user with `plant_manager` role scoped to Project A can access Project A pages but NOT Project B pages (assert `setPermissionsTeamId()` scoping works).
- `tests/Feature/Arkfleet/ArkfleetClientTest.php` — mocked Guzzle responses covering **both** the wrapped `{data}` shape and the raw paginator shape (projects/index); asserts `ArkfleetResponseNormalizer` returns a consistent `['data', 'meta']` shape in both cases.
- `tests/Feature/Arkfleet/EquipmentCacheTest.php` — cache hit/miss, TTL respected (using `Cache::shouldReceive` or fake Redis), stale-fallback behavior when ARKFLEET client throws `ConnectException`.

---

### Phase 1 — Budget Management (Ledger-Based)

**Goal:** Implement the CPA-grade, ledger-based budgeting backbone: 6-month rolling `budget_periods`, `budget_allocations` with derived balances, immutable `budget_ledgers`, an idempotent carry-forward job, and the Finance Director / Plant Division console UIs.

**Dependencies:** Phase 0 (auth, roles, ARKFLEET project list for `project_code`).

#### A. Deliverables Checklist

- [ ] Migrations: `create_budget_periods_table`, `create_budget_allocations_table`, `create_budget_ledgers_table`
- [ ] Models: `app/Models/BudgetPeriod.php`, `app/Models/BudgetAllocation.php`, `app/Models/BudgetLedger.php`
- [ ] `app/Services/Budget/BudgetEngine.php`
- [ ] `app/Services/Budget/VarianceCalculator.php`
- [ ] `app/Jobs/CarryForwardJob.php`
- [ ] `app/Console/Commands/RunCarryForward.php` (manual trigger wrapper for ops/testing)
- [ ] `app/Http/Controllers/BudgetController.php`
- [ ] `app/Http/Requests/{StoreBudgetAllocationRequest,ReviseBudgetAllocationRequest}.php`
- [ ] `app/Policies/BudgetAllocationPolicy.php`
- [ ] `resources/js/Pages/Budget/Index.tsx` (Finance Director editable console + Plant read-only view, role-gated in the same page component)
- [ ] `resources/js/Pages/Budget/Setting.tsx` (create new budget period / bulk allocation entry)
- [ ] `resources/js/Components/BudgetProgressBar.tsx`
- [ ] `database/factories/{BudgetPeriodFactory,BudgetAllocationFactory,BudgetLedgerFactory}.php`
- [ ] Scheduled entry in `routes/console.php`: `Schedule::job(new CarryForwardJob())->monthlyOn(1, '01:00')->timezone('Asia/Makassar');`

#### B. Migration Definitions

```php
// create_budget_periods_table
Schema::create('budget_periods', function (Blueprint $table) {
    $table->id();
    $table->string('project_code', 20);              // FK ARKFLEET projects (no local FK constraint — external system)
    $table->string('project_name_cache');
    $table->date('period_month');                     // always normalized to 1st of month
    $table->enum('status', ['draft', 'open', 'locked', 'closed'])->default('draft');
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();

    $table->unique(['project_code', 'period_month']);
    $table->index(['status', 'period_month']);
});

// create_budget_allocations_table
Schema::create('budget_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('equipment_id')->nullable();  // FK ARKFLEET equipment; null = division-level allocation
    $table->string('unit_code_cache')->nullable();
    $table->enum('plant_type_cache', ['DIGGER', 'HAULER', 'SUPPORT'])->nullable();
    $table->decimal('allocated_amount', 18, 2)->default(0);
    $table->decimal('tolerance_pct', 5, 2)->default(10.00);
    $table->decimal('carry_forward_in', 18, 2)->default(0);
    $table->decimal('committed_amount', 18, 2)->default(0);   // derived cache, recomputed from ledger — never hand-edited
    $table->decimal('actual_amount', 18, 2)->default(0);      // derived cache, recomputed from ledger — never hand-edited
    $table->boolean('is_editable')->default(false);           // true only while period.status = 'open' AND period_month = current month (or future, for Finance Director)
    $table->timestamps();

    $table->index(['budget_period_id', 'equipment_id']);
});

// create_budget_ledgers_table
Schema::create('budget_ledgers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('budget_allocation_id')->constrained()->cascadeOnDelete();
    $table->enum('entry_type', ['allocation', 'commitment', 'actual', 'carry_forward', 'reversal', 'overbudget']);
    $table->decimal('amount', 18, 2);                  // SIGNED — positive increases available budget, negative consumes it
    $table->string('ref_type')->nullable();             // 'plant_request'|'po'|'grpo'|'cancellation'|null
    $table->unsignedBigInteger('ref_id')->nullable();
    $table->text('memo')->nullable();
    $table->foreignId('posted_by')->constrained('users');
    $table->timestamp('posted_at');
    $table->timestamps();

    $table->index(['budget_allocation_id', 'entry_type']);
    $table->index(['ref_type', 'ref_id']);
});
```

> **No `deleted_at` / soft-delete on `budget_ledgers`.** Ledger rows are permanent. Corrections are always a new `reversal` row referencing the original via `ref_type`/`ref_id`, never an update or delete (Pitfall: ledger immutability, `docs/concept.md` §3.2).

#### C. Model Definitions

```php
// app/Models/BudgetPeriod.php
class BudgetPeriod extends Model
{
    protected $fillable = ['project_code', 'project_name_cache', 'period_month', 'status', 'created_by'];
    protected $casts = ['period_month' => 'date:Y-m-d'];

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->whereDate('period_month', now()->startOfMonth());
    }

    public function scopeRollingWindow(Builder $query, string $projectCode): Builder
    {
        // previous 1 + current + next 4 = 6-month rolling window per concept.md §4.1
        return $query->where('project_code', $projectCode)
            ->whereBetween('period_month', [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->addMonthsNoOverflow(4)->startOfMonth(),
            ]);
    }

    public function isEditableBy(User $user): bool
    {
        if ($this->status === 'locked' || $this->status === 'closed') {
            return false;
        }
        $isCurrentOrFuture = $this->period_month->greaterThanOrEqualTo(now()->startOfMonth());
        if ($user->hasRole('finance_director')) {
            return $isCurrentOrFuture;
        }
        return $this->period_month->isSameMonth(now());
    }
}

// app/Models/BudgetAllocation.php
class BudgetAllocation extends Model
{
    protected $fillable = [
        'budget_period_id', 'equipment_id', 'unit_code_cache', 'plant_type_cache',
        'allocated_amount', 'tolerance_pct', 'carry_forward_in', 'committed_amount',
        'actual_amount', 'is_editable',
    ];
    protected $casts = [
        'allocated_amount' => 'decimal:2', 'tolerance_pct' => 'decimal:2',
        'carry_forward_in' => 'decimal:2', 'committed_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2', 'is_editable' => 'boolean',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(BudgetLedger::class);
    }

    public function getToleranceCapAttribute(): string
    {
        $base = $this->allocated_amount + $this->carry_forward_in;
        return bcmul($base, bcadd('1', bcdiv($this->tolerance_pct, '100', 4), 4), 2);
    }

    public function getVarianceAttribute(): string
    {
        // positive = under budget, negative = over budget
        return bcsub(
            bcadd($this->allocated_amount, $this->carry_forward_in, 2),
            bcadd($this->committed_amount, $this->actual_amount, 2),
            2
        );
    }

    public function getUtilizationPctAttribute(): string
    {
        $base = bcadd($this->allocated_amount, $this->carry_forward_in, 2);
        if (bccomp($base, '0', 2) === 0) {
            return '0.00';
        }
        return bcmul(bcdiv(bcadd($this->committed_amount, $this->actual_amount, 2), $base, 4), '100', 2);
    }
}

// app/Models/BudgetLedger.php
class BudgetLedger extends Model
{
    protected $fillable = ['budget_allocation_id', 'entry_type', 'amount', 'ref_type', 'ref_id', 'memo', 'posted_by', 'posted_at'];
    protected $casts = ['amount' => 'decimal:2', 'posted_at' => 'datetime'];

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    protected static function booted(): void
    {
        static::saving(function (BudgetLedger $ledger) {
            if ($ledger->exists) {
                throw new \RuntimeException('budget_ledgers rows are immutable. Post a reversal entry instead of updating.');
            }
        });
    }
}
```

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `BudgetController::index` | `GET /budget` | `auth`, `EnsureProjectScope` | Renders `Budget/Index` — 6-month rolling view; Finance Director sees editable current+future cells, everyone else sees read-only progress bars |
| `BudgetController::store` | `POST /budget/periods` | `auth`, `can:manage,BudgetPeriod` | Create a new `budget_period` (+ optional bulk allocation rows) for a project/month; Finance Director only |
| `BudgetController::updateAllocation` | `PATCH /budget/allocations/{allocation}` | `auth`, `can:update,allocation` | Revise `allocated_amount`/`tolerance_pct` for current/future month; posts `reversal` + new `allocation` ledger pair (never overwrites `allocated_amount` directly — see §4.1 business rule) |
| `BudgetController::variance` | `GET /budget/variance` | `auth` | JSON/Inertia partial reload for variance report widget (project/month/equipment filters) |
| `BudgetController::runCarryForward` | `POST /budget/carry-forward/run` | `auth`, `can:manage,BudgetPeriod` | Manual/admin trigger of `CarryForwardJob` for a specific period (idempotency guarded — safe to click twice) |

#### E. Service Classes

**`app/Services/Budget/BudgetEngine.php`**
- Purpose: single entry point for every mutation of budget state — **all writes to `budget_allocations.committed_amount`/`actual_amount` happen only by replaying `budget_ledgers`, never by direct column assignment from a controller.**
- Public methods:
  - `createAllocation(BudgetPeriod $period, array $data, User $actor): BudgetAllocation` — creates the allocation row + posts the initial `allocation` ledger entry.
  - `reviseAllocation(BudgetAllocation $allocation, string $newAmount, User $actor, string $memo): BudgetAllocation` — guards via `$period->isEditableBy($actor)`; posts a `reversal` entry for the old `allocated_amount` then a new `allocation` entry; **never** does `$allocation->update(['allocated_amount' => ...])` directly.
  - `postCommitment(BudgetAllocation $allocation, string $amount, string $refType, int $refId, User $actor): BudgetLedger` — called by Phase 2 on Plant Request submission (negative-signed amount).
  - `reverseCommitment(BudgetAllocation $allocation, string $refType, int $refId, User $actor, string $reason): BudgetLedger` — called on rejection/cancellation (Phase 2/6); posts a positive-signed `reversal` referencing the original `plant_request`/`po` ref.
  - `postActual(BudgetAllocation $allocation, string $amount, int $sapGrpoRef, User $actor): BudgetLedger` — called by Phase 4's `ReconcileGrpoToLedger` job; converts `commitment` into `actual` (posts `actual` entry, and a matching `reversal` of the equivalent `commitment` amount so a line item isn't double counted).
  - `postOverbudget(BudgetAllocation $allocation, string $amount, int $overbudgetRequestId, User $actor): BudgetLedger` — called by Phase 6 on Overbudget approval.
  - `recomputeCachedBalances(BudgetAllocation $allocation): void` — sums `budget_ledgers` by `entry_type` and writes the derived `committed_amount`/`actual_amount`/`carry_forward_in` cache columns. This is the **only** place those columns are written, and it is always derived, never authoritative (the ledger is authoritative).
  - `validateAgainstTolerance(BudgetAllocation $allocation, string $additionalAmount): array` — returns `['within_tolerance' => bool, 'projected_pct' => string, 'cap' => string]`; used by Phase 2's 110% submit-time validation.
- Dependencies: `BudgetLedger`, `BudgetAllocation`, wrapped in `DB::transaction()` for every posting method (ledger insert + `recomputeCachedBalances` must be atomic).

**`app/Services/Budget/VarianceCalculator.php`**
- Purpose: read-side reporting helper, no writes.
- Public methods: `forAllocation(BudgetAllocation $allocation): array` (returns allocated/carry-forward/committed/actual/variance/utilization_pct), `forProject(string $projectCode, Carbon $month): Collection`, `forPlantType(string $projectCode, string $plantType, Carbon $month): array`.

#### F. Jobs

**`app/Jobs/CarryForwardJob.php`**
- Trigger: scheduled `Schedule::job(new CarryForwardJob())->monthlyOn(1, '01:00')->timezone('Asia/Makassar')` in `routes/console.php`; also manually dispatchable via `BudgetController::runCarryForward`.
- Idempotency key: `carry_forward:{budget_allocation_id}:{period_month}` — checked via a `budget_ledgers` existence query (`entry_type = 'carry_forward' AND ref_type = 'budget_period' AND ref_id = <new_period_id>`) before posting; if found, skip (safe to re-run).
- `handle()` logic:
  1. For each `BudgetPeriod` with `status = 'open'` whose `period_month` is the month that just ended, compute `variance = allocated + carry_forward_in - committed - actual` per allocation via `VarianceCalculator`.
  2. If `variance > 0`, find or create the corresponding `budget_allocation` in the **next** month's `budget_period` (same `project_code` + `equipment_id`), and call `BudgetEngine::postCommitment`... actually post a `carry_forward` entry with `amount = variance` on the **next** period's allocation.
  3. Mark the just-ended period `status = 'locked'`.
  4. Wrap steps 1–3 per-allocation in `DB::transaction()`; log failures per allocation without aborting the whole batch (one bad allocation must not block carry-forward for the rest of the division).
- Uses `$this->onQueue('budget')` in the constructor (never `public $queue` — Pitfall P6).

#### G. Policies

**`app/Policies/BudgetAllocationPolicy.php`**
- `view(User $user, BudgetAllocation $allocation)` — true for any authenticated, project-scoped user (read access is universal per role matrix §6, everyone gets at least 👁).
- `update(User $user, BudgetAllocation $allocation)` — true only if `$user->hasRole('finance_director')` **and** `$allocation->period->isEditableBy($user)`.
- `manage(User $user, ?BudgetPeriod $period = null)` (used for `create`/period-level actions) — true only for `finance_director`.

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/Budget/Index.tsx` | 6-month tab view; Finance Director sees inline-editable cells for current+future; others see read-only progress bars; locked months visually greyed | `ProTable`, `Tabs`, `InputNumber` (inline edit), `Tag` |
| `resources/js/Pages/Budget/Setting.tsx` | Create budget period + bulk allocation entry (project/equipment picker sourced from ARKFLEET via `useArkfleet`) | `ProForm`, `ProFormList` (per-equipment rows), `Select` |
| `resources/js/Components/BudgetProgressBar.tsx` | Reusable progress bar: green <90%, amber 90–110%, red >110% | `Progress`, `Tooltip` |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.2.

#### J. Tests

- `tests/Feature/Budget/BudgetAllocationTest.php` — Finance Director can create/revise current+future allocations; non-Finance-Director gets 403; past/locked month edits rejected at policy layer.
- `tests/Feature/Budget/BudgetLedgerImmutabilityTest.php` — attempting `BudgetLedger::find($id)->update(...)` throws `RuntimeException`; corrections must go through `BudgetEngine::reviseAllocation` (reversal + new entry), verified by asserting two new ledger rows exist and the original is untouched.
- `tests/Feature/Budget/CarryForwardJobTest.php` — running the job twice for the same period produces only one `carry_forward` ledger entry (idempotency); under-spent allocation carries the correct signed variance to next month; over-spent allocation carries zero (never negative carry-forward).
- `tests/Feature/Budget/VarianceCalculatorTest.php` — variance = allocated + carry_forward - committed - actual, verified against a seeded ledger with mixed entry types.
- `tests/Feature/Budget/ToleranceValidationTest.php` — `validateAgainstTolerance` correctly flags amounts crossing the 110% cap; configurable `tolerance_pct` per allocation respected (e.g. 15% for a critical DIGGER unit).
- `database/factories/BudgetLedgerFactory.php` — factory states for each `entry_type` to support the above tests.

---

### Phase 2 — Plant Request + Approval Workflow

**Goal:** Let a Planner request parts against budget with a mandatory SAP MR link, cascading price estimation, enforced ≤110% validation, and a two-step (Project Manager → Plant Manager) approval chain via the polymorphic `request_approvals` engine.

**Dependencies:** Phase 0 (auth/roles/ARKFLEET equipment selector), Phase 1 (`BudgetEngine::postCommitment`/`validateAgainstTolerance`), a working SAP **read** connection for MR line lookup (Phase 4 formalizes the full SAP service layer, but Phase 2 needs at minimum a read-only `SapReadRepository::getMaterialRequest()` stub — build the minimal version here and let Phase 4 harden it).

#### A. Deliverables Checklist

- [ ] Migrations: `create_plant_requests_table`, `create_plant_request_lines_table`, `create_request_approvals_table`, `create_request_comments_table`
- [ ] Models: `app/Models/PlantRequest.php`, `app/Models/PlantRequestLine.php`, `app/Models/RequestApproval.php`, `app/Models/RequestComment.php`
- [ ] `app/Models/Sap/MaterialRequest.php` (minimal read model, `connection = 'sap_sql'`)
- [ ] `app/Services/Approval/ApprovalEngine.php`
- [ ] `app/Services/Pricing/PricingEstimator.php`
- [ ] `app/Http/Controllers/PlantRequestController.php`
- [ ] `app/Http/Controllers/ApprovalController.php` (generic polymorphic approve/reject/return actions, reused by Phase 3/6/8)
- [ ] `app/Http/Requests/{StorePlantRequestRequest,SubmitPlantRequestRequest,ApprovalDecisionRequest}.php`
- [ ] `app/Policies/PlantRequestPolicy.php`
- [ ] `app/Notifications/{PlantRequestSubmitted,PlantRequestApprovalNeeded,PlantRequestDecided}.php` (Reverb broadcast + optional mail)
- [ ] `resources/js/Pages/PlantRequest/{Index,Create,Show}.tsx`
- [ ] `resources/js/Components/{LifecycleStepper,ApprovalQueue}.tsx`
- [ ] `database/factories/{PlantRequestFactory,PlantRequestLineFactory,RequestApprovalFactory}.php`
- [ ] `database/seeders/RequestApprovalChainSeeder.php` (seeds the step_order/required_role templates per approvable_type — see §E)

#### B. Migration Definitions

```php
// create_plant_requests_table
Schema::create('plant_requests', function (Blueprint $table) {
    $table->id();
    $table->string('request_no', 30)->unique();          // PMB-REQ-YYYYMM-####
    $table->foreignId('budget_allocation_id')->constrained();
    $table->unsignedBigInteger('equipment_id');            // FK ARKFLEET equipment
    $table->string('unit_code_cache');
    $table->foreignId('dmbd_entry_id')->nullable()->constrained('dmbd_entries');
    $table->unsignedBigInteger('sap_mr_id');                // FK SAP MR (DocEntry) — required
    $table->string('sap_pr_no')->nullable();                 // set once PR created (Phase 4)
    $table->enum('status', [
        'draft', 'pending_pm', 'pending_plant_mgr', 'approved',
        'pr_created', 'po_created', 'received', 'cancelled', 'rejected',
    ])->default('draft');
    $table->decimal('estimated_total', 18, 2)->default(0);
    $table->decimal('budget_utilization_pct', 5, 2)->nullable();  // snapshot at submission time
    $table->foreignId('requested_by')->constrained('users');
    $table->timestamp('submitted_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'budget_allocation_id']);
    $table->index('sap_mr_id');
});

// create_plant_request_lines_table
Schema::create('plant_request_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('plant_request_id')->constrained()->cascadeOnDelete();
    $table->string('part_number');
    $table->string('material_name');
    $table->string('uom', 10);
    $table->unsignedInteger('qty');
    $table->decimal('unit_price_est', 18, 2)->default(0);
    $table->enum('price_source', ['tabulation_bid', 'sap_price', 'manual', 'none'])->default('none');
    $table->foreignId('interchange_map_id')->nullable()->constrained('interchange_maps');
    $table->decimal('line_total', 18, 2)->default(0);
    $table->timestamps();

    $table->index('plant_request_id');
});

// create_request_approvals_table
Schema::create('request_approvals', function (Blueprint $table) {
    $table->id();
    $table->string('approvable_type');    // 'PlantRequest'|'TabulationBid'|'OverbudgetRequest'|'CannibalRequest'|'CancellationRequest'
    $table->unsignedBigInteger('approvable_id');
    $table->unsignedInteger('step_order');
    $table->string('required_role');       // e.g. 'project_manager'
    $table->foreignId('approver_id')->nullable()->constrained('users');
    $table->enum('decision', ['pending', 'approved', 'rejected', 'returned'])->default('pending');
    $table->text('remarks')->nullable();
    $table->timestamp('acted_at')->nullable();
    $table->timestamps();

    $table->index(['approvable_type', 'approvable_id', 'step_order']);
});

// create_request_comments_table
Schema::create('request_comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('plant_request_id')->constrained()->cascadeOnDelete();
    $table->enum('category', ['delay', 'indent', 'constraint', 'general']);
    $table->text('body');
    $table->foreignId('author_id')->constrained('users');
    $table->timestamps();
});
```

#### C. Model Definitions

```php
// app/Models/PlantRequest.php
class PlantRequest extends Model
{
    protected $fillable = [
        'request_no', 'budget_allocation_id', 'equipment_id', 'unit_code_cache',
        'dmbd_entry_id', 'sap_mr_id', 'sap_pr_no', 'status', 'estimated_total',
        'budget_utilization_pct', 'requested_by', 'submitted_at',
    ];
    protected $casts = [
        'estimated_total' => 'decimal:2', 'budget_utilization_pct' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    public function lines(): HasMany { return $this->hasMany(PlantRequestLine::class); }
    public function allocation(): BelongsTo { return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id'); }
    public function approvals(): MorphMany { return $this->morphMany(RequestApproval::class, 'approvable'); }
    public function comments(): HasMany { return $this->hasMany(RequestComment::class); }

    protected static function booted(): void
    {
        static::creating(function (PlantRequest $r) {
            $r->request_no ??= sprintf('PMB-REQ-%s-%04d', now()->format('Ym'), static::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count() + 1);
        });
    }
}

// app/Models/PlantRequestLine.php
class PlantRequestLine extends Model
{
    protected $fillable = ['plant_request_id', 'part_number', 'material_name', 'uom', 'qty', 'unit_price_est', 'price_source', 'interchange_map_id', 'line_total'];
    protected $casts = ['unit_price_est' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function request(): BelongsTo { return $this->belongsTo(PlantRequest::class, 'plant_request_id'); }
    public function interchangeMap(): BelongsTo { return $this->belongsTo(InterchangeMap::class); }

    protected static function booted(): void
    {
        static::saving(fn (PlantRequestLine $l) => $l->line_total = bcmul((string) $l->qty, $l->unit_price_est, 2));
    }
}

// app/Models/RequestApproval.php
class RequestApproval extends Model
{
    protected $fillable = ['approvable_type', 'approvable_id', 'step_order', 'required_role', 'approver_id', 'decision', 'remarks', 'acted_at'];
    protected $casts = ['acted_at' => 'datetime'];

    public function approvable(): MorphTo { return $this->morphTo(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}

// app/Models/RequestComment.php
class RequestComment extends Model
{
    protected $fillable = ['plant_request_id', 'category', 'body', 'author_id'];
    public function request(): BelongsTo { return $this->belongsTo(PlantRequest::class, 'plant_request_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}

// app/Models/Sap/MaterialRequest.php — minimal Phase 2 stub, hardened in Phase 4
class MaterialRequest extends Model
{
    protected $connection = 'sap_sql';
    protected $table = 'OPRQ';         // SAP B1 Purchase/Material Request header table
    protected $primaryKey = 'DocEntry';
    public $timestamps = false;
    protected $guarded = ['*'];        // read-only — never save()/create()/update() through this model
}
```

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `PlantRequestController::index` | `GET /plant-requests` | `auth`, `EnsureProjectScope` | List with lifecycle stepper column, filterable by status/project/equipment |
| `PlantRequestController::create` | `GET /plant-requests/create` | `auth`, `can:create,PlantRequest` | Renders the wizard (equipment select → MR link → lines → budget impact review) |
| `PlantRequestController::store` | `POST /plant-requests` | `auth`, `can:create,PlantRequest` | Persists as `draft`; does NOT post a ledger commitment yet |
| `PlantRequestController::show` | `GET /plant-requests/{plantRequest}` | `auth`, `can:view,plantRequest` | Detail + lifecycle stepper + comments thread |
| `PlantRequestController::submit` | `POST /plant-requests/{plantRequest}/submit` | `auth`, `can:update,plantRequest` | Runs `BudgetEngine::validateAgainstTolerance`; if ≤110%, calls `ApprovalEngine::initiate()` and posts the `commitment` ledger entry; if >110%, redirects to Overbudget flow (Phase 6) instead of erroring |
| `PlantRequestController::addComment` | `POST /plant-requests/{plantRequest}/comments` | `auth` | Categorized delay/indent/constraint/general comment |
| `ApprovalController::decide` | `POST /approvals/{requestApproval}/decide` | `auth`, `can:decide,requestApproval` | Generic `{decision: approved|rejected|returned, remarks}` handler; on `approved` at the final step, calls the approvable's `onFullyApproved()` hook (for `PlantRequest`, this notifies Logistic Foreman for stock check per `docs/concept.md` §5.1) |

#### E. Service Classes

**`app/Services/Approval/ApprovalEngine.php`**
- Purpose: drive the polymorphic `request_approvals` chain for any `approvable_type`. This is the single engine reused by Plant Request (Phase 2), Tabulation Bid (Phase 3), Overbudget/Cancellation (Phase 6), and Cannibal (Phase 8).
- Public methods:
  - `initiate(Model $approvable, array $chain): void` — `$chain` is an ordered array of `['step_order' => 1, 'required_role' => 'project_manager']`, ...; creates all `request_approvals` rows as `pending`, sets step 1 `approver_id = null` (resolved at decision time by role+project scope), and updates `$approvable->status` to the first pending state.
  - `decide(RequestApproval $approval, User $actor, string $decision, ?string $remarks): void` — validates `$actor` holds `required_role` (project-scoped); records decision + `acted_at`; on `approved` advances `$approvable` to the next step's pending status; on `rejected`/`returned` sets `$approvable->status` back to `draft` (or a rejected terminal state) and — critically — calls `BudgetEngine::reverseCommitment()` if a commitment had been posted.
  - `currentStep(Model $approvable): ?RequestApproval` — the first `pending` row ordered by `step_order`.
  - `isFullyApproved(Model $approvable): bool`.
- Chain templates (seeded via `RequestApprovalChainSeeder`, referenced by `docs/concept.md` §5): `PlantRequest` → `[project_manager, plant_manager]`; `TabulationBid` → `[procurement_manager]` (Admin "Create PO" is a separate gate, not a `request_approvals` step — see Phase 3) plus `[president_director]` for the PO approval; `OverbudgetRequest` → `[finance_director, operation_director]`; `CancellationRequest` → `[procurement_manager]` (Plant+Procurement agreement modeled as a single combined step — see Phase 6 detail); `CannibalRequest` → `[plant_manager, aml_manager, operation_director, president_director]`.

**`app/Services/Pricing/PricingEstimator.php`**
- Purpose: implement the 4-tier pricing cascade from `docs/concept.md` §4.2.
- Public method: `estimate(string $partNumber): array` returns `['unit_price' => string, 'source' => 'tabulation_bid'|'sap_price'|'manual'|'none']`.
- Cascade logic: (1) latest **awarded** `tabulation_bid_award` price for a vendor line matching `$partNumber` (via `TabulationBidVendor`, Phase 3); (2) SAP price list cache (`Sap\PriceList`, Phase 4, Redis-cached daily); (3) if neither found, return `source = none` and dispatch a notification job to Procurement (`app/Notifications/PricingGapDetected.php`); manual entry (`source = manual`) is set explicitly by the Planner in the UI, not by this service.

#### F. Jobs

No queued jobs strictly required in Phase 2 itself (submission is synchronous — DB transaction + ledger post). `PlantRequestSubmitted`/`PlantRequestApprovalNeeded` notifications are queued (`ShouldQueue`) for Reverb broadcast + optional email, using `$this->onQueue('notifications')`.

#### G. Policies

**`app/Policies/PlantRequestPolicy.php`**
- `create(User $user)` — `planner` or `mechanic` role.
- `view(User $user, PlantRequest $r)` — project-scoped read access for all roles per matrix.
- `update(User $user, PlantRequest $r)` — only `$r->requested_by === $user->id` and `$r->status === 'draft'`.
- `submit(User $user, PlantRequest $r)` — same as `update`, plus `$r->lines()->count() > 0` and `$r->sap_mr_id` is set.
- `decide(User $user, RequestApproval $approval)` (shared, used across all approvable types) — `$user->hasRole($approval->required_role, $approval->approvable->project_code ?? null)` and `$approval->decision === 'pending'` and it is the current step (`step_order` matches `ApprovalEngine::currentStep`).

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/PlantRequest/Index.tsx` | List with `LifecycleStepper` column, status filter chips | `ProTable`, `Tag`, `Steps` (compact) |
| `resources/js/Pages/PlantRequest/Create.tsx` | Wizard: select equipment (ARKFLEET, via `useArkfleet`) → link SAP MR (searchable) → add lines (auto price via `PricingEstimator` API call) → review budget impact meter → submit | `ProForm.StepsForm`, `Select` (async), `Table` (editable line items), `Progress` (budget impact meter) |
| `resources/js/Pages/PlantRequest/Show.tsx` | Detail, lifecycle stepper, approval timeline, categorized comment thread | `Descriptions`, `Steps`, `Timeline`, `Comment`/`List` |
| `resources/js/Components/LifecycleStepper.tsx` | Reusable MR→PR→PO→GRPO→Issued stepper, driven by `plant_request.status` + `sap_pr_no`/`sap_po_id` presence | `Steps` |
| `resources/js/Components/ApprovalQueue.tsx` | Reusable pending-approval table with inline approve/reject/return + remarks modal; reused in Phase 3/6/8 | `ProTable`, `Modal`, `Radio.Group`, `Input.TextArea` |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.3.

#### J. Tests

- `tests/Feature/PlantRequest/CreateSubmitTest.php` — draft creation, submit requires ≥1 line and `sap_mr_id`; submit posts exactly one `commitment` ledger entry.
- `tests/Feature/PlantRequest/ToleranceValidationTest.php` — submitting a request that would push cumulative committed >110% redirects to Overbudget flow instead of allowing normal submission (integration with Phase 6 stub — full flow tested end-to-end once Phase 6 lands).
- `tests/Feature/PlantRequest/ApprovalChainHappyPathTest.php` — Project Manager approves step 1 → status `pending_plant_mgr`; Plant Manager approves step 2 → status `approved`; Logistic notification dispatched.
- `tests/Feature/PlantRequest/ApprovalChainRejectionTest.php` — rejection at either step reverts status to `draft`/rejected terminal and reverses the ledger commitment (asserted via `budget_ledgers` reversal row).
- `tests/Feature/PlantRequest/ApprovalSegregationOfDutiesTest.php` — a user without the `required_role` for the current step gets 403 on `ApprovalController::decide`; a user with the role but scoped to a *different* `project_code` also gets 403.
- `tests/Feature/Pricing/PricingEstimatorCascadeTest.php` — awarded Tabulation Bid price takes priority over SAP price cache; falls back to `none` + Procurement notification when neither exists.
- `tests/Feature/PlantRequest/LifecycleStepperDataTest.php` — stepper renders correct step based on `status`/`sap_pr_no`/`sap_po_id` combinations (table-driven test over the full status enum).

---

### Phase 3 — Tabulation Bid + Auto PO

**Goal:** Standardize the 2–3 vendor comparison, gate it through Procurement Manager review and Procurement Admin's "Create PO" action, transmit the PO to SAP via a queued idempotent Service Layer job, and require President Director approval before the PO is sent.

**Dependencies:** Phase 2 (SAP PR must exist — `sap_pr_no` on the source `PlantRequest`, or a directly-referenced SAP PR id), Phase 1 (ledger — award doesn't move budget, but PO creation is tracked against the same allocation), a minimal SAP **write** client (hardened fully in Phase 4).

#### A. Deliverables Checklist

- [ ] Migrations: `create_tabulation_bids_table`, `create_tabulation_bid_vendors_table`, `create_tabulation_bid_awards_table`
- [ ] Models: `app/Models/TabulationBid.php`, `app/Models/TabulationBidVendor.php`, `app/Models/TabulationBidAward.php`
- [ ] `app/Services/Sap/SapWriteClient.php` (minimal — `createPurchaseOrder()` only; full client built out in Phase 4)
- [ ] `app/Jobs/CreateSapPurchaseOrder.php`
- [ ] `app/Http/Controllers/TabulationBidController.php`
- [ ] `app/Http/Requests/{StoreTabulationBidRequest,AwardVendorRequest,CreatePoRequest}.php`
- [ ] `app/Policies/TabulationBidPolicy.php`
- [ ] `app/Notifications/{TabulationBidReviewNeeded,PoCreationFailed,PoApprovalNeeded}.php`
- [ ] `resources/js/Pages/TabulationBid/{Index,Create,Review}.tsx`
- [ ] `resources/js/Components/VendorComparisonTable.tsx`
- [ ] `database/factories/{TabulationBidFactory,TabulationBidVendorFactory,TabulationBidAwardFactory}.php`

#### B. Migration Definitions

```php
// create_tabulation_bids_table
Schema::create('tabulation_bids', function (Blueprint $table) {
    $table->id();
    $table->string('bid_no', 30)->unique();      // PMB-BID-YYYYMM-####
    $table->string('sap_pr_id');                   // FK SAP PR (DocEntry as string for consistency w/ concept ERD)
    $table->enum('status', ['draft', 'pending_proc_mgr', 'forwarded_admin', 'po_created', 'closed'])->default('draft');
    $table->foreignId('created_by')->constrained('users');       // Buyer
    $table->foreignId('reviewed_by')->nullable()->constrained('users');  // Procurement Manager
    $table->string('sap_po_id')->nullable();
    $table->timestamps();

    $table->index('sap_pr_id');
    $table->index('status');
});

// create_tabulation_bid_vendors_table
Schema::create('tabulation_bid_vendors', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tabulation_bid_id')->constrained()->cascadeOnDelete();
    $table->string('vendor_code');           // SAP vendor master (OCRD.CardCode)
    $table->string('vendor_name');
    $table->decimal('price', 18, 2);
    $table->string('payment_terms')->nullable();
    $table->enum('stock_availability', ['ready', 'indent', 'partial']);
    $table->text('remarks')->nullable();
    $table->unsignedTinyInteger('rank')->nullable();
    $table->timestamps();

    $table->index('tabulation_bid_id');
});

// create_tabulation_bid_awards_table
Schema::create('tabulation_bid_awards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tabulation_bid_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tabulation_bid_vendor_id')->constrained();
    $table->text('justification')->nullable();     // mandatory if not lowest price — enforced in FormRequest, not DB
    $table->foreignId('awarded_by')->constrained('users');
    $table->timestamp('awarded_at');
    $table->timestamps();
});
```

#### C. Model Definitions

```php
// app/Models/TabulationBid.php
class TabulationBid extends Model
{
    protected $fillable = ['bid_no', 'sap_pr_id', 'status', 'created_by', 'reviewed_by', 'sap_po_id'];

    public function vendors(): HasMany { return $this->hasMany(TabulationBidVendor::class); }
    public function award(): HasOne { return $this->hasOne(TabulationBidAward::class); }
    public function approvals(): MorphMany { return $this->morphMany(RequestApproval::class, 'approvable'); }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    protected static function booted(): void
    {
        static::creating(fn (TabulationBid $b) => $b->bid_no ??= sprintf('PMB-BID-%s-%04d', now()->format('Ym'), static::whereYear('created_at', now()->year)->count() + 1));
    }
}

// app/Models/TabulationBidVendor.php
class TabulationBidVendor extends Model
{
    protected $fillable = ['tabulation_bid_id', 'vendor_code', 'vendor_name', 'price', 'payment_terms', 'stock_availability', 'remarks', 'rank'];
    protected $casts = ['price' => 'decimal:2'];
    public function bid(): BelongsTo { return $this->belongsTo(TabulationBid::class, 'tabulation_bid_id'); }
}

// app/Models/TabulationBidAward.php
class TabulationBidAward extends Model
{
    protected $fillable = ['tabulation_bid_id', 'tabulation_bid_vendor_id', 'justification', 'awarded_by', 'awarded_at'];
    protected $casts = ['awarded_at' => 'datetime'];
    public function vendor(): BelongsTo { return $this->belongsTo(TabulationBidVendor::class, 'tabulation_bid_vendor_id'); }
}
```

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `TabulationBidController::index` | `GET /tabulation-bids` | `auth` | List, filterable by status; Buyer sees own bids, Proc Mgr/Admin see review queue |
| `TabulationBidController::create` | `GET /tabulation-bids/create` | `auth`, `can:create,TabulationBid` | Select SAP PR (searchable, read via `SapReadRepository`), add 2–3 vendor rows |
| `TabulationBidController::store` | `POST /tabulation-bids` | `auth`, `can:create,TabulationBid` (Buyer role) | Validates min 2 / max 3 vendors; sets `status = pending_proc_mgr`; initiates `ApprovalEngine` with `[procurement_manager]` step |
| `TabulationBidController::review` | `GET /tabulation-bids/{tabulationBid}/review` | `auth`, `can:review,tabulationBid` | Proc Mgr comparison view |
| `TabulationBidController::award` | `POST /tabulation-bids/{tabulationBid}/award` | `auth`, `can:award,tabulationBid` | Records `TabulationBidAward`; **FormRequest rejects if `justification` empty and awarded vendor is not the lowest price** (fraud/governance control) |
| `TabulationBidController::createPo` | `POST /tabulation-bids/{tabulationBid}/create-po` | `auth`, `can:createPo,tabulationBid` (Procurement Admin only, and **not** the same user as `created_by` — SoD) | Dispatches `CreateSapPurchaseOrder` job with idempotency key `tabulation_bid_id`; sets `status = po_created` optimistically (job confirms/corrects) |
| `TabulationBidController::approvePo` | `POST /tabulation-bids/{tabulationBid}/approve-po` | `auth`, `can:decide` (President Director) | High-value PO approval gate before SAP marks it "Sent" |

#### E. Service Classes

**`app/Services/Sap/SapWriteClient.php`** (Phase 3 minimal slice — full client in Phase 4 `SapService`)
- Purpose: wraps the Service Layer `POST /Orders` (Purchase Order creation) call with cookie session handling.
- Public methods: `createPurchaseOrder(array $payload): array` — returns `['DocEntry' => ..., 'DocNum' => ...]` on success; throws `SapWriteException` on failure (caught by the job for retry).
- Built as (or delegating to, once Phase 4 lands) the **singleton** `SapService` — see Phase 4 §E for the full session-management implementation. Phase 3 should reference `app(SapService::class)` rather than instantiating its own Guzzle client, to avoid Pitfall "duplicate session management logic."

#### F. Jobs

**`app/Jobs/CreateSapPurchaseOrder.php`**
- Dispatch trigger: `TabulationBidController::createPo` after the SoD + award checks pass.
- Idempotency key: `sap_po_create:{tabulation_bid_id}` — implemented as a DB-backed unique constraint check (query `tabulation_bids.sap_po_id IS NOT NULL` before dispatch) **plus** a cache lock (`Cache::lock("sap-po-create-{$bidId}", 120)`) held for the duration of `handle()` to prevent double-dispatch races.
- `handle()` logic:
  1. Acquire lock; if already held, release job back to queue with delay (another worker is mid-creation).
  2. Re-check `sap_po_id` is still null (guards against a race that slipped past the lock).
  3. Build SAP `Orders` payload from `TabulationBid::award->vendor` (CardCode, DocDate, line items resolved from the source PR via `SapReadRepository`).
  4. Call `SapWriteClient::createPurchaseOrder()`.
  5. On success: `$bid->update(['sap_po_id' => $result['DocEntry']])`, dispatch `PoApprovalNeeded` notification to President Director.
  6. On failure: catch, log with full payload (redacted credentials), retry with `$this->backoff([10, 30, 90])` (3 attempts total), on final failure mark a `sap_sync_failed` flag (added as a boolean column via a small follow-up migration, or tracked in a generic `sap_sync_logs` table shared across Phase 4 jobs — prefer the shared table, see Phase 4 §B) and notify Procurement via `PoCreationFailed`.
- Constructor uses `$this->onQueue('sap-writes')` (never `public $queue`).

#### G. Policies

**`app/Policies/TabulationBidPolicy.php`**
- `create(User $user)` — `buyer` role.
- `review(User $user, TabulationBid $bid)` — `procurement_manager` role, and it is the current pending approval step for this bid.
- `award(User $user, TabulationBid $bid)` — `procurement_manager` or `procurement_admin`, and `$bid->status === 'forwarded_admin'`.
- `createPo(User $user, TabulationBid $bid)` — `procurement_admin` role **and** `$user->id !== $bid->created_by` (hard SoD enforcement — the Buyer who created the bid can never be the Admin who creates its PO, even if one person holds both roles) **and** `$bid->award()->exists()`.
- `decide(User $user, RequestApproval $approval)` — reused from Phase 2, generic role+step check; for the PO approval step, `required_role = president_director`.

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/TabulationBid/Index.tsx` | List/queue, status filter | `ProTable`, `Tag` |
| `resources/js/Pages/TabulationBid/Create.tsx` | SAP PR picker + 2–3 vendor entry rows | `ProForm`, `ProFormList` (min 2, max 3 items enforced client-side + server-side) |
| `resources/js/Pages/TabulationBid/Review.tsx` | Side-by-side vendor comparison, award action, "Create PO" button (disabled until award + role check) | `VendorComparisonTable`, `Modal` (justification textarea), `Button` |
| `resources/js/Components/VendorComparisonTable.tsx` | Reusable comparison grid: best price highlighted green, rank badges, stock availability chips | `Table` (columns per vendor), `Tag`, `Badge` |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.4.

#### J. Tests

- `tests/Feature/TabulationBid/CreateReviewAwardTest.php` — min 2/max 3 vendor validation; award without justification blocked when not lowest price; award with justification allowed.
- `tests/Feature/TabulationBid/SegregationOfDutiesTest.php` — the Buyer who created a bid cannot call `createPo` on it even if also holding `procurement_admin` role (403); a different Procurement Admin can.
- `tests/Feature/TabulationBid/CreateSapPurchaseOrderJobTest.php` — mocked `SapWriteClient`; job dispatch sets `sap_po_id` on success; failure triggers 3 retries with backoff then marks `sap_sync_failed`; **double-dispatch guarded** — dispatching the job twice for the same bid only results in one `POST /Orders` call (assert via Guzzle mock call count / `Http::fake()` assertion count).
- `tests/Feature/TabulationBid/PoApprovalGateTest.php` — PO cannot be marked "Sent" without President Director approval; non-PresDir decision attempt is 403.

---

### Phase 4 — SAP Integration (Read + Write + Sync)

**Goal:** Harden the SAP integration built minimally in Phases 2–3 into the full dual-channel architecture from `docs/concept.md` §7: singleton `SapService` with Guzzle CookieJar session management, direct SQL Server (`sqlsrv`) reads for complex joins, Service Layer OData for standard reads/writes, a sync health dashboard, and nightly reconciliation.

**Dependencies:** Phases 2–3 (consumers of SAP reads/writes already exist and must be migrated onto the hardened `SapService`).

#### A. Deliverables Checklist

- [ ] Models: `app/Models/Sap/{PurchaseRequest,PurchaseOrder,Grpo,VendorMaster,PriceList}.php` (all `connection = 'sap_sql'`, `$guarded = ['*']`)
- [ ] `app/Services/Sap/SapService.php` (the singleton — supersedes Phase 3's `SapWriteClient`, which becomes a thin facade or is merged into this class)
- [ ] `app/Services/Sap/SapReadRepository.php`
- [ ] `app/Services/Sap/SapCircuitBreaker.php`
- [ ] `app/Jobs/{CreateSapPurchaseRequest,PollSapPoStatus,ReconcileGrpoToLedger,NightlyReconciliation}.php`
- [ ] Migration: `create_sap_sync_logs_table` (shared audit/idempotency/failure table for all SAP write jobs — referenced by Phase 3's job too, retrofit it)
- [ ] `app/Models/SapSyncLog.php`
- [ ] `app/Console/Commands/{TestSapConnection,SyncSapPricing,SyncSapVendors}.php`
- [ ] `routes/console.php` schedule entries (PO/GRPO poll every 15 min, pricing/vendor daily, nightly reconciliation)
- [ ] `resources/js/Pages/Sap/SyncDashboard.tsx`
- [ ] Horizon dashboard configured with a dedicated `sap-writes` queue + higher-priority worker allocation

#### B. Migration Definitions

```php
// create_sap_sync_logs_table — shared idempotency + audit trail for ALL SAP write operations
Schema::create('sap_sync_logs', function (Blueprint $table) {
    $table->id();
    $table->string('operation');                 // 'create_pr'|'create_po'|'sync_interchange'|'reconcile_grpo'
    $table->string('correlation_key')->unique();  // e.g. 'create_po:tabulation_bid:42'
    $table->string('ref_type');
    $table->unsignedBigInteger('ref_id');
    $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
    $table->unsignedTinyInteger('attempts')->default(0);
    $table->json('request_payload')->nullable();
    $table->json('response_payload')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['ref_type', 'ref_id']);
    $table->index('status');
});
```

> Retrofit `CreateSapPurchaseOrder` (Phase 3) to write/update a `sap_sync_logs` row instead of (or in addition to) the cache-lock approach — the `correlation_key` unique constraint becomes the durable idempotency guard; the cache lock remains as a fast in-memory guard against concurrent workers, while `sap_sync_logs` is the audit-durable source of truth surfaced on the Sync Dashboard.

#### C. Model Definitions

```php
// app/Models/Sap/PurchaseRequest.php
class PurchaseRequest extends Model
{
    protected $connection = 'sap_sql';
    protected $table = 'OPRQ';
    protected $primaryKey = 'DocEntry';
    public $timestamps = false;
    protected $guarded = ['*'];

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class, 'DocEntry', 'DocEntry');
    }
}

// app/Models/Sap/PurchaseOrder.php
class PurchaseOrder extends Model
{
    protected $connection = 'sap_sql';
    protected $table = 'OPOR';
    protected $primaryKey = 'DocEntry';
    public $timestamps = false;
    protected $guarded = ['*'];
}

// app/Models/Sap/Grpo.php  (Goods Receipt PO — SAP table OPDN)
class Grpo extends Model
{
    protected $connection = 'sap_sql';
    protected $table = 'OPDN';
    protected $primaryKey = 'DocEntry';
    public $timestamps = false;
    protected $guarded = ['*'];
}

// app/Models/Sap/VendorMaster.php  (Business Partners — OCRD)
class VendorMaster extends Model
{
    protected $connection = 'sap_sql';
    protected $table = 'OCRD';
    protected $primaryKey = 'CardCode';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}

// app/Models/Sap/PriceList.php  (Item price list — ITM1)
class PriceList extends Model
{
    protected $connection = 'sap_sql';
    protected $table = 'ITM1';
    public $timestamps = false;
    protected $guarded = ['*'];
}

// app/Models/SapSyncLog.php  (PMB's OWN mysql connection — audit trail, not SAP data)
class SapSyncLog extends Model
{
    protected $fillable = ['operation', 'correlation_key', 'ref_type', 'ref_id', 'status', 'attempts', 'request_payload', 'response_payload', 'error_message', 'completed_at'];
    protected $casts = ['request_payload' => 'array', 'response_payload' => 'array', 'completed_at' => 'datetime'];
}
```

> **Every model in `App\Models\Sap\`** declares `protected $guarded = ['*'];` as a hard guard-rail — even if a developer accidentally calls `->save()` on one, Eloquent mass-assignment protection combined with code review / the repository-layer convention prevents accidental writes. All actual SAP writes go through `SapService`'s Service Layer HTTP methods, never through these Eloquent models.

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `Sap\SyncDashboardController::index` | `GET /sap/sync-dashboard` | `auth`, `can:viewSapDashboard` (IT Manager, Procurement Manager, Finance Director) | Renders `Sap/SyncDashboard` — recent `sap_sync_logs`, failure counts, Horizon queue depth widget, "retry" action per failed row |
| `Sap\SyncDashboardController::retry` | `POST /sap/sync-dashboard/{sapSyncLog}/retry` | `auth`, `can:viewSapDashboard` | Re-dispatches the underlying job for a `failed` log row |
| `Sap\TestConnectionController::index` | `GET /sap/test-connection` | `auth`, `can:viewSapDashboard` | On-demand health check hitting both Service Layer `/Login` and `sap_sql` `SELECT 1`; used for ops troubleshooting |

#### E. Service Classes

**`app/Services/Sap/SapService.php`** — the singleton, registered in `AppServiceProvider::register()`:

```php
$this->app->singleton(SapService::class);
```

- Purpose: the **only** class that owns a Guzzle `CookieJar` + `Client` for the Service Layer. All Phase 2/3 code that previously used `SapWriteClient` is refactored to call `app(SapService::class)` instead.
- Constructor sets up the Guzzle client exactly per `docs/concept.md` §7.2 / `docs/reference/sap-b1-session-management.md`:

```php
public function __construct()
{
    $this->cookieJar = new CookieJar();
    $this->client = new Client([
        'base_uri' => rtrim(config('services.sap.server_url'), '/') . '/b1s/v1/',
        'cookies'  => $this->cookieJar,
        'headers'  => ['Content-Type' => 'application/json'],
        'verify'   => config('services.sap.verify_ssl'),
        'timeout'  => 15,
    ]);
}
```

- Public methods:
  - `login(): bool` — `POST /Login` with `{CompanyDB, UserName, Password}`; cookies (`B1SESSION`, `ROUTEID`) stored automatically by Guzzle.
  - `ensureSession(): void` — `if (!$this->cookieJar->count()) { $this->login(); }`.
  - `request(string $method, string $endpoint, array $options = []): array` — generic wrapper: `ensureSession()` → send → on `401` catch, `login()`, retry once → decode JSON. All specific methods below delegate to this.
  - `getEntity(string $entity, array $query = []): array` — generic OData GET (`$filter`, `$select`, `$expand` passed via `$query`).
  - `createPurchaseRequest(array $payload): array` — `POST /PurchaseRequests`.
  - `createPurchaseOrder(array $payload): array` — `POST /Orders` (moves here from Phase 3's `SapWriteClient`).
  - `getVendorMaster(string $cardCode): array`, `getPriceList(string $itemCode): array`, `getPurchaseOrderStatus(string $docEntry): array`, `getGrpoByPoRef(string $poDocEntry): array`.
- **Session management strategy (verbatim from concept §7.2 and the reference doc):** one `SapService` instance per application lifecycle (singleton) → one B1SESSION cookie, reused across all HTTP requests in that PHP process/worker, preventing session-limit exhaustion under load. Horizon workers each get their own singleton instance per worker process (that's expected and fine — SAP supports multiple concurrent sessions per the reference doc §"Multiple Applications").

**`app/Services/Sap/SapReadRepository.php`**
- Purpose: implements the **priority pattern** from `docs/concept.md` §7.4 — Direct SQL Server first, Service Layer OData fallback.
- Public methods: `getMaterialRequestLines(int $docEntry): Collection`, `getPurchaseRequest(int $docEntry): array`, `getPurchaseOrder(int $docEntry): array`, `getInventoryTransferOut(Carbon $from, Carbon $to): Collection` (the `list_ITO.sql` case — direct SQL only, per `docs/reference/sap-sql-direct-access.md`), `getVendorMaster(string $cardCode): array`, `getPriceList(array $itemCodes): Collection`.
- Pattern per method:

```php
public function getInventoryTransferOut(Carbon $from, Carbon $to): Collection
{
    try {
        return $this->queryDirectSql($from, $to); // DB::connection('sap_sql')->select(...) — exact list_ITO.sql
    } catch (\Throwable $e) {
        Log::warning('SAP direct SQL failed, falling back to OData', ['error' => $e->getMessage()]);
        return $this->queryViaODataFallback($from, $to);
    }
}
```

- The exact parameterized query for ITO (`OWTR`/`WTR1`/`OITW` joined with the `U_MIS_TransferType` UDF) lives in `database/sql/list_ITO.sql` and is loaded via `DB::connection('sap_sql')->select(File::get(database_path('sql/list_ITO.sql')), [$from, $to])` — never string-concatenated, always parameterized to prevent SQL injection even though this is a read-only connection.

**`app/Services/Sap/SapCircuitBreaker.php`**
- Purpose: implements the resilience pattern from `docs/concept.md` §7.8 — if the Service Layer has failed N times in the last M minutes, short-circuit further attempts and serve cache/fail fast instead of piling up slow timeouts.
- Public methods: `isOpen(string $channel): bool` (channel = `'service_layer'|'sql_server'`), `recordFailure(string $channel): void`, `recordSuccess(string $channel): void`, `reset(string $channel): void`. Backed by Redis counters with a sliding TTL window (e.g., 5 failures in 2 minutes trips the breaker for 60 seconds).

#### F. Jobs

**`app/Jobs/CreateSapPurchaseRequest.php`**
- Trigger: Logistic Foreman confirms stock unavailable on a `PlantRequest` (Phase 2/5 UI action `POST /plant-requests/{id}/stock-check` with `result = unavailable`).
- Idempotency key: `create_pr:plant_request:{plant_request_id}` in `sap_sync_logs.correlation_key`.
- `handle()`: resolves interchange P/N substitutions (`InterchangeMap`, Phase 6) for each line before building the payload; calls `SapService::createPurchaseRequest()`; on success sets `plant_requests.sap_pr_no`; on failure retries 3x with `[10, 30, 90]` backoff then flags `sap_sync_logs.status = failed` and notifies Procurement.
- `$this->onQueue('sap-writes')`.

**`app/Jobs/PollSapPoStatus.php`**
- Trigger: scheduled every 15 minutes (`Schedule::job(new PollSapPoStatus())->everyFifteenMinutes()`), iterates all `tabulation_bids` with `sap_po_id IS NOT NULL AND status != 'closed'`.
- Idempotency: naturally idempotent (pure read + upsert of cached status fields), no correlation key needed, but still logs to `sap_sync_logs` with `operation = 'poll_po_status'` for observability.
- `handle()`: for each bid, `SapService::getPurchaseOrderStatus()`, update local cached stage (Created/Approved/Sent), and when stage transitions to a state with a matching GRPO, dispatch `ReconcileGrpoToLedger`.

**`app/Jobs/ReconcileGrpoToLedger.php`**
- Trigger: dispatched by `PollSapPoStatus` when a new GRPO is detected for a tracked PO, or manually via console command.
- Idempotency key: `reconcile_grpo:{sap_grpo_doc_entry}` — a GRPO is reconciled to the ledger exactly once.
- `handle()`: resolves the `PlantRequest`/`budget_allocation` chain from the PO's originating PR/MR, calls `BudgetEngine::postActual()` (Phase 1) with the GRPO line total, converting `commitment` → `actual`.
- `$this->onQueue('sap-writes')`.

**`app/Jobs/NightlyReconciliation.php`**
- Trigger: scheduled daily at 02:00 WITA.
- `handle()`: compares PMB's cached lifecycle state (`plant_requests.status`, `tabulation_bids.status`, `sap_po_id`/`sap_pr_no`) against live SAP reads via `SapReadRepository`; flags divergences into a `reconciliation_discrepancies` log (simple table or structured log entry) and notifies IT Manager + Procurement Manager of any mismatch found.

#### G. Policies

- `viewSapDashboard` — a `Gate::define` in `AppServiceProvider::boot()` (not a full Policy class, since it's not tied to a single Eloquent model): `Gate::define('viewSapDashboard', fn (User $u) => $u->hasRole('it_manager') || $u->hasRole('procurement_manager') || $u->hasRole('finance_director'));`

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/Sap/SyncDashboard.tsx` | `sap_sync_logs` table with status filter, retry button per failed row, Horizon queue depth widget (via a small JSON endpoint or embedded iframe to `/horizon`), circuit breaker state indicator | `ProTable`, `Badge`, `Statistic`, `Alert` (breaker-open warning) |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.5.

#### J. Tests

- `tests/Feature/Sap/SapServiceSessionTest.php` — mocked Guzzle handler: login stores cookies; a `401` response on a subsequent call triggers exactly one re-login + retry (assert call count); singleton behavior verified via `app(SapService::class) === app(SapService::class)`.
- `tests/Feature/Sap/SapReadRepositoryPriorityTest.php` — direct SQL success path never calls OData fallback; direct SQL exception triggers OData fallback (mock both channels).
- `tests/Feature/Sap/CreateSapPurchaseRequestJobTest.php` — idempotency: dispatching twice for the same `plant_request_id` results in exactly one `POST /PurchaseRequests` call; interchange substitution applied before payload build (assert payload P/N matches the mapped Genuine P/N, not the original OEM P/N).
- `tests/Feature/Sap/ReconcileGrpoToLedgerJobTest.php` — GRPO reconciliation posts exactly one `actual` + one offsetting `commitment` reversal per allocation; re-running for the same GRPO is a no-op (idempotency key already `success`).
- `tests/Feature/Sap/SapCircuitBreakerTest.php` — breaker opens after the configured failure threshold within the window and blocks further attempts until the cooldown expires.
- `tests/Feature/Sap/NightlyReconciliationTest.php` — seeded divergence (PMB says `po_created`, mocked SAP says PO doesn't exist) is detected and logged/notified.

---

### Phase 5 — DMBD Integration

**Goal:** Digitize the Daily Monitoring Breakdown: a daily equipment-status grid sourced from ARKFLEET, upserted per equipment per day, with a best-effort status sync back to ARKFLEET and breakdown→request pre-fill.

**Dependencies:** Phase 0 (ARKFLEET equipment client/cache), Phase 2 (Plant Request pre-fill target).

#### A. Deliverables Checklist

- [ ] Migration: `create_dmbd_entries_table`
- [ ] Model: `app/Models/DmbdEntry.php`
- [ ] `app/Jobs/SyncDmbdStatusToArkfleet.php`
- [ ] `app/Http/Controllers/DmbdController.php`
- [ ] `app/Http/Requests/StoreDmbdEntryRequest.php`
- [ ] `app/Policies/DmbdEntryPolicy.php`
- [ ] `resources/js/Pages/Dmbd/Index.tsx`
- [ ] `database/factories/DmbdEntryFactory.php`
- [ ] `routes/console.php` schedule entry: retry failed ARKFLEET status syncs every 30 minutes

#### B. Migration Definitions

```php
// create_dmbd_entries_table
Schema::create('dmbd_entries', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('equipment_id');    // FK ARKFLEET equipment
    $table->string('unit_code_cache');
    $table->date('report_date');
    $table->enum('operational_status', ['rfu', 'standby', 'breakdown']);
    $table->text('breakdown_note')->nullable();
    $table->foreignId('reported_by')->constrained('users');   // Planner
    $table->boolean('synced_to_arkfleet')->default(false);
    $table->timestamps();

    $table->unique(['equipment_id', 'report_date']);  // one entry per equipment per day — enforced upsert
    $table->index(['report_date', 'operational_status']);
});
```

#### C. Model Definitions

```php
// app/Models/DmbdEntry.php
class DmbdEntry extends Model
{
    protected $fillable = ['equipment_id', 'unit_code_cache', 'report_date', 'operational_status', 'breakdown_note', 'reported_by', 'synced_to_arkfleet'];
    protected $casts = ['report_date' => 'date:Y-m-d', 'synced_to_arkfleet' => 'boolean'];

    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function plantRequests(): HasMany { return $this->hasMany(PlantRequest::class, 'dmbd_entry_id'); }

    public static function upsertForToday(int $equipmentId, string $unitCode, string $status, ?string $note, int $userId): self
    {
        return static::updateOrCreate(
            ['equipment_id' => $equipmentId, 'report_date' => now()->toDateString()],
            ['unit_code_cache' => $unitCode, 'operational_status' => $status, 'breakdown_note' => $note, 'reported_by' => $userId, 'synced_to_arkfleet' => false]
        );
    }
}
```

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `DmbdController::index` | `GET /dmbd` | `auth`, `EnsureProjectScope` | Daily grid: equipment rows (from ARKFLEET `EquipmentCache::list()`) × today's status, color-coded chips |
| `DmbdController::store` | `POST /dmbd` | `auth`, `can:create,DmbdEntry` | Upserts today's entry via `DmbdEntry::upsertForToday()`; dispatches `SyncDmbdStatusToArkfleet`; busts `EquipmentCache` for this equipment |
| `DmbdController::history` | `GET /dmbd/{equipment}/history` | `auth` | Historical status timeline for one equipment (breakdown frequency analysis, feeds Phase 7 reporting) |
| `DmbdController::prefillRequest` | `GET /dmbd/{dmbdEntry}/prefill-request` | `auth`, `can:create,PlantRequest` | Redirects to `PlantRequest/Create` with `dmbd_entry_id` + `equipment_id` query params pre-filled (Beta: breakdown → WO → MR pre-fill per `docs/concept.md` §4.4) |

#### E. Service Classes

No new dedicated service class — `DmbdController` uses `EquipmentCache` (Phase 0) directly for the equipment roster and delegates status-sync to the job below. If breakdown→WO→MR logic grows complex in a later iteration, extract `app/Services/Dmbd/BreakdownRequestPrefiller.php` then.

#### F. Jobs

**`app/Jobs/SyncDmbdStatusToArkfleet.php`**
- Trigger: dispatched immediately after `DmbdController::store` upserts an entry.
- Idempotency: naturally idempotent (a status PATCH is safe to repeat); tracked via `dmbd_entries.synced_to_arkfleet` boolean rather than a `sap_sync_logs`-style table (ARKFLEET sync volume/criticality is lower than SAP writes).
- `handle()` logic — **per `docs/concept.md` §8.5, the ARKFLEET endpoint `PATCH /equipment/{id}/status` does NOT exist yet.** The job is written defensively:

```php
public function handle(ArkfleetClient $client): void
{
    try {
        $client->patchEquipmentStatus($this->dmbdEntry->equipment_id, [
            'unitstatus_id' => $this->mapStatusToArkfleetId($this->dmbdEntry->operational_status),
            'is_rfu' => $this->dmbdEntry->operational_status === 'rfu',
        ]);
        $this->dmbdEntry->update(['synced_to_arkfleet' => true]);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        if ($e->getResponse()?->getStatusCode() === 404) {
            // Endpoint not yet implemented by ARKFLEET team — leave synced_to_arkfleet=false,
            // scheduled retry job will keep trying until the endpoint ships (see §8.5).
            Log::info('ARKFLEET status PATCH endpoint not available yet — will retry on schedule.');
            return;
        }
        throw $e;
    }
}
```

- A scheduled command (`Schedule::command('dmbd:retry-sync')->everyThirtyMinutes()`) re-dispatches this job for all `synced_to_arkfleet = false` entries, so that the moment ARKFLEET ships the proposed `PATCH /equipment/{id}/status` endpoint, PMB starts syncing without any PMB-side deployment.
- `ArkfleetClient::patchEquipmentStatus()` is added to the Phase 0 client as a forward-looking method now, per the proposed contract in `docs/concept.md` §8.5.

#### G. Policies

**`app/Policies/DmbdEntryPolicy.php`**
- `create(User $user)` — `planner` role (✅ full per role matrix); `mechanic`/`project_manager`/`plant_manager`/directors get `view`-only (👁).
- `view(User $user)` — true for all authenticated, project-scoped users.

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/Dmbd/Index.tsx` | Mobile-first daily grid; large touch targets for status chips; quick-entry modal for breakdown notes; staleness banner if ARKFLEET cache is degraded | `List` (mobile) / `Table` (desktop, responsive breakpoint), `Tag` (RFU green / Standby amber / Breakdown red), `Modal`, `Input.TextArea` |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.6.

#### J. Tests

- `tests/Feature/Dmbd/UpsertEntryTest.php` — one entry per equipment per day enforced (`upsertForToday` called twice same day updates, doesn't duplicate); status change busts `EquipmentCache`.
- `tests/Feature/Dmbd/SyncToArkfleetJobTest.php` — mocked `ArkfleetClient::patchEquipmentStatus` — success sets `synced_to_arkfleet = true`; a `404` (endpoint not implemented) leaves it `false` without throwing/failing the job; scheduled retry command re-dispatches only unsynced entries.
- `tests/Feature/Dmbd/PrefillRequestTest.php` — breakdown entry correctly pre-fills `PlantRequest/Create` with `dmbd_entry_id`/`equipment_id`.

---

### Phase 6 — Overbudget + Cancellation + Interchange

**Goal:** Complete the governance loop: a controlled path for spend beyond 110% (Finance Director → Operation Director), stage-gated cancellation with automatic ledger reversal, and Procurement-owned Genuine↔OEM interchange mapping with SAP part-master sync and mandatory Plant technical sign-off.

**Dependencies:** Phases 1 (ledger engine), 2 (Plant Request blocking on >110%), 3/4 (PO stage for cancellation gating, SAP part master for interchange sync).

#### A. Deliverables Checklist

- [ ] Migrations: `create_overbudget_requests_table`, `create_cancellation_requests_table`, `create_interchange_maps_table`
- [ ] Models: `app/Models/{OverbudgetRequest,CancellationRequest,InterchangeMap}.php`
- [ ] `app/Jobs/SyncInterchangeToSap.php`
- [ ] `app/Http/Controllers/{OverbudgetController,CancellationController,InterchangeController}.php`
- [ ] `app/Http/Requests/{StoreOverbudgetRequestRequest,StoreCancellationRequestRequest,StoreInterchangeMapRequest}.php`
- [ ] `app/Policies/{OverbudgetRequestPolicy,CancellationRequestPolicy,InterchangeMapPolicy}.php`
- [ ] `resources/js/Pages/{Overbudget,Cancellation,Interchange}/Index.tsx`
- [ ] `database/factories/{OverbudgetRequestFactory,CancellationRequestFactory,InterchangeMapFactory}.php`
- [ ] `database/seeders/RequestApprovalChainSeeder.php` updated with `OverbudgetRequest` → `[finance_director, operation_director]` and `CancellationRequest` → `[procurement_manager]` chain templates (retrofit from Phase 2 seeder)

#### B. Migration Definitions

```php
// create_overbudget_requests_table
Schema::create('overbudget_requests', function (Blueprint $table) {
    $table->id();
    $table->string('request_no', 30)->unique();     // PMB-OB-YYYYMM-####
    $table->foreignId('budget_allocation_id')->constrained();
    $table->foreignId('plant_request_id')->nullable()->constrained();
    $table->decimal('requested_amount', 18, 2);
    $table->decimal('over_pct', 5, 2);               // % beyond 110%
    $table->enum('status', ['pending_fin_dir', 'pending_ops_dir', 'approved', 'rejected'])->default('pending_fin_dir');
    $table->text('justification');
    $table->foreignId('requested_by')->constrained('users');
    $table->timestamps();

    $table->index(['status', 'budget_allocation_id']);
});

// create_cancellation_requests_table
Schema::create('cancellation_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('plant_request_id')->constrained();
    $table->string('sap_po_id')->nullable();
    $table->enum('po_stage', ['created', 'approved', 'sent'])->nullable();  // snapshot at time of cancellation request
    $table->enum('initiated_by', ['plant', 'procurement']);
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->decimal('budget_reversal_amount', 18, 2)->default(0);
    $table->text('reason');
    $table->timestamps();

    $table->index(['plant_request_id', 'status']);
});

// create_interchange_maps_table
Schema::create('interchange_maps', function (Blueprint $table) {
    $table->id();
    $table->string('genuine_part_number');
    $table->string('oem_part_number');
    $table->string('material_name');
    $table->boolean('sap_synced')->default(false);
    $table->string('sap_sync_ref')->nullable();
    $table->foreignId('created_by')->constrained('users');   // Procurement only, enforced at policy layer
    $table->foreignId('technical_signoff_by')->nullable()->constrained('users');  // Plant/AML secondary sign-off — see docs/concept.md §12.2 recommendation
    $table->timestamp('technical_signoff_at')->nullable();
    $table->timestamps();

    $table->unique(['genuine_part_number', 'oem_part_number']);
    $table->index('sap_synced');
});
```

> **Note on `technical_signoff_by`/`technical_signoff_at`:** these two columns are an addition beyond the base ERD in `docs/concept.md` §3, implementing the Open Question #2 recommendation ("Procurement creates, but require a secondary technical sign-off from Plant/AML before SAP sync"). This is called out explicitly here because it is a plan-level enhancement, not a concept.md contradiction — `sap_synced` must only ever transition to `true` after `technical_signoff_at` is set (enforced in `InterchangeController::sync`, not just in the job).

#### C. Model Definitions

```php
// app/Models/OverbudgetRequest.php
class OverbudgetRequest extends Model
{
    protected $fillable = ['request_no', 'budget_allocation_id', 'plant_request_id', 'requested_amount', 'over_pct', 'status', 'justification', 'requested_by'];
    protected $casts = ['requested_amount' => 'decimal:2', 'over_pct' => 'decimal:2'];

    public function allocation(): BelongsTo { return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id'); }
    public function plantRequest(): BelongsTo { return $this->belongsTo(PlantRequest::class); }
    public function approvals(): MorphMany { return $this->morphMany(RequestApproval::class, 'approvable'); }

    protected static function booted(): void
    {
        static::creating(fn (OverbudgetRequest $r) => $r->request_no ??= sprintf('PMB-OB-%s-%04d', now()->format('Ym'), static::whereYear('created_at', now()->year)->count() + 1));
    }
}

// app/Models/CancellationRequest.php
class CancellationRequest extends Model
{
    protected $fillable = ['plant_request_id', 'sap_po_id', 'po_stage', 'initiated_by', 'status', 'budget_reversal_amount', 'reason'];
    protected $casts = ['budget_reversal_amount' => 'decimal:2'];

    public function plantRequest(): BelongsTo { return $this->belongsTo(PlantRequest::class); }
    public function approvals(): MorphMany { return $this->morphMany(RequestApproval::class, 'approvable'); }

    public function canBeCancelledByPlant(): bool
    {
        return $this->po_stage !== 'sent';   // hard stage gate per docs/concept.md §4.7/§5.4
    }
}

// app/Models/InterchangeMap.php
class InterchangeMap extends Model
{
    protected $fillable = ['genuine_part_number', 'oem_part_number', 'material_name', 'sap_synced', 'sap_sync_ref', 'created_by', 'technical_signoff_by', 'technical_signoff_at'];
    protected $casts = ['sap_synced' => 'boolean', 'technical_signoff_at' => 'datetime'];

    public function lines(): HasMany { return $this->hasMany(PlantRequestLine::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function signoffBy(): BelongsTo { return $this->belongsTo(User::class, 'technical_signoff_by'); }

    public function isReadyForSapSync(): bool
    {
        return $this->technical_signoff_at !== null && ! $this->sap_synced;
    }
}
```

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `OverbudgetController::create` | `GET /overbudget/create` | `auth` | Guided escalation modal target, pre-filled from the blocking `PlantRequest`/`budget_allocation` |
| `OverbudgetController::store` | `POST /overbudget` | `auth`, `can:create,OverbudgetRequest` | Computes `over_pct`, initiates `ApprovalEngine` with `[finance_director, operation_director]` |
| `OverbudgetController::index` | `GET /overbudget` | `auth` | Director approval queue with budget context (`BudgetProgressBar` inline) |
| `ApprovalController::decide` (reused) | `POST /approvals/{requestApproval}/decide` | `auth`, `can:decide` | On final approval, calls `BudgetEngine::postOverbudget()` and unblocks the underlying `PlantRequest` (`status` reverts from blocked to `pending_pm` to resume its own chain, or directly to `approved` if the Overbudget was raised post-approval — implementation decision made against the actual blocking point in Phase 2) |
| `CancellationController::create` | `GET /plant-requests/{plantRequest}/cancel` | `auth`, `can:cancel,plantRequest` | Cancellation modal showing PO stage, who can act, exact reversal amount |
| `CancellationController::store` | `POST /plant-requests/{plantRequest}/cancel` | `auth`, `can:cancel,plantRequest` | Creates `CancellationRequest`; if `initiated_by = plant` and stage is `sent`, **rejected server-side even before creating the row** (stage gate enforced at the controller, not just the policy, per `docs/concept.md` §4.7) |
| `CancellationController::agree` | `POST /cancellation-requests/{cancellationRequest}/agree` | `auth`, `can:agree,cancellationRequest` (the counterparty — Procurement if Plant initiated, or Plant/Plant Manager if Procurement initiated) | Records agreement; on both-sides agreement, calls `BudgetEngine::reverseCommitment()` and sets `status = approved` |
| `InterchangeController::index` | `GET /interchange` | `auth` | Searchable mapping table with sync status indicator |
| `InterchangeController::store` | `POST /interchange` | `auth`, `can:create,InterchangeMap` (Procurement only) | Validates both P/Ns resolve in SAP part master (`SapReadRepository::getPriceList()` lookup) before saving |
| `InterchangeController::signoff` | `POST /interchange/{interchangeMap}/signoff` | `auth`, `can:signoff,interchangeMap` (Plant/AML technical role) | Sets `technical_signoff_by`/`technical_signoff_at`; dispatches `SyncInterchangeToSap` |

#### E. Service Classes

No new dedicated service beyond reuse of `BudgetEngine` (Phase 1), `ApprovalEngine` (Phase 2), and `SapReadRepository`/`SapService` (Phase 4). This phase is primarily controller + job orchestration on top of existing engines — a deliberate design choice to avoid service-class proliferation for what are essentially two more approval-chain consumers and one more SAP-sync job.

#### F. Jobs

**`app/Jobs/SyncInterchangeToSap.php`**
- Trigger: dispatched after `InterchangeController::signoff` sets the technical sign-off (never before — enforced by `InterchangeMap::isReadyForSapSync()` guard inside the job's `handle()` as a defense-in-depth check, not just at the controller).
- Idempotency key: `sync_interchange:{interchange_map_id}` in `sap_sync_logs`.
- `handle()`: calls SAP part-master update via `SapService` (exact endpoint depends on whether SAP models Genuine/OEM interchange via `ItemGroups`/`OITM` remarks or a UDF — flagged as an **open implementation detail to confirm with the SAP functional consultant during Phase 4/6 build**; the job is written against an injectable `SapService::syncInterchangeMapping(array $payload): array` method so the actual SAP call can be finalized without changing the job's retry/idempotency scaffolding); on success sets `sap_synced = true`, `sap_sync_ref`; on failure retries 3x then flags `sap_sync_logs.status = failed` and notifies Procurement.

#### G. Policies

- `OverbudgetRequestPolicy::create` — any role that can create a `PlantRequest` (the escalation is triggered from the blocked request, not created cold by a director).
- `CancellationRequestPolicy::create` (`cancel` ability on `PlantRequest`) — `planner`/`project_manager`/`plant_manager` (Plant side) or `buyer`/`procurement_manager`/`procurement_admin` (Procurement side); server-side stage gate always re-checked regardless of who initiates.
- `CancellationRequestPolicy::agree` — the *other* party from `initiated_by` (Plant-initiated → Procurement role must agree; Procurement-initiated → Plant Manager must agree).
- `InterchangeMapPolicy::create` — `buyer`/`procurement_manager`/`procurement_admin` only (matches the ✅/✅/✅ row in the role matrix; **no other role can create**).
- `InterchangeMapPolicy::signoff` — `plant_manager` or an AML technical role (per Open Question #2 recommendation); cannot be the same user who created the mapping (SoD).

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/Overbudget/Index.tsx` | Escalation modal (launched from blocked `PlantRequest/Show`) + director approval queue with budget context | `Modal`, `ProForm`, `ApprovalQueue` (reused), `BudgetProgressBar` (reused) |
| `resources/js/Pages/Cancellation/Index.tsx` | Cancellation modal (PO stage badge, reversal amount preview) + agreement queue | `Modal`, `Descriptions`, `Tag` (stage badge), `Button` (agree/reject) |
| `resources/js/Pages/Interchange/Index.tsx` | Searchable P/N mapping table, sync status indicator, sign-off action button | `ProTable`, `Badge` (sync status), `Button` |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.7.

#### J. Tests

- `tests/Feature/Overbudget/EscalationTest.php` — `over_pct` computed correctly; two-step Fin Dir → Ops Dir chain; final approval posts `overbudget` ledger entry and unblocks the underlying `PlantRequest`.
- `tests/Feature/Cancellation/StageGateTest.php` — Plant-initiated cancellation on a `sent` PO is rejected server-side (403/422) even if attempted directly via API bypassing the UI button-disable; `created`/`approved` stage cancellation allowed.
- `tests/Feature/Cancellation/ReversalTest.php` — mutual agreement triggers exactly one `reversal` ledger entry equal to `budget_reversal_amount`; one-sided agreement leaves status `pending`.
- `tests/Feature/Interchange/CreateAndSignoffTest.php` — only Procurement roles can create; SAP part-master validation blocks save if either P/N doesn't resolve; sign-off cannot be performed by the mapping's own creator (SoD); `SyncInterchangeToSap` only dispatches after sign-off (assert job not dispatched on create, is dispatched on signoff).
- `tests/Feature/Interchange/SapSyncGuardTest.php` — job's defense-in-depth check refuses to sync a mapping with `technical_signoff_at = null` even if dispatched manually/out-of-band.

---

### Phase 7 — Reporting & Analytics

**Goal:** Board-ready budget consumption, vendor performance, and equipment cost-per-hour/km analytics, all reconciling to `budget_ledgers` as the single source of financial truth, with PDF export via dompdf.

**Dependencies:** Phases 1 (ledger), 3 (vendor/Tabulation Bid history), 0 (ARKFLEET HM/KM readings).

#### A. Deliverables Checklist

- [ ] `app/Services/Reporting/{BudgetConsumptionReport,VendorPerformanceReport,EquipmentCostReport}.php`
- [ ] `app/Http/Controllers/ReportController.php`
- [ ] `resources/views/pdf/{budget-consumption,vendor-performance,equipment-cost,po-document}.blade.php` (dompdf templates)
- [ ] `resources/js/Pages/Reports/{BudgetConsumption,VendorPerformance,EquipmentCost}.tsx`
- [ ] `app/Exports/` — **none using `maatwebsite/excel`**; CSV exports implemented as plain controller responses using `League\Csv` or native `fputcsv` streaming (see §D)
- [ ] `app/Policies/ReportPolicy.php` (gate per report type per role matrix §6 Reports row)

#### B. Migration Definitions

No new tables — Phase 7 is entirely read-side, querying `budget_ledgers`, `tabulation_bid_vendors`/`tabulation_bid_awards`, and ARKFLEET HM/KM via the existing `ArkfleetClient`.

#### C. Model Definitions

No new models. Reports query existing Eloquent models directly (`BudgetLedger::query()`, `TabulationBidVendor::query()`, etc.) via the service classes below — no new Eloquent classes needed, though each report service may define small read-only DTFO (data transfer) value objects/arrays for clarity, not Eloquent models.

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `ReportController::budgetConsumption` | `GET /reports/budget-consumption` | `auth`, `can:view,budget-consumption-report` | By project/month/equipment/plant_type, with variance + carry-forward columns |
| `ReportController::vendorPerformance` | `GET /reports/vendor-performance` | `auth`, `can:view,vendor-performance-report` | Price competitiveness, stock reliability %, indent frequency, from `tabulation_bid_vendors`/`awards` history |
| `ReportController::equipmentCost` | `GET /reports/equipment-cost` | `auth`, `can:view,equipment-cost-report` | Per-unit / per-plant_type cost breakdown, joined with ARKFLEET HM/KM for IDR/hour and IDR/km |
| `ReportController::exportPdf` | `GET /reports/{reportType}/export/pdf` | `auth`, same gate as the source report | Streams a dompdf-rendered PDF using the same query as the on-screen report |
| `ReportController::exportCsv` | `GET /reports/{reportType}/export/csv` | `auth`, same gate | Streams CSV via `StreamedResponse` + `fputcsv` — **no Excel library** |

#### E. Service Classes

**`app/Services/Reporting/BudgetConsumptionReport.php`**
- Public methods: `byProject(string $projectCode, Carbon $month): array`, `byEquipment(int $equipmentId, Carbon $month): array`, `byPlantType(string $projectCode, string $plantType, Carbon $month): array`, `rollingSixMonth(string $projectCode): Collection`.
- Every figure is derived by summing `budget_ledgers.amount` grouped by `entry_type` — **never** reads `budget_allocations.committed_amount`/`actual_amount` directly for a report (those are caches; reports recompute from the ledger to double-guarantee reconciliation, catching any cache-drift bug immediately in the reports themselves).

**`app/Services/Reporting/VendorPerformanceReport.php`**
- Public methods: `priceCompetitiveness(string $vendorCode, ?Carbon $from = null, ?Carbon $to = null): array` (avg rank across bids, win rate), `stockReliability(string $vendorCode): array` (`ready` vs `indent`/`partial` % from `tabulation_bid_vendors.stock_availability`), `indentFrequency(): Collection` (all vendors ranked by indent %).

**`app/Services/Reporting/EquipmentCostReport.php`**
- Public methods: `costPerHour(int $equipmentId, Carbon $month): array` (joins `budget_ledgers` actual spend for the equipment's allocation to `ArkfleetClient::getHmKmReadings()` delta-HM for the period; formula `total_spend / delta_hm` per `docs/concept.md` §8.6), `costPerKm(int $equipmentId, Carbon $month): array`, `fleetSummary(string $projectCode, Carbon $month): Collection`.
- Uses `EquipmentCache` (Phase 0), not a fresh ARKFLEET call, respecting the caching architecture; degrades gracefully with a staleness note if ARKFLEET is unreachable.

#### F. Jobs

None — all reports compute on-demand at request time. If report queries prove slow at scale (large ledger tables), a future optimization can add a nightly `app/Jobs/PrecomputeMonthlyReportSnapshots.php`, but this is explicitly deferred (not built in Phase 7) since the concept doc prioritizes ledger-as-source-of-truth over precomputed snapshots (§11 "Budget calculation" decision row: "performance via periodic snapshots if needed" — treated as a later optimization, not a Phase 7 deliverable).

#### G. Policies

**`app/Policies/ReportPolicy.php`** (or three `Gate::define` closures, given the small surface) — maps directly to the role matrix §6 "Reports & analytics" row: 👁 for Planner/Mechanic/Project Mgr/Buyer/Log Foreman/Log PIC/IT Mgr/AML Dept Head; ✅ for Plant Mgr/Proc Mgr/Finance Dir/Ops Dir/Pres Dir/AML Mgr. (👁 = can view on-screen but not export; ✅ = full, including PDF/CSV export — implemented as two abilities, `view` and `export`, per report type.)

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/Reports/BudgetConsumption.tsx` | Drill-down table (project → equipment), variance highlighting, export buttons | `ProTable`, `Statistic`, `Button` (export dropdown: PDF/CSV) |
| `resources/js/Pages/Reports/VendorPerformance.tsx` | Ranked vendor table, price competitiveness chart | `ProTable`, `Column`/`Line` chart (via `@ant-design/plots` if added, else simple `Progress`/`Statistic` grid to avoid another dependency) |
| `resources/js/Pages/Reports/EquipmentCost.tsx` | Fleet cost-per-hour/km table, drill into single equipment trend | `ProTable`, `Statistic` |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.8.

#### J. Tests

- `tests/Feature/Reports/BudgetConsumptionReportTest.php` — figures match a hand-computed sum over a seeded `budget_ledgers` set across all six `entry_type`s; rolling six-month view returns exactly 6 periods in the correct order.
- `tests/Feature/Reports/VendorPerformanceReportTest.php` — win rate / stock reliability computed correctly against seeded `TabulationBidVendor`/`TabulationBidAward` data.
- `tests/Feature/Reports/EquipmentCostReportTest.php` — cost-per-hour formula verified against a mocked `ArkfleetClient::getHmKmReadings()` delta; graceful degradation (stale flag surfaced) when ARKFLEET client throws.
- `tests/Feature/Reports/ExportTest.php` — PDF export returns `application/pdf` content-type with non-empty body; CSV export streams valid CSV (header row + N data rows matching the on-screen query); **no `maatwebsite/excel` classes referenced anywhere in the codebase** — enforced by a simple `composer show` assertion in CI or a grep-based static check in this test file's `beforeAll`.
- `tests/Feature/Reports/ReportGatePolicyTest.php` — 👁-only roles get 403 on export endpoints but 200 on the on-screen index.

---

### Phase 8 — Component Database + Cannibal (Beta)

**Goal:** A hierarchical component database (Housing → Inner Parts → Critical Parts) maintained by AML, and a 4-level Cannibal Request approval chain (Plant Manager → AML Manager → Operation Director → President Director) that must cite a DMBD breakdown status, with ARKFLEET component sync designed against the proposed (not-yet-existing) endpoints from `docs/concept.md` §8.7.

**Dependencies:** Phase 0 (ARKFLEET equipment FK, roles), Phase 5 (DMBD entry citation requirement), Phase 2's `ApprovalEngine` (reused for the 4-level chain).

> **Beta scope note:** per `docs/concept.md` §1.5/§10, this phase is explicitly Beta — build it behind a feature flag (`config('features.cannibal_beta')` / a simple `.env` `FEATURE_CANNIBAL_BETA=true`) so it can ship disabled in production until Directors sign off on the workflow, per Open Question #3's recommendation to consider an emergency fast-path (documented here but **not built** unless a Director explicitly requests it — flag as a backlog item in `docs/backlog.md`, not implemented speculatively).

#### A. Deliverables Checklist

- [ ] Migrations: `create_components_table`, `create_cannibal_requests_table`
- [ ] Models: `app/Models/{Component,CannibalRequest}.php`
- [ ] `app/Jobs/SyncComponentMovementToArkfleet.php`
- [ ] `app/Http/Controllers/{ComponentController,CannibalController}.php`
- [ ] `app/Http/Requests/{StoreComponentRequest,StoreCannibalRequestRequest}.php`
- [ ] `app/Policies/{ComponentPolicy,CannibalRequestPolicy}.php`
- [ ] `resources/js/Pages/{Component,Cannibal}/Index.tsx`
- [ ] `database/factories/{ComponentFactory,CannibalRequestFactory}.php`
- [ ] `config/features.php` (`cannibal_beta` flag) + middleware or route-group gate hiding Phase 8 routes/menu items when disabled
- [ ] `database/seeders/RequestApprovalChainSeeder.php` updated with `CannibalRequest` → `[plant_manager, aml_manager, operation_director, president_director]`

#### B. Migration Definitions

```php
// create_components_table
Schema::create('components', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('components')->nullOnDelete();  // self-referencing hierarchy
    $table->enum('level', ['housing', 'inner', 'critical']);
    $table->unsignedBigInteger('equipment_id');   // FK ARKFLEET equipment (installed_on)
    $table->string('component_code');
    $table->string('description');
    $table->enum('status', ['installed', 'removed', 'cannibalized', 'scrapped'])->default('installed');
    $table->foreignId('maintained_by')->nullable()->constrained('users');  // AML
    $table->timestamps();

    $table->index(['equipment_id', 'status']);
    $table->index('parent_id');
});

// create_cannibal_requests_table
Schema::create('cannibal_requests', function (Blueprint $table) {
    $table->id();
    $table->string('request_no', 30)->unique();     // PMB-CAN-YYYYMM-####
    $table->unsignedBigInteger('source_equipment_id');   // FK ARKFLEET
    $table->unsignedBigInteger('target_equipment_id');   // FK ARKFLEET
    $table->foreignId('dmbd_entry_id')->constrained('dmbd_entries');  // mandatory justification
    $table->enum('status', [
        'pending_plant_mgr', 'pending_aml_mgr', 'pending_ops_dir', 'pending_presdir', 'approved', 'rejected',
    ])->default('pending_plant_mgr');
    $table->text('reason');
    $table->foreignId('requested_by')->constrained('users');
    $table->timestamps();

    $table->index(['status', 'source_equipment_id']);
});

// pivot to track which components move in a cannibal request (per ERD: CANNIBAL_REQUEST ||--o{ COMPONENT : "moves")
Schema::create('cannibal_request_component', function (Blueprint $table) {
    $table->foreignId('cannibal_request_id')->constrained()->cascadeOnDelete();
    $table->foreignId('component_id')->constrained()->cascadeOnDelete();
    $table->primary(['cannibal_request_id', 'component_id']);
});
```

> The pivot `cannibal_request_component` is a plan-level addition to realize the ERD's `CANNIBAL_REQUEST ||--o{ COMPONENT : "moves"` relationship (`docs/concept.md` §3 models it as a one-to-many comment in the diagram; a many-to-many pivot is the correct concrete implementation since a single cannibal action typically moves more than one component and a component could theoretically be referenced by request history).

#### C. Model Definitions

```php
// app/Models/Component.php
class Component extends Model
{
    protected $fillable = ['parent_id', 'level', 'equipment_id', 'component_code', 'description', 'status', 'maintained_by'];

    public function parent(): BelongsTo { return $this->belongsTo(Component::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Component::class, 'parent_id'); }
    public function cannibalRequests(): BelongsToMany { return $this->belongsToMany(CannibalRequest::class, 'cannibal_request_component'); }
}

// app/Models/CannibalRequest.php
class CannibalRequest extends Model
{
    protected $fillable = ['request_no', 'source_equipment_id', 'target_equipment_id', 'dmbd_entry_id', 'status', 'reason', 'requested_by'];

    public function components(): BelongsToMany { return $this->belongsToMany(Component::class, 'cannibal_request_component'); }
    public function dmbdEntry(): BelongsTo { return $this->belongsTo(DmbdEntry::class); }
    public function approvals(): MorphMany { return $this->morphMany(RequestApproval::class, 'approvable'); }

    protected static function booted(): void
    {
        static::creating(fn (CannibalRequest $r) => $r->request_no ??= sprintf('PMB-CAN-%s-%04d', now()->format('Ym'), static::whereYear('created_at', now()->year)->count() + 1));
    }
}
```

#### D. Controller Methods

| Method | Route | Middleware | Purpose |
|---|---|---|---|
| `ComponentController::index` | `GET /components` | `auth`, `feature:cannibal_beta` | Hierarchical tree view (Housing → Inner → Critical) per equipment |
| `ComponentController::store` | `POST /components` | `auth`, `can:create,Component` (AML) | Register a component under a parent |
| `ComponentController::updateStatus` | `PATCH /components/{component}/status` | `auth`, `can:update,Component` (AML) | Manual status change (installed/removed/scrapped) outside a cannibal flow |
| `CannibalController::index` | `GET /cannibal-requests` | `auth`, `feature:cannibal_beta` | List + 4-level approval queue view |
| `CannibalController::create` | `GET /cannibal-requests/create` | `auth`, `can:create,CannibalRequest` (Planner/Mechanic) | Requires selecting a `DmbdEntry` with `operational_status = breakdown` for the source equipment (validated server-side, not just client-side) |
| `CannibalController::store` | `POST /cannibal-requests` | `auth`, `can:create,CannibalRequest` | Initiates the 4-level `ApprovalEngine` chain |

#### E. Service Classes

No new dedicated service class — reuses `ApprovalEngine` (Phase 2) for the 4-level chain. If the "Emergency Cannibal" fast-path from Open Question #3 is later approved by Directors, it would be implemented as `app/Services/Approval/EmergencyCannibalPath.php` at that time — explicitly **not built now**.

#### F. Jobs

**`app/Jobs/SyncComponentMovementToArkfleet.php`**
- Trigger: dispatched when a `CannibalRequest` reaches `status = approved`.
- `handle()`: calls proposed ARKFLEET endpoints `PATCH /equipment/{id}/components/{componentId}/status` (per `docs/concept.md` §8.7 — **not yet implemented by ARKFLEET**). Written defensively exactly like `SyncDmbdStatusToArkfleet` (Phase 5): catch `404`, log, leave a `synced_to_arkfleet`-style flag false (add this boolean to `components` via a small follow-up migration when this phase is actually built), and rely on a scheduled retry command.
- On success: updates local `components.status = cannibalized` for the moved components and `installed`/`removed` transitions on source/target as appropriate.

#### G. Policies

- `ComponentPolicy::create`/`update` — `aml_manager`/`aml_dept_head` (✅ per role matrix); `plant_manager`/`operation_director`/`president_director` get view-only (👁).
- `CannibalRequestPolicy::create` — `planner` (✅) or `mechanic` (✔).
- `CannibalRequestPolicy::decide` (via the shared `ApprovalController::decide`, reused) — step 1 `plant_manager`, step 2 `aml_manager`, step 3 `operation_director`, step 4 `president_director`, matching the role matrix's `✔(1)/✔(2)/✔(3)/✔(4)` annotations exactly.

#### H. Frontend Pages

| File | Purpose | AntD components |
|---|---|---|
| `resources/js/Pages/Component/Index.tsx` | Hierarchical tree (Housing → Inner → Critical) per equipment | `Tree`, `Tag` (status) |
| `resources/js/Pages/Cannibal/Index.tsx` | Request creation (source/target equipment + mandatory breakdown DMBD citation) + 4-level approval timeline | `Steps` (4-level), `ApprovalQueue` (reused), `Select` (equipment, DMBD entry) |

#### I. Routes

See [Full Route Table](#3-full-route-table) §3.9.

#### J. Tests

- `tests/Feature/Component/HierarchyTest.php` — parent/child nesting (Housing→Inner→Critical) enforced; a `critical`-level component cannot have children (business rule, validated in `StoreComponentRequest`).
- `tests/Feature/Cannibal/CreateRequiresBreakdownDmbdTest.php` — creating a `CannibalRequest` without a `breakdown`-status `DmbdEntry` for the source equipment is rejected (422), even if attempted directly via API.
- `tests/Feature/Cannibal/FourLevelChainTest.php` — full happy path through all 4 steps in order; rejection at any step halts the chain and sets `status = rejected` (no skipping steps, no out-of-order approval — attempting to approve step 3 before step 1/2 complete is 403).
- `tests/Feature/Cannibal/SyncComponentMovementJobTest.php` — mocked ARKFLEET `404` (endpoint not implemented) handled gracefully, matching the `SyncDmbdStatusToArkfleet` pattern.
- `tests/Feature/FeatureFlag/CannibalBetaFlagTest.php` — with `FEATURE_CANNIBAL_BETA=false`, all Phase 8 routes return 404/403 and the sidebar menu omits Component/Cannibal entries.

---

## 3. Full Route Table

All Inertia page routes live in `routes/web.php` under the `auth` middleware group (Sanctum SPA session guard) plus `EnsureProjectScope` where a project context is required. Machine-consumable/JSON-only endpoints (used by the SPA for async lookups, not full page loads) live in `routes/api.php` under `auth:sanctum`. Route names use dot notation matching the resource (`plant-requests.index`, etc.).

### 3.1 Auth & Admin (Phase 0)

**`routes/web.php`**

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/login` | `Auth\AuthenticatedSessionController@create` | `login` | `guest` |
| POST | `/login` | `Auth\AuthenticatedSessionController@store` | — | `guest`, `throttle:5,1` |
| POST | `/logout` | `Auth\AuthenticatedSessionController@destroy` | `logout` | `auth` |
| GET | `/dashboard` | `DashboardController@index` | `dashboard` | `auth`, `EnsureProjectScope` |
| GET | `/admin/projects` | `Admin\ProjectController@index` | `admin.projects.index` | `auth`, `can:manage,Role` |
| POST | `/admin/projects/sync` | `Admin\ProjectController@sync` | `admin.projects.sync` | `auth`, `can:manage,Role` |
| GET | `/admin/users` | `Admin\UserController@index` | `admin.users.index` | `auth`, `can:manage,Role` |
| POST | `/admin/users` | `Admin\UserController@store` | `admin.users.store` | `auth`, `can:manage,Role` |
| PATCH | `/admin/users/{user}` | `Admin\UserController@update` | `admin.users.update` | `auth`, `can:manage,Role` |
| DELETE | `/admin/users/{user}` | `Admin\UserController@destroy` | `admin.users.destroy` | `auth`, `can:manage,Role` |
| POST | `/admin/users/{user}/roles` | `Admin\UserController@assignRole` | `admin.users.assign-role` | `auth`, `can:manage,Role` |
| GET | `/admin/roles` | `Admin\RoleController@index` | `admin.roles.index` | `auth`, `can:manage,Role` |
| POST | `/admin/roles` | `Admin\RoleController@store` | `admin.roles.store` | `auth`, `can:manage,Role` |
| PATCH | `/admin/roles/{role}` | `Admin\RoleController@update` | `admin.roles.update` | `auth`, `can:manage,Role` |
| POST | `/admin/roles/{role}/permissions` | `Admin\RoleController@syncPermissions` | `admin.roles.sync-permissions` | `auth`, `can:manage,Role` |

### 3.2 Budget (Phase 1)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/budget` | `BudgetController@index` | `budget.index` | `auth`, `EnsureProjectScope` |
| POST | `/budget/periods` | `BudgetController@store` | `budget.periods.store` | `auth`, `can:manage,BudgetPeriod` |
| PATCH | `/budget/allocations/{allocation}` | `BudgetController@updateAllocation` | `budget.allocations.update` | `auth`, `can:update,allocation` |
| GET | `/budget/variance` | `BudgetController@variance` | `budget.variance` | `auth` |
| POST | `/budget/carry-forward/run` | `BudgetController@runCarryForward` | `budget.carry-forward.run` | `auth`, `can:manage,BudgetPeriod` |

### 3.3 Plant Request + Approvals (Phase 2)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/plant-requests` | `PlantRequestController@index` | `plant-requests.index` | `auth`, `EnsureProjectScope` |
| GET | `/plant-requests/create` | `PlantRequestController@create` | `plant-requests.create` | `auth`, `can:create,PlantRequest` |
| POST | `/plant-requests` | `PlantRequestController@store` | `plant-requests.store` | `auth`, `can:create,PlantRequest` |
| GET | `/plant-requests/{plantRequest}` | `PlantRequestController@show` | `plant-requests.show` | `auth`, `can:view,plantRequest` |
| POST | `/plant-requests/{plantRequest}/submit` | `PlantRequestController@submit` | `plant-requests.submit` | `auth`, `can:update,plantRequest` |
| POST | `/plant-requests/{plantRequest}/comments` | `PlantRequestController@addComment` | `plant-requests.comments.store` | `auth`, `can:view,plantRequest` |
| POST | `/plant-requests/{plantRequest}/stock-check` | `PlantRequestController@stockCheck` | `plant-requests.stock-check` | `auth`, `can:stockCheck,plantRequest` (Logistic Foreman/PIC) |
| POST | `/approvals/{requestApproval}/decide` | `ApprovalController@decide` | `approvals.decide` | `auth`, `can:decide,requestApproval` |

**`routes/api.php`** (async lookups consumed by the wizard)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/api/sap/material-requests/search` | `Api\SapLookupController@searchMr` | `api.sap.mr.search` | `auth:sanctum` |
| GET | `/api/pricing/estimate` | `Api\PricingController@estimate` | `api.pricing.estimate` | `auth:sanctum` |

### 3.4 Tabulation Bid + Auto PO (Phase 3)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/tabulation-bids` | `TabulationBidController@index` | `tabulation-bids.index` | `auth` |
| GET | `/tabulation-bids/create` | `TabulationBidController@create` | `tabulation-bids.create` | `auth`, `can:create,TabulationBid` |
| POST | `/tabulation-bids` | `TabulationBidController@store` | `tabulation-bids.store` | `auth`, `can:create,TabulationBid` |
| GET | `/tabulation-bids/{tabulationBid}/review` | `TabulationBidController@review` | `tabulation-bids.review` | `auth`, `can:review,tabulationBid` |
| POST | `/tabulation-bids/{tabulationBid}/award` | `TabulationBidController@award` | `tabulation-bids.award` | `auth`, `can:award,tabulationBid` |
| POST | `/tabulation-bids/{tabulationBid}/create-po` | `TabulationBidController@createPo` | `tabulation-bids.create-po` | `auth`, `can:createPo,tabulationBid` |
| POST | `/tabulation-bids/{tabulationBid}/approve-po` | `TabulationBidController@approvePo` | `tabulation-bids.approve-po` | `auth`, `can:decide` |

### 3.5 SAP Sync Dashboard (Phase 4)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/sap/sync-dashboard` | `Sap\SyncDashboardController@index` | `sap.sync-dashboard` | `auth`, `can:viewSapDashboard` |
| POST | `/sap/sync-dashboard/{sapSyncLog}/retry` | `Sap\SyncDashboardController@retry` | `sap.sync-dashboard.retry` | `auth`, `can:viewSapDashboard` |
| GET | `/sap/test-connection` | `Sap\TestConnectionController@index` | `sap.test-connection` | `auth`, `can:viewSapDashboard` |

### 3.6 DMBD (Phase 5)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/dmbd` | `DmbdController@index` | `dmbd.index` | `auth`, `EnsureProjectScope` |
| POST | `/dmbd` | `DmbdController@store` | `dmbd.store` | `auth`, `can:create,DmbdEntry` |
| GET | `/dmbd/{equipment}/history` | `DmbdController@history` | `dmbd.history` | `auth` |
| GET | `/dmbd/{dmbdEntry}/prefill-request` | `DmbdController@prefillRequest` | `dmbd.prefill-request` | `auth`, `can:create,PlantRequest` |

### 3.7 Overbudget, Cancellation, Interchange (Phase 6)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/overbudget` | `OverbudgetController@index` | `overbudget.index` | `auth` |
| GET | `/overbudget/create` | `OverbudgetController@create` | `overbudget.create` | `auth` |
| POST | `/overbudget` | `OverbudgetController@store` | `overbudget.store` | `auth`, `can:create,OverbudgetRequest` |
| GET | `/plant-requests/{plantRequest}/cancel` | `CancellationController@create` | `cancellation.create` | `auth`, `can:cancel,plantRequest` |
| POST | `/plant-requests/{plantRequest}/cancel` | `CancellationController@store` | `cancellation.store` | `auth`, `can:cancel,plantRequest` |
| POST | `/cancellation-requests/{cancellationRequest}/agree` | `CancellationController@agree` | `cancellation.agree` | `auth`, `can:agree,cancellationRequest` |
| GET | `/interchange` | `InterchangeController@index` | `interchange.index` | `auth` |
| POST | `/interchange` | `InterchangeController@store` | `interchange.store` | `auth`, `can:create,InterchangeMap` |
| POST | `/interchange/{interchangeMap}/signoff` | `InterchangeController@signoff` | `interchange.signoff` | `auth`, `can:signoff,interchangeMap` |

### 3.8 Reporting (Phase 7)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/reports/budget-consumption` | `ReportController@budgetConsumption` | `reports.budget-consumption` | `auth`, `can:view,budget-consumption-report` |
| GET | `/reports/vendor-performance` | `ReportController@vendorPerformance` | `reports.vendor-performance` | `auth`, `can:view,vendor-performance-report` |
| GET | `/reports/equipment-cost` | `ReportController@equipmentCost` | `reports.equipment-cost` | `auth`, `can:view,equipment-cost-report` |
| GET | `/reports/{reportType}/export/pdf` | `ReportController@exportPdf` | `reports.export.pdf` | `auth`, `can:export,{reportType}` |
| GET | `/reports/{reportType}/export/csv` | `ReportController@exportCsv` | `reports.export.csv` | `auth`, `can:export,{reportType}` |

### 3.9 Component DB + Cannibal — Beta (Phase 8)

| Method | URI | Controller@method | Name | Middleware |
|---|---|---|---|---|
| GET | `/components` | `ComponentController@index` | `components.index` | `auth`, `feature:cannibal_beta` |
| POST | `/components` | `ComponentController@store` | `components.store` | `auth`, `can:create,Component`, `feature:cannibal_beta` |
| PATCH | `/components/{component}/status` | `ComponentController@updateStatus` | `components.update-status` | `auth`, `can:update,Component`, `feature:cannibal_beta` |
| GET | `/cannibal-requests` | `CannibalController@index` | `cannibal-requests.index` | `auth`, `feature:cannibal_beta` |
| GET | `/cannibal-requests/create` | `CannibalController@create` | `cannibal-requests.create` | `auth`, `can:create,CannibalRequest`, `feature:cannibal_beta` |
| POST | `/cannibal-requests` | `CannibalController@store` | `cannibal-requests.store` | `auth`, `can:create,CannibalRequest`, `feature:cannibal_beta` |

---

## 4. Frontend Component Tree

```
resources/js/
├── app.tsx                              # Inertia root, AntD ConfigProvider (id_ID locale), Reverb echo bootstrap
├── ssr.tsx                              # (optional — skip unless SEO/first-paint becomes a requirement for an internal app)
├── Pages/
│   ├── Auth/
│   │   └── Login.tsx
│   ├── Dashboard.tsx                    # role-aware landing widgets (Phase 0, extended per phase)
│   ├── Budget/
│   │   ├── Index.tsx                    # Finance Director editable + Plant read-only view (Phase 1)
│   │   └── Setting.tsx                  # create budget period / bulk allocation (Phase 1)
│   ├── PlantRequest/
│   │   ├── Index.tsx                    # list + lifecycle stepper column (Phase 2)
│   │   ├── Create.tsx                   # wizard: equipment → MR → lines → budget impact (Phase 2)
│   │   └── Show.tsx                     # detail + approval timeline + comments (Phase 2)
│   ├── TabulationBid/
│   │   ├── Index.tsx                    # queue (Phase 3)
│   │   ├── Create.tsx                   # SAP PR + 2-3 vendor entry (Phase 3)
│   │   └── Review.tsx                   # comparison + award + Create PO (Phase 3)
│   ├── Sap/
│   │   └── SyncDashboard.tsx            # sap_sync_logs health, retry, circuit breaker state (Phase 4)
│   ├── Dmbd/
│   │   └── Index.tsx                    # daily grid, mobile-first (Phase 5)
│   ├── Overbudget/
│   │   └── Index.tsx                    # escalation modal + director approval queue (Phase 6)
│   ├── Cancellation/
│   │   └── Index.tsx                    # cancellation modal + agreement queue (Phase 6)
│   ├── Interchange/
│   │   └── Index.tsx                    # P/N mapping table + sign-off (Phase 6)
│   ├── Reports/
│   │   ├── BudgetConsumption.tsx        # (Phase 7)
│   │   ├── VendorPerformance.tsx        # (Phase 7)
│   │   └── EquipmentCost.tsx            # (Phase 7)
│   ├── Admin/
│   │   ├── Projects.tsx                 # (Phase 0)
│   │   ├── Users.tsx                    # (Phase 0)
│   │   └── Roles.tsx                    # (Phase 0)
│   ├── Component/
│   │   └── Index.tsx                    # hierarchical component tree (Phase 8, Beta)
│   └── Cannibal/
│       └── Index.tsx                    # 4-level approval chain UI (Phase 8, Beta)
├── Components/
│   ├── BudgetProgressBar.tsx            # green<90% / amber90-110% / red>110% (Phase 1, reused everywhere budget is shown)
│   ├── LifecycleStepper.tsx             # MR→PR→PO→GRPO→Issued (Phase 2, reused in Show + Index)
│   ├── ApprovalQueue.tsx                # generic pending-approval table w/ inline decide (Phase 2, reused Phase 3/6/8)
│   └── VendorComparisonTable.tsx        # side-by-side vendor grid w/ best-value highlight (Phase 3)
├── Layouts/
│   └── AppLayout.tsx                    # role-aware sidebar, notification bell (Reverb), locale switcher (Phase 0)
└── Hooks/
    ├── useArkfleet.ts                   # equipment/project/plant-type/unit-status fetchers w/ stale flag (Phase 0)
    ├── useReverbNotifications.ts        # subscribes to private per-user + per-role channels (Phase 2+)
    └── useCurrency.ts                   # Rp 1.234.567.890,00 formatter, IDR locale helper (Phase 1+)
```

**Conventions for this tree:**
- Every `Pages/*` component receives its data exclusively via Inertia props from the corresponding controller — no client-side data-fetching for the initial page render (only for in-page async lookups like the MR search box, which go through `routes/api.php`).
- Shared domain widgets (`BudgetProgressBar`, `LifecycleStepper`, `ApprovalQueue`, `VendorComparisonTable`) live in `Components/`, never duplicated per-page.
- All new pages import the currency/date formatting helpers from `Hooks/useCurrency.ts` — never hand-roll `Intl.NumberFormat` calls inline (keeps `Rp 1.234.567.890,00` formatting consistent everywhere per `docs/concept.md` §9.5).

---

## 5. Testing Strategy

Test runner: **Pest** (`pestphp/pest` + `pestphp/pest-plugin-laravel`), per the `--dev` install in §1.2. Every Feature test in this document (see each phase's §J) runs against an **in-memory or transactional test database** (`RefreshDatabase` trait) for the PMB `mysql` connection. SAP (`sap_sql`) and ARKFLEET calls are **always mocked** in tests — no test ever hits the real `arkasrv2` host or the real ARKFLEET API, per the "API integration tests (mock ARKFLEET + SAP)" requirement.

### 5.1 Factory Definitions (one per model, by phase)

| Phase | Factories |
|---|---|
| 0 | `UserFactory` (extend default, add `division`/`employee_no`/`project_code_scope` states), `RoleFactory`, `PermissionFactory` |
| 1 | `BudgetPeriodFactory` (states: `draft`, `open`, `locked`), `BudgetAllocationFactory`, `BudgetLedgerFactory` (a state per `entry_type`: `allocation()`, `commitment()`, `actual()`, `carryForward()`, `reversal()`, `overbudget()`) |
| 2 | `PlantRequestFactory` (states per `status` value), `PlantRequestLineFactory`, `RequestApprovalFactory` (states: `pending`, `approved`, `rejected`, `returned`), `RequestCommentFactory` |
| 3 | `TabulationBidFactory`, `TabulationBidVendorFactory`, `TabulationBidAwardFactory` |
| 4 | No Eloquent factories for `App\Models\Sap\*` (read-only external data — tests use `Http::fake()`/mocked `sqlsrv` results instead); `SapSyncLogFactory` |
| 5 | `DmbdEntryFactory` (states: `rfu`, `standby`, `breakdown`) |
| 6 | `OverbudgetRequestFactory`, `CancellationRequestFactory` (states per `po_stage`), `InterchangeMapFactory` |
| 7 | none (read-only reporting; tests seed via Phase 1/3 factories) |
| 8 | `ComponentFactory` (states per `level`), `CannibalRequestFactory` |

### 5.2 Seeders

- `RoleSeeder` — seeds all roles from the role matrix §6 of `docs/concept.md` (`planner`, `mechanic`, `project_manager`, `plant_manager`, `buyer`, `procurement_manager`, `procurement_admin`, `logistic_foreman`, `logistic_pic`, `finance_director`, `operation_director`, `president_director`, `it_manager`, `aml_manager`, `aml_dept_head`).
- `PermissionSeeder` — one permission per capability row in the role matrix (`budget.view`, `budget.set`, `plant_request.create`, `plant_request.approve.pm`, `plant_request.approve.plant_mgr`, `dmbd.update`, `tabulation_bid.create`, `tabulation_bid.review`, `po.create`, `po.approve`, `overbudget.approve.fin_dir`, `overbudget.approve.ops_dir`, `cancellation.plant`, `cancellation.procurement`, `interchange.manage`, `grpo.verify`, `component.maintain`, `cannibal.create`, `cannibal.approve.1..4`, `project.setup`, `user.manage`, `reports.view`, `reports.export`) mapped to roles per the matrix table exactly.
- `ProjectSeeder` — a handful of sample `project_code`s (`PRJ-BRD`, etc.) mirroring what ARKFLEET's `/projects` would return, used only for local dev seeding when ARKFLEET isn't reachable; production never uses this seeder (projects always sync live from ARKFLEET).
- `DemoUserSeeder` — one demo user per role for manual QA/UAT, each with a memorable email (`planner@pmb.test`, `finance.director@pmb.test`, etc.) and a known password, **gated behind `app()->environment('local', 'testing')`** so it never runs in production.
- `RequestApprovalChainSeeder` — not a data seeder in the traditional sense but a **config/constant registry** (`config/approval_chains.php`) consumed by `ApprovalEngine::initiate()`; introduced in Phase 2, extended in Phases 3/6/8. Modeled as a config file rather than a DB table because chain templates are code-level business rules, not admin-editable data (per the fixed approval depths in `docs/concept.md` §1.4).

### 5.3 API Integration Tests (Mocked ARKFLEET + SAP)

- **ARKFLEET:** all tests use Laravel's `Http::fake()` against `config('services.arkfleet.base_url')`, with fixture JSON files under `tests/Fixtures/arkfleet/*.json` mirroring the exact shapes from `docs/concept.md` §8.2 — including at least one fixture for the **raw paginator** `projects/index` response and one for a normal `{data}`-wrapped response, so `ArkfleetResponseNormalizer` is exercised against both real shapes, not just idealized ones.
- **SAP Service Layer:** `Http::fake()` against `config('services.sap.server_url')`; a base `SapServiceTestCase` (in `tests/TestCase.php` subclass or a trait `tests/Concerns/FakesSapServiceLayer.php`) provides `fakeSapLogin()`, `fakeSapPurchaseOrderCreate(array $response)`, `fakeSap401ThenSuccess()` helpers so every SAP-write job test doesn't hand-roll cookie-flow mocking from scratch.
- **SAP Direct SQL (`sap_sql`):** tests never connect to a real SQL Server. `SapReadRepository` methods that hit `sap_sql` are tested via **constructor-injected fakes** — `SapReadRepository` depends on an interface (`SapSqlReaderContract`) with a `RealSapSqlReader` (production, uses `DB::connection('sap_sql')`) and `FakeSapSqlReader` (test, returns fixture arrays) binding swapped in `tests/TestCase.php` for the whole suite, since `sqlsrv` may not even be installed on CI runners.

### 5.4 Budget Ledger Integrity Tests

Beyond the per-phase tests already listed in Phase 1 §J, add a cross-cutting suite:

- `tests/Feature/Budget/LedgerImmutabilitySuite.php` — parametrized over all six `entry_type`s, asserting **none** can be updated or deleted via any code path reachable from a controller (attempts return 403/422, and direct model manipulation throws per the `booted()` guard in `BudgetLedger`).
- `tests/Feature/Budget/LedgerReversalCorrectnessSuite.php` — every reversal-producing action across the whole app (allocation revision, request rejection, cancellation agreement, GRPO reconciliation) is asserted to post a reversal whose `amount` is the exact negation of the entry it reverses, and that the sum of `(original + reversal)` for that `ref_type`/`ref_id` pair is always zero.
- `tests/Feature/Budget/CarryForwardIdempotencySuite.php` — running `CarryForwardJob` 1x, 2x, and 5x in a row for the same period produces identical final ledger state (already covered per-phase; this suite additionally fuzzes across multiple allocations with mixed over/under-budget states in a single run).

### 5.5 Approval Chain Tests (Happy Path + Rejection + SoD)

A shared test helper `tests/Concerns/TestsApprovalChains.php` provides `assertChainAdvances(Model $approvable, array $roleSequence)` and `assertSoDBlocks(Model $approvable, User $conflictedUser)`, reused across:

- Plant Request (2-step: PM → Plant Mgr) — Phase 2.
- Tabulation Bid → PO (Proc Mgr review → Admin Create PO gate → President Director PO approval) — Phase 3. Note the "Admin Create PO" gate is **not** a `request_approvals` step but a policy-gated action (`can:createPo`) — the shared helper's `assertSoDBlocks` is used here to verify the Buyer/Admin conflict specifically, separate from the chain-advancement assertions used for the two true `request_approvals` steps.
- Overbudget (2-step: Finance Director → Operation Director) — Phase 6.
- Cancellation (mutual agreement, not a strict ordered chain — tested separately, not via the shared chain helper) — Phase 6.
- Cannibal (4-step: Plant Mgr → AML Mgr → Ops Dir → Pres Dir) — Phase 8.

Every chain test asserts: (a) happy path reaches the terminal `approved` state in exactly the right step order, (b) a rejection at any step halts and reverts state correctly (including ledger reversal where applicable), (c) a user without the required role for the *current* step is blocked (403) even if they hold a role required by a *later* step, (d) project-scoped role assignments (`role_user.project_code`) block cross-project approval attempts.

---

## 6. Conventions & Code Organization

### 6.1 Directory Structure

```
app/
├── Console/
│   └── Commands/                 # WarmArkfleetCache, TestSapConnection, SyncSapPricing, SyncSapVendors, RunCarryForward, RetryDmbdSync
├── Http/
│   ├── Controllers/
│   │   ├── Admin/                # ProjectController, UserController, RoleController
│   │   ├── Api/                  # SapLookupController, PricingController (JSON-only, routes/api.php)
│   │   ├── Sap/                  # SyncDashboardController, TestConnectionController
│   │   ├── BudgetController.php
│   │   ├── PlantRequestController.php
│   │   ├── ApprovalController.php
│   │   ├── TabulationBidController.php
│   │   ├── DmbdController.php
│   │   ├── OverbudgetController.php
│   │   ├── CancellationController.php
│   │   ├── InterchangeController.php
│   │   ├── ReportController.php
│   │   ├── ComponentController.php
│   │   └── CannibalController.php
│   ├── Middleware/
│   │   ├── EnsureProjectScope.php
│   │   ├── EnsureRole.php
│   │   ├── EnsurePermission.php
│   │   └── EnsureFeatureEnabled.php   # generic `feature:{flag}` middleware backing the cannibal_beta gate
│   └── Requests/                 # one FormRequest per write action, namespaced flat (no sub-folders needed at this scale)
├── Jobs/
│   ├── CarryForwardJob.php
│   ├── CreateSapPurchaseRequest.php
│   ├── CreateSapPurchaseOrder.php
│   ├── PollSapPoStatus.php
│   ├── ReconcileGrpoToLedger.php
│   ├── NightlyReconciliation.php
│   ├── SyncDmbdStatusToArkfleet.php
│   ├── SyncInterchangeToSap.php
│   └── SyncComponentMovementToArkfleet.php
├── Models/
│   ├── Sap/                      # MaterialRequest, PurchaseRequest, PurchaseOrder, Grpo, VendorMaster, PriceList — all $connection = 'sap_sql'
│   ├── User.php, Role.php, Permission.php
│   ├── BudgetPeriod.php, BudgetAllocation.php, BudgetLedger.php
│   ├── PlantRequest.php, PlantRequestLine.php, RequestApproval.php, RequestComment.php
│   ├── TabulationBid.php, TabulationBidVendor.php, TabulationBidAward.php
│   ├── OverbudgetRequest.php, CancellationRequest.php, InterchangeMap.php
│   ├── DmbdEntry.php
│   ├── Component.php, CannibalRequest.php
│   └── SapSyncLog.php
├── Notifications/                # PlantRequestSubmitted, PlantRequestApprovalNeeded, PoApprovalNeeded, PoCreationFailed, PricingGapDetected, ...
├── Policies/                     # one per domain model — BudgetAllocationPolicy, PlantRequestPolicy, TabulationBidPolicy, DmbdEntryPolicy, OverbudgetRequestPolicy, CancellationRequestPolicy, InterchangeMapPolicy, ComponentPolicy, CannibalRequestPolicy, ReportPolicy. (Role/permission authorization: spatie handles via Gate::before + middleware; no RolePolicy needed.)
└── Services/
    ├── Arkfleet/                 # ArkfleetClient, EquipmentCache, ArkfleetResponseNormalizer
    ├── Sap/                      # SapService (singleton), SapReadRepository, SapCircuitBreaker
    ├── Budget/                   # BudgetEngine, VarianceCalculator
    ├── Approval/                 # ApprovalEngine
    ├── Pricing/                  # PricingEstimator
    └── Reporting/                # BudgetConsumptionReport, VendorPerformanceReport, EquipmentCostReport
```

### 6.2 Naming Conventions

| Category | Convention | Examples |
|---|---|---|
| Service classes | `{Domain}Engine` for stateful orchestrators, `{Noun}Client`/`{Noun}Repository` for I/O boundaries, `{Noun}Calculator`/`{Noun}Estimator` for pure computation | `BudgetEngine`, `ApprovalEngine`, `ArkfleetClient`, `SapReadRepository`, `VarianceCalculator`, `PricingEstimator` |
| Jobs | `{Verb}{Noun}Job` or `{Verb}{Noun}` when the job name already reads as an imperative action | `CarryForwardJob`, `CreateSapPurchaseOrder`, `SyncDmbdStatusToArkfleet`, `ReconcileGrpoToLedger` |
| Policies | `{Model}Policy`, ability names are verbs matching controller method names (`create`, `update`, `view`, `decide`, `award`, `createPo`, `signoff`, `agree`, `cancel`) | `PlantRequestPolicy::submit()`, `TabulationBidPolicy::createPo()` |
| FormRequests | `{Verb}{Model}Request` | `StorePlantRequestRequest`, `ApprovalDecisionRequest` |
| Migrations (pivot tables) | alphabetical table-name order per user rule | `cannibal_request_component`, `create_permission_tables` (spatie published) |
| Route names | dot notation, resource-first | `plant-requests.submit`, `tabulation-bids.create-po` |

### 6.3 Monetary & Cached-Field Conventions

- **Every monetary column is `DECIMAL(18,2)`, currency IDR.** No `float`/`double` column is ever used for money. PHP-side arithmetic on money uses `bcmath` functions (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bccomp`) exclusively — never native `+`/`-`/`*`/`/` on decimal-cast Eloquent attributes, to avoid float-precision drift across the ledger.
- **UI formatting:** `Rp 1.234.567.890,00` via the shared `Hooks/useCurrency.ts` helper (Indonesian locale: `.` thousands separator, `,` decimal separator).
- **Cached/denormalized fields carry a `_cache` suffix** (`unit_code_cache`, `project_name_cache`, `plant_type_cache`) — these are always overwritten from the external source of truth (ARKFLEET) at write time and are never treated as authoritative in business-rule checks. Financial validation (e.g., 110% tolerance) always re-reads live/cached-with-TTL external data through the service layer, never the `_cache` column on an old record for anything other than display.

### 6.4 SAP Model Conventions

- Every class under `App\Models\Sap\` declares `protected $connection = 'sap_sql';` and `protected $guarded = ['*'];` — no exceptions, even for a "just this once" read-modify convenience. Writes happen exclusively through `SapService`'s HTTP methods (Service Layer), never through these Eloquent models' `save()`/`create()`.
- SAP table names keep their native SAP B1 names (`OPRQ`, `OPOR`, `OPDN`, `OCRD`, `ITM1`) rather than translating to English pseudo-names — this matches what any SAP consultant or DBA reading the codebase will recognize immediately, and avoids a confusing extra mapping layer.

### 6.5 Validation

- One `FormRequest` class per write action (not one per model) — e.g., both `StorePlantRequestRequest` (draft creation) and `SubmitPlantRequestRequest` (submission, which re-validates business rules like "≥1 line" that don't apply to a draft) exist separately, because the validation rules genuinely differ by action, not just by model.
- Cross-field/business-rule validation (110% tolerance, SoD checks, stage gates) lives in FormRequest `withValidator()` closures or a dedicated `after()` hook calling into the relevant service (`BudgetEngine::validateAgainstTolerance`), **not** duplicated ad-hoc in the controller.

### 6.6 Queue Jobs

- Every job that writes to SAP or ARKFLEET lives in `app/Jobs/` (flat, no sub-namespacing — the SAP/ARKFLEET/Budget prefix in the class name itself is sufficient signal) and uses `$this->onQueue('name')` set in the constructor — **never** `public $queue = 'name';` (Pitfall P6, breaks on the Queueable trait in newer PHP).
- Queue names: `sap-writes` (PR/PO creation, GRPO reconciliation, interchange sync — highest priority, monitored closely in Horizon), `budget` (carry-forward), `notifications` (Reverb broadcasts, emails), `default` (everything else, e.g. ARKFLEET status sync retries).
- Every SAP-write job carries an explicit idempotency key persisted to `sap_sync_logs.correlation_key` (Phase 4) — no SAP-write job is ever "fire and forget" without a durable, queryable record of the attempt.

### 6.7 Policies

One Policy class per domain model that has non-trivial authorization logic (see the `app/Policies/` listing in §6.1). Simple cross-cutting gates that aren't tied to a single model's CRUD lifecycle (e.g., `viewSapDashboard`, `feature:cannibal_beta`) are `Gate::define()` closures in `AppServiceProvider::boot()` instead of full Policy classes — avoids empty boilerplate Policy files for single-ability checks.

---

## 7. Critical Pitfalls to Avoid

| # | Pitfall | Where it bites | Guard (enforced in this plan) |
|---|---|---|---|
| P1 | `maatwebsite/excel` fails on PHP ≥8.5 (and is banned regardless of PMB's 8.3/8.4 target, to keep the dependency tree clean) | Phase 7 exports | **Never installed.** Phase 7 §A explicitly lists CSV via native `fputcsv`/`League\Csv` and PDF via `barryvdh/laravel-dompdf` only. Enforced by a static check in `ExportTest.php`. |
| P2 / P9 | `npm install` stalls or produces broken installs on AntD Pro peer deps | Every `npm install`/`npm ci`, not just the first one | `--legacy-peer-deps` on **every single** invocation for the lifetime of the project (§1.2). This is called out again here because it is the single most common "forgot on the second install" mistake. |
| P3 | SAP writes via raw SQL corrupt ERP integrity (business logic, numbering series, and validation live in SAP's Service Layer, not the raw tables) | Any temptation to "just INSERT into OPOR directly, it's faster" | All SAP writes go through `SapService` (Phase 4) using the Service Layer REST API exclusively, wrapped in queued jobs with idempotency keys (`sap_sync_logs.correlation_key`). `App\Models\Sap\*` models are `$guarded = ['*']` as a code-level guard-rail against accidental `save()` calls. |
| P4 | PHP 8.5 static properties on traits break | Any trait shared across services/jobs | PMB targets 8.3/8.4 (not 8.5) specifically to avoid this class of bug entirely, but the codebase still avoids trait statics as a forward-compatibility discipline — use class constants or constructor-injected instance state instead. |
| P5 | `pcre.jit=0` may be needed on some PHP 8.x hosts for regex-heavy validation | FormRequest regex rules (e.g., part-number pattern validation) | If regex validation on part numbers / SAP doc references exhibits unexplained failures/hangs on the production host, set `pcre.jit=0` in `php.ini` and document the exact symptom + fix in `MEMORY.md` immediately — don't pre-emptively disable JIT without an observed failure. |
| P6 | `public $queue` on Laravel Jobs conflicts with the Queueable trait on newer PHP | Every job class | **Every** job in this plan (`CarryForwardJob`, `CreateSapPurchaseOrder`, `CreateSapPurchaseRequest`, `PollSapPoStatus`, `ReconcileGrpoToLedger`, `SyncDmbdStatusToArkfleet`, `SyncInterchangeToSap`, `SyncComponentMovementToArkfleet`) uses `$this->onQueue('name')` inside the constructor — verified explicitly in each phase's job description above. |
| P7 | Duplicating ARKFLEET equipment or SAP transaction tables inside PMB's own schema | Any migration that tempts "let's just cache the full equipment row locally for easy joins" | PMB never creates an `equipment` or `OPOR`/`OPRQ`-shaped table of its own. Every reference is an unconstrained `unsignedBigInteger` FK column (no local foreign-key constraint, since the referenced table doesn't exist in this DB) plus `_cache` display columns for the minimum needed for UI/audit snapshotting (§6.3). |
| P8 | Skipping the ledger for a "quick fix" direct balance mutation | Any hotfix under deadline pressure touching `budget_allocations.committed_amount`/`actual_amount` | `BudgetLedger::booted()`'s `saving` guard throws on any update attempt; `BudgetAllocation.committed_amount`/`actual_amount` are documented as **derived-only** and only ever written by `BudgetEngine::recomputeCachedBalances()` (Phase 1 §E) — no controller, job, or Artisan command should ever call `$allocation->update(['committed_amount' => ...])` directly. Code review checklist item. |
| P10 | TypeScript syntax inside `.jsx` files breaks the Vite/rolldown toolchain | Frontend page/component files | This plan uses `.tsx` uniformly for all pages/components/hooks (§1.6) rather than mixing `.jsx` with inline TS annotations — sidesteps the pitfall entirely rather than managing around it. |
| P11 | `RateLimiter::for()` facade calls inside `->withMiddleware()` in `bootstrap/app.php` don't work on Laravel 11+ (facade not booted yet at that point) | Rate limiting login attempts, SAP write job dispatch throttling, ARKFLEET client retry backoff | Register all `RateLimiter::for(...)` definitions inside `->booting()` in `bootstrap/app.php`, never inside the `->withMiddleware()` closure. Applies to the `throttle:5,1` on `/login` (§3.1) and any future named limiter. |

### 7.1 Additional PMB-Specific Pitfalls (beyond the sister-project list)

| # | Pitfall | Guard |
|---|---|---|
| A1 | ARKFLEET's `projects`, `fixed-assets`, and `depreciation/*` **index** endpoints return a raw paginator with no `{data}` wrapper, while every other endpoint (including all **show/detail** endpoints) wraps in `{data: ...}` | `ArkfleetResponseNormalizer` (Phase 0 §E) detects both shapes and always returns a consistent `['data', 'meta']` array; tested explicitly against both fixture shapes (§5.3). Never assume `$response->json('data')` works uniformly across all ARKFLEET endpoints. |
| A2 | ARKFLEET's `PATCH /equipment/{id}/status` and component-status endpoints (`docs/concept.md` §8.5/§8.7) **do not exist yet** — building PMB features that assume they're live will silently no-op or throw in production | `SyncDmbdStatusToArkfleet` (Phase 5) and `SyncComponentMovementToArkfleet` (Phase 8) both catch `404` explicitly, log at `info` level (not `error` — this is an expected, documented state, not a bug), leave the `synced_to_arkfleet` flag false, and rely on a scheduled retry command so PMB starts working automatically the day ARKFLEET ships the endpoint — no PMB redeploy needed. |
| A3 | SAP B1 is **SQL Server**, not MySQL — a developer instinctively reaching for `DB::table(...)` on the default connection when they mean `sap_sql`, or using MySQL-specific SQL syntax (`LIMIT`, backtick identifiers) against SAP, will fail | Every SAP direct-SQL query is written through `SapReadRepository` (Phase 4 §E) using T-SQL syntax (`TOP`, square-bracket identifiers) and always specifies `DB::connection('sap_sql')` explicitly — never relies on a default-connection assumption. Code review checklist item: any raw `DB::` call touching SAP data must show `connection('sap_sql')` in the same line. |
| A4 | Singleton `SapService` session exhaustion if accidentally re-bound as non-singleton, or if a code path constructs `new SapService()` directly instead of resolving via the container | SAP session-limit errors under load (§7.8 in concept.md) | `SapService` is registered via `$this->app->singleton(SapService::class)` in `AppServiceProvider::register()` (Phase 4 §E) and **every** consumer resolves it via `app(SapService::class)` / constructor injection — never `new SapService()`. A grep for `new SapService(` returning zero results outside the class's own file is a valid CI/code-review check. |
| A5 | Ledger double-counting when a `commitment` converts to `actual` via GRPO reconciliation, if the offsetting reversal of the original commitment is forgotten | Budget figures silently inflate (a request's cost counted in both `commitment` and `actual` simultaneously) | `BudgetEngine::postActual()` (Phase 1 §E) **always** posts the `actual` entry **and** a matching `reversal` of the equivalent `commitment` amount in the same DB transaction — verified by `LedgerReversalCorrectnessSuite` (§5.4), which asserts the sum of `(original commitment + actual + reversal)` nets to exactly the actual spend, never double. |
| A6 | 110% tolerance is configurable per allocation (`tolerance_pct`), but a hardcoded `1.10` literal anywhere in code would silently ignore per-equipment-type overrides (e.g., a critical DIGGER unit configured at 15% tolerance) | `BudgetEngine::validateAgainstTolerance()` (Phase 1 §E) and `BudgetAllocation::getToleranceCapAttribute()` always read `$this->tolerance_pct` from the allocation row — never a hardcoded `1.10`/`0.10` constant anywhere in the codebase. Grep check: no literal `1.10` or `* 1.1` in `app/Services/Budget/` or `app/Http/Controllers/PlantRequestController.php`. |
| A7 | Forgetting to call `setPermissionsTeamId($projectCode)` before role/permission checks — spatie's Teams feature requires the team context to be set, or else `$user->hasRole('plant_manager')` returns false even for correctly assigned roles | `EnsureProjectScope` middleware (Phase 0 §I) sets the team ID on every request automatically. In queued jobs, CLI commands, and tests, call `setPermissionsTeamId($projectCode)` explicitly at the top of `handle()` / test `setUp()` — document this in the team onboarding guide. |

---

*End of implementation action plan. This document is a living companion to `docs/concept.md` — update `MEMORY.md` with any deviation discovered during actual implementation (e.g., a real ARKFLEET/SAP response shape that differs from what's documented here), and correct this file to match reality per the "Documentation Standards" in the workspace rules.*

