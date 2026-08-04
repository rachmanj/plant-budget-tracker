<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviseBudgetAllocationRequest;
use App\Http\Requests\StoreBudgetAllocationRequest;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\ProjectCache;
use App\Services\Arkfleet\ArkfleetClient;
use App\Services\Budget\BudgetEngine;
use App\Services\Budget\VarianceCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetEngine $budgetEngine,
        private readonly VarianceCalculator $varianceCalculator,
        private readonly ArkfleetClient $arkfleetClient,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $projectCode = $request->input('project_code')
            ?? session('current_project')
            ?? $user->project_code_scope
            ?? ProjectCache::query()->value('project_code')
            ?? 'MBL';

        $periods = BudgetPeriod::query()
            ->rollingWindow($projectCode)
            ->with(['allocations' => fn ($q) => $q->orderBy('unit_code_cache')])
            ->orderBy('period_month')
            ->get()
            ->map(function (BudgetPeriod $period) use ($user) {
                return [
                    'id' => $period->id,
                    'project_code' => $period->project_code,
                    'project_name_cache' => $period->project_name_cache,
                    'period_month' => $period->period_month->format('Y-m-d'),
                    'status' => $period->status,
                    'is_editable' => $period->isEditableBy($user),
                    'is_locked' => in_array($period->status, ['locked', 'closed'], true),
                    'allocations' => $period->allocations->map(fn (BudgetAllocation $allocation) => [
                        'id' => $allocation->id,
                        'equipment_id' => $allocation->equipment_id,
                        'unit_code_cache' => $allocation->unit_code_cache,
                        'plant_type_cache' => $allocation->plant_type_cache,
                        'allocated_amount' => (string) $allocation->allocated_amount,
                        'tolerance_pct' => (string) $allocation->tolerance_pct,
                        'carry_forward_in' => (string) $allocation->carry_forward_in,
                        'committed_amount' => (string) $allocation->committed_amount,
                        'actual_amount' => (string) $allocation->actual_amount,
                        'is_editable' => $allocation->is_editable,
                        'variance' => $allocation->variance,
                        'utilization_pct' => $allocation->utilization_pct,
                        'tolerance_cap' => $allocation->tolerance_cap,
                    ]),
                ];
            });

        $projects = ProjectCache::query()
            ->orderBy('project_code')
            ->get(['project_code', 'project_name']);

        return Inertia::render('Budget/Index', [
            'projectCode' => $projectCode,
            'projects' => $projects,
            'periods' => $periods,
            'canManage' => $user->hasRole('finance_director'),
            'isFinanceDirector' => $user->hasRole('finance_director'),
        ]);
    }

    public function setting(Request $request): Response
    {
        $this->authorize('create', BudgetAllocation::class);

        $projects = ProjectCache::query()
            ->orderBy('project_code')
            ->get(['project_code', 'project_name']);

        return Inertia::render('Budget/Setting', [
            'projects' => $projects,
            'defaultProjectCode' => $request->input('project_code')
                ?? session('current_project')
                ?? $request->user()->project_code_scope,
        ]);
    }

    public function store(StoreBudgetAllocationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $periodMonth = Carbon::parse($validated['period_month'])->startOfMonth();

        $projectName = ProjectCache::query()
            ->where('project_code', $validated['project_code'])
            ->value('project_name');

        if (! $projectName) {
            try {
                $project = $this->arkfleetClient->getProject($validated['project_code']);
                $projectName = $project['name'] ?? $project['project_name'] ?? $validated['project_code'];
            } catch (\Throwable) {
                $projectName = $validated['project_code'];
            }
        }

        $period = BudgetPeriod::query()->firstOrCreate(
            [
                'project_code' => $validated['project_code'],
                'period_month' => $periodMonth->toDateString(),
            ],
            [
                'project_name_cache' => $projectName,
                'status' => $validated['status'] ?? 'open',
                'created_by' => $request->user()->id,
            ]
        );

        $this->budgetEngine->allocate($period, $validated['allocations'], $request->user());

        return redirect()
            ->route('budget.index', ['project_code' => $validated['project_code']])
            ->with('success', 'Anggaran berhasil disimpan.');
    }

    public function revise(ReviseBudgetAllocationRequest $request, BudgetAllocation $allocation): RedirectResponse
    {
        $validated = $request->validated();

        $this->budgetEngine->revise(
            $allocation,
            (string) $validated['allocated_amount'],
            $request->user(),
            isset($validated['tolerance_pct']) ? (string) $validated['tolerance_pct'] : null,
            $validated['memo'] ?? null
        );

        return back()->with('success', 'Alokasi anggaran berhasil direvisi.');
    }

    public function carryForward(Request $request, BudgetPeriod $period): RedirectResponse
    {
        $this->authorize('carryForward', $period);

        $this->budgetEngine->carryForwardPeriod($period);

        return back()->with('success', 'Carry-forward berhasil dijalankan.');
    }

    public function variance(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'project_code' => ['required', 'string'],
            'month' => ['required', 'date'],
            'plant_type' => ['nullable', 'in:DIGGER,HAULER,SUPPORT'],
        ]);

        $month = Carbon::parse($request->input('month'));

        if ($request->filled('plant_type')) {
            $data = $this->varianceCalculator->forPlantType(
                $request->input('project_code'),
                $request->input('plant_type'),
                $month
            );
        } else {
            $data = $this->varianceCalculator->forProject($request->input('project_code'), $month);
        }

        return response()->json(['data' => $data]);
    }
}
