<?php

namespace App\Http\Controllers;

use App\Jobs\SyncInterchangeToSap;
use App\Models\InterchangeMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterchangeController extends Controller
{
    public function index(): Response
    {
        $maps = InterchangeMap::query()
            ->with(['creator', 'signoffBy'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Interchange/Index', ['maps' => $maps]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', InterchangeMap::class);

        $validated = $request->validate([
            'genuine_part_number' => 'required|string',
            'oem_part_number' => 'required|string',
            'material_name' => 'required|string',
        ]);

        InterchangeMap::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Interchange mapping created.');
    }

    public function signoff(Request $request, InterchangeMap $interchangeMap): RedirectResponse
    {
        $this->authorize('signoff', $interchangeMap);

        $interchangeMap->update([
            'technical_signoff_by' => $request->user()->id,
            'technical_signoff_at' => now(),
        ]);

        SyncInterchangeToSap::dispatch($interchangeMap->id);

        return back()->with('success', 'Technical sign-off recorded. SAP sync queued.');
    }
}
