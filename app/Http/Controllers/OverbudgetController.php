<?php

namespace App\Http\Controllers;

use App\Models\OverbudgetRequest;
use App\Models\PlantRequest;
use App\Services\Approval\ApprovalEngine;
use App\Services\Budget\BudgetEngine;
use App\Support\ApprovalChains;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OverbudgetController extends Controller
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
        private readonly BudgetEngine $budgetEngine,
    ) {}

    public function index(): Response
    {
        $requests = OverbudgetRequest::query()
            ->with(['allocation.period', 'plantRequest'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Overbudget/Index', ['requests' => $requests]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Overbudget/Index', [
            'prefill' => $request->only([
                'plant_request_id', 'budget_allocation_id', 'requested_amount', 'over_pct',
            ]),
            'showForm' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'budget_allocation_id' => 'required|exists:budget_allocations,id',
            'plant_request_id' => 'nullable|exists:plant_requests,id',
            'requested_amount' => 'required|numeric|min:0',
            'over_pct' => 'required|numeric',
            'justification' => 'required|string',
        ]);

        $overbudget = DB::transaction(function () use ($validated, $request) {
            $overbudget = OverbudgetRequest::create([
                ...$validated,
                'requested_by' => $request->user()->id,
                'status' => 'pending_fin_dir',
            ]);

            $this->approvalEngine->initiate($overbudget, ApprovalChains::for('OverbudgetRequest'));

            return $overbudget;
        });

        return redirect()->route('overbudget.index')
            ->with('success', 'Overbudget request submitted.');
    }

    public function onApproved(OverbudgetRequest $overbudget): void
    {
        $this->budgetEngine->postOverbudget(
            $overbudget->allocation,
            (string) $overbudget->requested_amount,
            $overbudget->id,
            $overbudget->requester ?? auth()->user(),
            'Overbudget approved'
        );

        if ($overbudget->plant_request_id) {
            $plantRequest = PlantRequest::find($overbudget->plant_request_id);
            if ($plantRequest && $plantRequest->status === 'draft') {
                $plantRequest->update(['status' => 'pending_pm']);
            }
        }
    }
}
