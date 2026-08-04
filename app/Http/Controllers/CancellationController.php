<?php

namespace App\Http\Controllers;

use App\Models\CancellationRequest;
use App\Models\PlantRequest;
use App\Services\Cancellation\CancelRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CancellationController extends Controller
{
    public function __construct(
        private readonly CancelRequestService $cancelService,
    ) {}

    public function index(): Response
    {
        $requests = CancellationRequest::query()
            ->with('plantRequest')
            ->latest()
            ->paginate(20);

        return Inertia::render('Cancellation/Index', ['requests' => $requests]);
    }

    public function store(Request $request, PlantRequest $plantRequest): RedirectResponse
    {
        $this->authorize('cancel', $plantRequest);

        $validated = $request->validate([
            'po_stage' => 'nullable|in:created,approved,sent',
            'reason' => 'required|string',
        ]);

        $initiatedBy = $request->user()->can('cancellation.procurement') ? 'procurement' : 'plant';

        if ($initiatedBy === 'plant' && ($validated['po_stage'] ?? null) === 'sent') {
            abort(422, 'Cannot cancel: PO has been sent.');
        }

        $this->cancelService->createCancellation(
            $plantRequest,
            $request->user(),
            $initiatedBy,
            $validated['po_stage'] ?? null,
            $validated['reason']
        );

        return redirect()->route('cancellation.index')
            ->with('success', 'Cancellation request created.');
    }

    public function agree(Request $request, CancellationRequest $cancellationRequest): RedirectResponse
    {
        $this->authorize('agree', $cancellationRequest);

        $this->cancelService->agree($cancellationRequest, $request->user());

        return back()->with('success', 'Cancellation agreed.');
    }
}
