<?php

namespace App\Http\Controllers;

use App\Jobs\SyncDmbdStatusToArkfleet;
use App\Models\DmbdEntry;
use App\Services\Arkfleet\EquipmentCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DmbdController extends Controller
{
    public function __construct(
        private readonly EquipmentCache $equipmentCache,
    ) {}

    public function index(Request $request): Response
    {
        $projectCode = session('current_project') ?? $request->user()->project_code_scope;
        $equipment = $this->equipmentCache->list($projectCode);
        $today = now()->toDateString();

        $entries = DmbdEntry::query()
            ->where('report_date', $today)
            ->get()
            ->keyBy('equipment_id');

        return Inertia::render('Dmbd/Index', [
            'equipment' => $equipment,
            'entries' => $entries,
            'reportDate' => $today,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DmbdEntry::class);

        $validated = $request->validate([
            'equipment_id' => 'required|integer',
            'unit_code_cache' => 'required|string',
            'operational_status' => 'required|in:rfu,standby,breakdown',
            'breakdown_note' => 'nullable|string',
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
