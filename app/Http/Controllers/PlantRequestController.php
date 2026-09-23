<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivePlantRequestRequest;
use App\Jobs\CreateSapPurchaseRequest;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\PlantRequest;
use App\Models\ProjectCache;
use App\Models\SapSyncLog;
use App\Models\TabulationBid;
use App\Services\Approval\ApprovalEngine;
use App\Services\Arkfleet\EquipmentCache;
use App\Services\Budget\BudgetEngine;
use App\Services\Pricing\PricingEstimator;
use App\Support\ApprovalChains;
use App\Support\ProjectContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlantRequestController extends Controller
{
    public function __construct(
        private readonly BudgetEngine $budgetEngine,
        private readonly ApprovalEngine $approvalEngine,
        private readonly PricingEstimator $pricingEstimator,
        private readonly EquipmentCache $equipmentCache,
    ) {}

    public function index(Request $request): Response
    {
        $requests = PlantRequest::query()
            ->with(['allocation.period', 'requester'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return Inertia::render('PlantRequest/Index', [
            'requests' => $requests,
            'filters' => $request->only('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PlantRequest::class);

        $projectCode = ProjectContext::resolve($request);

        $equipmentResult = $this->equipmentCache->list(['project_code' => $projectCode]);
        $equipment = $this->mapEquipmentList($equipmentResult);
        $projectBudget = $this->projectBudgetForCurrentPeriod($projectCode);
        $projects = $this->projectList();

        return Inertia::render('PlantRequest/Create', [
            'prefill' => $request->only(['dmbd_entry_id', 'equipment_id', 'unit_code_cache', 'project_code']),
            ...$this->wizardSharedProps($projectCode, $projects, $equipment, $projectBudget),
        ]);
    }

    public function edit(Request $request, PlantRequest $plantRequest): Response
    {
        $this->authorize('update', $plantRequest);

        $plantRequest->load(['lines', 'allocation.period']);

        $projectCode = $plantRequest->allocation?->period?->project_code
            ?: ProjectContext::resolve($request);

        $equipmentResult = $this->equipmentCache->list(['project_code' => $projectCode]);
        $equipment = $this->mapEquipmentList($equipmentResult);
        $projectBudget = $this->projectBudgetForCurrentPeriod($projectCode);
        $projects = $this->projectList();

        return Inertia::render('PlantRequest/Edit', [
            'request' => $plantRequest,
            ...$this->wizardSharedProps($projectCode, $projects, $equipment, $projectBudget),
        ]);
    }

    public function update(Request $request, PlantRequest $plantRequest): RedirectResponse
    {
        $this->authorize('update', $plantRequest);

        $validated = $request->validate([
            'equipment_id' => 'required|integer',
            'unit_code_cache' => 'required|string',
            'dmbd_entry_id' => 'nullable|exists:dmbd_entries,id',
            'sap_mr_id' => 'required|integer',
            'lines' => 'required|array|min:1',
            'lines.*.part_number' => 'required|string',
            'lines.*.material_name' => 'required|string',
            'lines.*.uom' => 'required|string|max:10',
            'lines.*.qty' => 'required|integer|min:1',
            'lines.*.unit_price_est' => 'nullable|numeric',
            'lines.*.price_source' => 'nullable|in:tabulation_bid,sap_price,manual,none',
        ]);

        $projectCode = ProjectContext::resolve($request);
        $allocation = $this->resolveProjectAllocation($projectCode);

        DB::transaction(function () use ($validated, $plantRequest, $allocation) {
            $total = '0.00';
            $resolvedLines = [];

            foreach ($validated['lines'] as $line) {
                $price = $line['unit_price_est'] ?? null;
                $source = $line['price_source'] ?? 'none';

                if (! $price || $source === 'none') {
                    $estimate = $this->pricingEstimator->estimate($line['part_number']);
                    $price = $estimate['unit_price'];
                    $source = $estimate['source'];
                }

                $lineTotal = bcmul((string) $line['qty'], (string) $price, 2);
                $total = bcadd($total, $lineTotal, 2);

                $resolvedLines[] = [
                    'part_number' => $line['part_number'],
                    'material_name' => $line['material_name'],
                    'uom' => $line['uom'],
                    'qty' => $line['qty'],
                    'unit_price_est' => $price,
                    'price_source' => $source,
                ];
            }

            $plantRequest->update([
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => $validated['equipment_id'],
                'unit_code_cache' => $validated['unit_code_cache'],
                'dmbd_entry_id' => $validated['dmbd_entry_id'] ?? null,
                'sap_mr_id' => $validated['sap_mr_id'],
                'estimated_total' => $total,
            ]);

            $plantRequest->lines()->delete();

            foreach ($resolvedLines as $line) {
                $plantRequest->lines()->create($line);
            }
        });

        return redirect()->route('plant-requests.show', $plantRequest)
            ->with('success', 'Plant request draft updated.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PlantRequest::class);

        $validated = $request->validate([
            'equipment_id' => 'required|integer',
            'unit_code_cache' => 'required|string',
            'dmbd_entry_id' => 'nullable|exists:dmbd_entries,id',
            'sap_mr_id' => 'required|integer',
            'lines' => 'required|array|min:1',
            'lines.*.part_number' => 'required|string',
            'lines.*.material_name' => 'required|string',
            'lines.*.uom' => 'required|string|max:10',
            'lines.*.qty' => 'required|integer|min:1',
            'lines.*.unit_price_est' => 'nullable|numeric',
            'lines.*.price_source' => 'nullable|in:tabulation_bid,sap_price,manual,none',
        ]);

        $projectCode = ProjectContext::resolve($request);
        $allocation = $this->resolveProjectAllocation($projectCode);

        $plantRequest = DB::transaction(function () use ($validated, $request, $allocation) {
            $total = '0.00';
            foreach ($validated['lines'] as $line) {
                $price = $line['unit_price_est'] ?? null;
                $source = $line['price_source'] ?? 'none';

                if (! $price || $source === 'none') {
                    $estimate = $this->pricingEstimator->estimate($line['part_number']);
                    $price = $estimate['unit_price'];
                    $source = $estimate['source'];
                }

                $lineTotal = bcmul((string) $line['qty'], (string) $price, 2);
                $total = bcadd($total, $lineTotal, 2);
            }

            $plantRequest = PlantRequest::create([
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => $validated['equipment_id'],
                'unit_code_cache' => $validated['unit_code_cache'],
                'dmbd_entry_id' => $validated['dmbd_entry_id'] ?? null,
                'sap_mr_id' => $validated['sap_mr_id'],
                'estimated_total' => $total,
                'requested_by' => $request->user()->id,
                'status' => 'draft',
            ]);

            foreach ($validated['lines'] as $line) {
                $price = $line['unit_price_est'] ?? null;
                $source = $line['price_source'] ?? 'none';

                if (! $price || $source === 'none') {
                    $estimate = $this->pricingEstimator->estimate($line['part_number']);
                    $price = $estimate['unit_price'];
                    $source = $estimate['source'];
                }

                $plantRequest->lines()->create([
                    'part_number' => $line['part_number'],
                    'material_name' => $line['material_name'],
                    'uom' => $line['uom'],
                    'qty' => $line['qty'],
                    'unit_price_est' => $price,
                    'price_source' => $source,
                ]);
            }

            return $plantRequest;
        });

        return redirect()->route('plant-requests.show', $plantRequest)
            ->with('success', 'Plant request draft created.');
    }

    public function show(PlantRequest $plantRequest): Response
    {
        $plantRequest->load(['lines', 'allocation.period', 'approvals.approver', 'comments.author', 'requester', 'receiver']);

        $allocation = $plantRequest->allocation;
        $tolerance = $this->budgetEngine->validateAgainstTolerance(
            $allocation,
            (string) $plantRequest->estimated_total
        );

        $base = $tolerance['base'];
        $spent = bcadd((string) $allocation->committed_amount, (string) $allocation->actual_amount, 2);
        $tolerance = array_merge($tolerance, [
            'remaining' => bcsub($base, $spent, 2),
            'utilization_pct' => $allocation->utilization_pct,
            'committed_amount' => (string) $allocation->committed_amount,
            'actual_amount' => (string) $allocation->actual_amount,
            'tolerance_pct' => (string) $allocation->tolerance_pct,
            'message' => $this->toleranceExceededMessage($tolerance, $allocation),
        ]);

        $bid = $plantRequest->sap_pr_no
            ? TabulationBid::query()->where('sap_pr_id', $plantRequest->sap_pr_no)->latest()->first()
            : null;

        $prSyncLog = SapSyncLog::query()
            ->where('operation', 'create_pr')
            ->where('ref_type', 'plant_request')
            ->where('ref_id', $plantRequest->id)
            ->latest()
            ->first();

        return Inertia::render('PlantRequest/Show', [
            'request' => $plantRequest,
            'tolerance' => $tolerance,
            'procurement' => [
                'sap_po_id' => $plantRequest->sap_po_id ?: $bid?->sap_po_id,
                'sap_pr_created_at' => $prSyncLog?->completed_at,
                'sap_pr_sync_status' => $prSyncLog?->status,
                'sap_pr_sync_error' => $prSyncLog?->error_message,
            ],
            'can' => [
                'submit' => Gate::allows('submit', $plantRequest),
                'cancel' => Gate::allows('cancel', $plantRequest),
                'update' => Gate::allows('update', $plantRequest),
                'receive' => Gate::allows('receive', $plantRequest),
                'createPr' => Gate::allows('createPr', $plantRequest),
            ],
        ]);
    }

    public function submit(Request $request, PlantRequest $plantRequest): RedirectResponse
    {
        $this->authorize('submit', $plantRequest);

        $allocation = $plantRequest->allocation;
        $tolerance = $this->budgetEngine->validateAgainstTolerance(
            $allocation,
            (string) $plantRequest->estimated_total
        );

        if (! $tolerance['within_tolerance']) {
            $capPct = bcadd('100', (string) $allocation->tolerance_pct, 2);

            return redirect()->route('overbudget.create', [
                'plant_request_id' => $plantRequest->id,
                'budget_allocation_id' => $allocation->id,
                'requested_amount' => $plantRequest->estimated_total,
                'over_pct' => bcsub($tolerance['projected_pct'], $capPct, 2),
            ])->with('budget_exceeded', $this->toleranceExceededMessage($tolerance, $allocation));
        }

        DB::transaction(function () use ($plantRequest, $allocation, $tolerance, $request) {
            $this->budgetEngine->postCommitment(
                $allocation,
                (string) $plantRequest->estimated_total,
                'plant_request',
                $plantRequest->id,
                $request->user(),
                'Plant request submission'
            );

            $plantRequest->update([
                'submitted_at' => now(),
                'budget_utilization_pct' => $tolerance['projected_pct'],
            ]);

            $this->approvalEngine->initiate($plantRequest, ApprovalChains::for('PlantRequest'));
        });

        return redirect()->route('plant-requests.show', $plantRequest)
            ->with('success', 'Plant request submitted for approval.');
    }

    public function receive(ReceivePlantRequestRequest $request, PlantRequest $plantRequest): RedirectResponse
    {
        if ($plantRequest->status === 'received') {
            abort(422, 'This plant request has already been marked as received.');
        }

        if (! Gate::allows('receive', $plantRequest)) {
            abort(403, 'This plant request is not at a stage where it can be marked received, or you do not have permission to do so.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($plantRequest, $validated, $request) {
            $plantRequest->update([
                'status' => 'received',
                'sap_grpo_no' => $validated['sap_grpo_no'],
                'received_at' => $validated['received_at'],
                'received_by' => $request->user()->id,
            ]);

            // Nilai aktual sudah/akan diposting ke ledger oleh job ReconcileGrpoToLedger
            // berdasarkan dokumen GRPO SAP. Jangan posting ulang di sini agar nilainya
            // tidak dihitung dua kali (double counting) pada budget_ledgers.
            $plantRequest->comments()->create([
                'category' => 'general',
                'body' => sprintf(
                    'Goods received — GRPO %s on %s.%s',
                    $validated['sap_grpo_no'],
                    Carbon::parse($validated['received_at'])->format('d M Y'),
                    ! empty($validated['note']) ? ' Note: '.$validated['note'] : ''
                ),
                'author_id' => $request->user()->id,
            ]);
        });

        return redirect()->route('plant-requests.show', $plantRequest)
            ->with('success', 'Goods receipt recorded successfully.');
    }

    public function createPr(Request $request, PlantRequest $plantRequest): RedirectResponse
    {
        if (! Gate::allows('createPr', $plantRequest)) {
            abort(403, 'Only Procurement Admin or IT Manager can trigger PR creation in SAP for an approved plant request.');
        }

        CreateSapPurchaseRequest::dispatch($plantRequest->id);

        return redirect()->route('plant-requests.show', $plantRequest)
            ->with('success', 'PR request sent to SAP.');
    }

    public function estimatePart(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'part_number' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $result = $this->pricingEstimator->estimate($validator->validated()['part_number']);

        return response()->json([
            'ok' => true,
            'unit_price' => $result['unit_price'],
            'source' => $result['source'],
            'reference' => $result['reference'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $equipmentResult
     * @return list<array{id: int, unit_code: string, description: string, plant_type: string, unitstatus: string}>
     */
    private function mapEquipmentList(array $equipmentResult): array
    {
        return collect($equipmentResult['data'] ?? [])
            ->map(fn (array $item) => [
                'id' => $item['id'],
                'unit_code' => $item['unit_code'],
                'description' => $item['description'],
                'plant_type' => $item['plant_type'],
                'unitstatus' => $item['unitstatus'],
            ])
            ->sortBy('unit_code')
            ->values()
            ->all();
    }

    /**
     * @return list<array{project_code: string, project_name: string, is_active: bool}>
     */
    private function projectList(): array
    {
        return ProjectCache::query()
            ->orderBy('project_code')
            ->get(['project_code', 'project_name', 'is_active'])
            ->map(fn (ProjectCache $project) => [
                'project_code' => $project->project_code,
                'project_name' => $project->project_name,
                'is_active' => $project->is_active,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function projectBudgetForCurrentPeriod(string $projectCode): ?array
    {
        try {
            $allocation = $this->resolveProjectAllocation($projectCode);
        } catch (ValidationException) {
            return null;
        }

        $this->budgetEngine->recomputeCachedBalances($allocation);
        $allocation->refresh();

        $base = bcadd((string) $allocation->allocated_amount, (string) $allocation->carry_forward_in, 2);
        $spent = bcadd((string) $allocation->committed_amount, (string) $allocation->actual_amount, 2);

        return [
            'allocation_id' => $allocation->id,
            'allocated_amount' => (string) $allocation->allocated_amount,
            'carry_forward_in' => (string) $allocation->carry_forward_in,
            'pagu' => $base,
            'tolerance_pct' => (string) $allocation->tolerance_pct,
            'committed_amount' => (string) $allocation->committed_amount,
            'actual_amount' => (string) $allocation->actual_amount,
            'tolerance_cap' => $allocation->tolerance_cap,
            'utilization_pct' => $allocation->utilization_pct,
            'remaining' => bcsub($base, $spent, 2),
        ];
    }

    private function resolveProjectAllocation(string $projectCode): BudgetAllocation
    {
        $periods = BudgetPeriod::query()
            ->rollingWindow($projectCode)
            ->with('allocations')
            ->orderBy('period_month')
            ->get();

        $currentMonth = now()->startOfMonth();
        $period = $periods->first(fn (BudgetPeriod $p) => $p->period_month->isSameMonth($currentMonth))
            ?? $periods->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'equipment_id' => ['No budget period exists for this project — the Finance Director must set the project budget first.'],
            ]);
        }

        $allocation = $period->allocations->first();

        if (! $allocation) {
            throw ValidationException::withMessages([
                'equipment_id' => ['No project budget allocation exists for this period — the Finance Director must set the project budget first.'],
            ]);
        }

        if ($allocation->period->project_code !== $projectCode) {
            throw ValidationException::withMessages([
                'equipment_id' => ['The budget allocation does not match the active project.'],
            ]);
        }

        return $allocation;
    }

    /**
     * @param  array<string, mixed>  $tolerance
     */
    private function toleranceExceededMessage(array $tolerance, BudgetAllocation $allocation): string
    {
        $pagu = $this->formatIdr($tolerance['base']);
        $pemakaian = $this->formatIdr($tolerance['projected']);
        $batas = $this->formatIdr($tolerance['cap']);

        return sprintf(
            'Project budget: %s. Usage after this request: %s. Tolerance limit (%s%%): %s.',
            $pagu,
            $pemakaian,
            number_format((float) $allocation->tolerance_pct, 2, ',', '.'),
            $batas
        );
    }

    private function formatIdr(string $amount): string
    {
        return 'Rp '.number_format((float) $amount, 2, ',', '.');
    }

    /**
     * @param  list<array<string, mixed>>  $projects
     * @param  list<array<string, mixed>>  $equipment
     * @param  array<string, mixed>|null  $projectBudget
     * @return array{projectCode: string, projects: list<array<string, mixed>>, equipment: list<array<string, mixed>>, projectBudget: array<string, mixed>|null}
     */
    private function wizardSharedProps(
        string $projectCode,
        array $projects,
        array $equipment,
        ?array $projectBudget,
    ): array {
        return [
            'projectCode' => $projectCode,
            'projects' => $projects,
            'equipment' => $equipment,
            'projectBudget' => $projectBudget,
        ];
    }
}
