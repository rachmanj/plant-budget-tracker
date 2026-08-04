<?php

namespace App\Http\Controllers;

use App\Models\CannibalRequest;
use App\Models\DmbdEntry;
use App\Support\ApprovalChains;
use App\Services\Approval\ApprovalEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CannibalController extends Controller
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    public function index(): Response
    {
        $requests = CannibalRequest::query()
            ->with(['dmbdEntry', 'requester', 'approvals'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Cannibal/Index', ['requests' => $requests]);
    }

    public function create(): Response
    {
        return Inertia::render('Cannibal/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CannibalRequest::class);

        $validated = $request->validate([
            'source_equipment_id' => 'required|integer',
            'target_equipment_id' => 'required|integer|different:source_equipment_id',
            'dmbd_entry_id' => 'required|exists:dmbd_entries,id',
            'reason' => 'required|string',
            'component_ids' => 'nullable|array',
            'component_ids.*' => 'exists:components,id',
        ]);

        $dmbd = DmbdEntry::findOrFail($validated['dmbd_entry_id']);

        if ($dmbd->operational_status !== 'breakdown') {
            return back()->withErrors(['dmbd_entry_id' => 'DMBD entry must be breakdown status.']);
        }

        if ($dmbd->equipment_id != $validated['source_equipment_id']) {
            return back()->withErrors(['dmbd_entry_id' => 'DMBD entry must match source equipment.']);
        }

        $cannibal = DB::transaction(function () use ($validated, $request) {
            $cannibal = CannibalRequest::create([
                'source_equipment_id' => $validated['source_equipment_id'],
                'target_equipment_id' => $validated['target_equipment_id'],
                'dmbd_entry_id' => $validated['dmbd_entry_id'],
                'reason' => $validated['reason'],
                'requested_by' => $request->user()->id,
            ]);

            if (! empty($validated['component_ids'])) {
                $cannibal->components()->attach($validated['component_ids']);
            }

            $this->approvalEngine->initiate($cannibal, ApprovalChains::for('CannibalRequest'));

            return $cannibal;
        });

        return redirect()->route('cannibal-requests.index')
            ->with('success', 'Cannibal request submitted.');
    }
}
