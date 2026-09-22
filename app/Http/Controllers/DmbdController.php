<?php

namespace App\Http\Controllers;

use App\Jobs\SyncDmbdStatusToArkfleet;
use App\Models\DmbdEntry;
use App\Models\ProjectCache;
use App\Services\Arkfleet\EquipmentCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DmbdController extends Controller
{
    private const STATUSES = ['rfu', 'standby', 'breakdown'];

    private const PER_PAGE_OPTIONS = [25, 50, 100];

    public function __construct(
        private readonly EquipmentCache $equipmentCache,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = now()->toDateString();

        $projectCode = $request->input('project_code')
            ?? session('current_project')
            ?? $user->project_code_scope
            ?? ProjectCache::query()->where('is_active', true)->value('project_code');

        $search = trim((string) $request->input('search', ''));
        $statusFilter = $request->input('status');
        if (! in_array($statusFilter, self::STATUSES, true)) {
            $statusFilter = null;
        }

        $listFilters = [];
        if ($projectCode !== null && $projectCode !== 'all') {
            $listFilters['project_code'] = $projectCode;
        }
        if ($search !== '') {
            $listFilters['search'] = $search;
        }

        $result = $this->equipmentCache->list($listFilters);
        $equipment = $result['data'] ?? [];

        usort(
            $equipment,
            fn (array $a, array $b) => strnatcasecmp((string) ($a['unit_code'] ?? ''), (string) ($b['unit_code'] ?? ''))
        );

        $allIds = array_values(array_unique(array_filter(
            array_map(fn (array $item) => (int) ($item['id'] ?? 0), $equipment)
        )));

        $entriesMap = collect();
        foreach (array_chunk($allIds, 500) as $chunk) {
            $entriesMap = $entriesMap->union(
                DmbdEntry::query()
                    ->where('report_date', $today)
                    ->whereIn('equipment_id', $chunk)
                    ->get()
                    ->keyBy('equipment_id')
            );
        }

        $statusSummary = array_fill_keys(self::STATUSES, 0);
        foreach ($equipment as $item) {
            $id = (int) ($item['id'] ?? 0);
            $status = $entriesMap->get($id)?->operational_status ?? 'rfu';
            $statusSummary[$status] = ($statusSummary[$status] ?? 0) + 1;
        }

        if ($statusFilter !== null) {
            $equipment = array_values(array_filter($equipment, function (array $item) use ($entriesMap, $statusFilter) {
                $id = (int) ($item['id'] ?? 0);
                $status = $entriesMap->get($id)?->operational_status ?? 'rfu';

                return $status === $statusFilter;
            }));
        }

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }
        $page = max(1, (int) $request->input('page', 1));

        $total = count($equipment);
        $pageItems = array_slice($equipment, ($page - 1) * $perPage, $perPage);

        $units = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page']
        );

        $pageIds = array_values(array_filter(
            array_map(fn (array $item) => (int) ($item['id'] ?? 0), $pageItems)
        ));
        $entries = $entriesMap->only($pageIds);

        $projects = ProjectCache::query()
            ->orderBy('project_code')
            ->get(['project_code', 'project_name']);

        return Inertia::render('Dmbd/Index', [
            'units' => $units,
            'entries' => $entries,
            'statusSummary' => $statusSummary,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $statusFilter,
                'project_code' => $projectCode,
            ],
            'perPage' => $perPage,
            'projectCode' => $projectCode,
            'reportDate' => $today,
            'projects' => $projects,
            'can' => [
                'update' => Gate::allows('create', DmbdEntry::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DmbdEntry::class);

        $isBreakdown = $request->input('operational_status') === 'breakdown';

        $validated = $request->validate([
            'equipment_id' => 'required|integer',
            'unit_code_cache' => 'required|string',
            'operational_status' => 'required|in:rfu,standby,breakdown',
            'breakdown_note' => [
                $isBreakdown ? 'required' : 'nullable',
                'string',
                ...($isBreakdown ? ['min:5'] : []),
                'max:500',
            ],
        ]);

        $entry = DmbdEntry::upsertForToday(
            $validated['equipment_id'],
            $validated['unit_code_cache'],
            $validated['operational_status'],
            $validated['breakdown_note'] ?? null,
            $request->user()->id
        );

        SyncDmbdStatusToArkfleet::dispatch($entry->id);
        $this->equipmentCache->bust($validated['equipment_id']);

        return back()->with('success', 'DMBD entry saved.');
    }

    public function prefillRequest(DmbdEntry $dmbdEntry): RedirectResponse
    {
        $this->authorize('create', \App\Models\PlantRequest::class);

        return redirect()->route('plant-requests.create', [
            'dmbd_entry_id' => $dmbdEntry->id,
            'equipment_id' => $dmbdEntry->equipment_id,
            'unit_code_cache' => $dmbdEntry->unit_code_cache,
        ]);
    }
}
