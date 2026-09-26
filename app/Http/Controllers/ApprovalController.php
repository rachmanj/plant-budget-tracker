<?php

namespace App\Http\Controllers;

use App\Models\PlantRequest;
use App\Models\RequestApproval;
use App\Models\TabulationBid;
use App\Services\Approval\ApprovalEngine;
use App\Support\ProcurementSettings;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RequestApproval::class);

        $user = $request->user();
        $roleNames = $user->getRoleNames();

        $approvals = RequestApproval::query()
            ->with([
                'approvable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        TabulationBid::class => ['award.vendor'],
                        PlantRequest::class => ['allocation.period'],
                    ]);
                },
            ])
            ->where('decision', 'pending')
            ->whereIn('required_role', $roleNames)
            ->latest()
            ->paginate(20);

        $approvals->through(fn (RequestApproval $approval) => array_merge(
            $approval->toArray(),
            ['document' => $this->documentMetaFor($approval)],
        ));

        return Inertia::render('Approvals/Index', [
            'approvals' => $approvals,
        ]);
    }

    public function decide(Request $request, RequestApproval $approval): RedirectResponse
    {
        $this->authorize('decide', $approval);

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected,returned',
            'remarks' => 'nullable|string|required_if:decision,rejected,returned',
        ], [
            'remarks.required_if' => 'Remarks are required when rejecting or returning.',
        ]);

        $this->approvalEngine->decide(
            $approval,
            $request->user(),
            $validated['decision'],
            $validated['remarks'] ?? null
        );

        return back()->with('success', 'Decision recorded.');
    }

    /**
     * @return array{
     *     type_label: string,
     *     document_no: string,
     *     project_code: string|null,
     *     project_name: string|null,
     *     amount: string|null,
     *     submitted_at: string|null,
     *     url: string,
     *     step_order: int,
     *     total_steps: int,
     *     step_label: string,
     *     is_director_threshold_step: bool,
     *     director_threshold: string|null
     * }
     */
    private function documentMetaFor(RequestApproval $approval): array
    {
        $approvable = $approval->approvable;
        $stepOrder = (int) $approval->step_order;
        $totalSteps = $approvable?->approvals()->count() ?? $stepOrder;

        $defaults = [
            'type_label' => 'Document',
            'document_no' => (string) ($approval->approvable_id ?? ''),
            'project_code' => null,
            'project_name' => null,
            'amount' => null,
            'submitted_at' => $approval->created_at?->toIso8601String(),
            'url' => '#',
            'step_order' => $stepOrder,
            'total_steps' => max($totalSteps, $stepOrder),
            'step_label' => 'Step '.$stepOrder.' of '.max($totalSteps, $stepOrder),
            'is_director_threshold_step' => false,
            'director_threshold' => null,
        ];

        if ($approvable instanceof TabulationBid) {
            $winnerPrice = $approvable->award?->vendor?->price;
            $threshold = ProcurementSettings::poDirectorThreshold();
            $isDirectorThresholdStep = $approval->required_role === 'president_director'
                && $stepOrder === 2;
            $directorThreshold = number_format($threshold, 2, '.', '');
            $total = max($totalSteps, $stepOrder);

            return array_merge($defaults, [
                'type_label' => 'Tabulation Bid (PO)',
                'document_no' => $approvable->bid_no,
                'amount' => $winnerPrice !== null ? (string) $winnerPrice : null,
                'submitted_at' => $approvable->created_at?->toIso8601String(),
                'url' => route('tabulation-bids.show', $approvable),
                'step_label' => 'Step '.$stepOrder.' of '.$total,
                'total_steps' => $total,
                'is_director_threshold_step' => $isDirectorThresholdStep,
                'director_threshold' => $isDirectorThresholdStep ? $directorThreshold : null,
            ]);
        }

        if ($approvable instanceof PlantRequest) {
            $period = $approvable->allocation?->period;
            $total = max($totalSteps, $stepOrder);

            return array_merge($defaults, [
                'type_label' => 'Plant Request',
                'document_no' => $approvable->request_no,
                'project_code' => $period?->project_code,
                'project_name' => $period?->project_name_cache ?? $period?->project_code,
                'amount' => $approvable->estimated_total !== null
                    ? (string) $approvable->estimated_total
                    : null,
                'submitted_at' => $approvable->submitted_at?->toIso8601String()
                    ?? $approvable->created_at?->toIso8601String(),
                'url' => route('plant-requests.show', $approvable),
                'step_label' => 'Step '.$stepOrder.' of '.$total,
                'total_steps' => $total,
            ]);
        }

        return $defaults;
    }
}
