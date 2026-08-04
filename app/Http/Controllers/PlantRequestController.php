<?php

namespace App\Http\Controllers;

use App\Models\PlantRequest;
use App\Services\Budget\BudgetEngine;
use App\Services\Pricing\PricingEstimator;
use App\Support\ApprovalChains;
use App\Services\Approval\ApprovalEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlantRequestController extends Controller
{
    public function __construct(
        private readonly BudgetEngine $budgetEngine,
        private readonly ApprovalEngine $approvalEngine,
        private readonly PricingEstimator $pricingEstimator,
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
        return Inertia::render('PlantRequest/Create', [
            'prefill' => $request->only(['dmbd_entry_id', 'equipment_id', 'unit_code_cache']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'budget_allocation_id' => 'required|exists:budget_allocations,id',
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

        $plantRequest = DB::transaction(function () use ($validated, $request) {
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
                'budget_allocation_id' => $validated['budget_allocation_id'],
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
        $plantRequest->load(['lines', 'allocation.period', 'approvals.approver', 'comments.author', 'requester']);

        $tolerance = $this->budgetEngine->validateAgainstTolerance(
            $plantRequest->allocation,
            (string) $plantRequest->estimated_total
        );

        return Inertia::render('PlantRequest/Show', [
            'request' => $plantRequest,
            'tolerance' => $tolerance,
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
            return redirect()->route('overbudget.create', [
                'plant_request_id' => $plantRequest->id,
                'budget_allocation_id' => $allocation->id,
                'requested_amount' => $plantRequest->estimated_total,
                'over_pct' => bcsub($tolerance['projected_pct'], '110.00', 2),
            ]);
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

    public function estimatePrice(Request $request): array
    {
        $request->validate(['part_number' => 'required|string']);

        return $this->pricingEstimator->estimate($request->part_number);
    }
}
