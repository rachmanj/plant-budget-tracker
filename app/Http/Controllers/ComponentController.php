<?php

namespace App\Http\Controllers;

use App\Models\Component;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComponentController extends Controller
{
    public function index(): Response
    {
        $components = Component::query()
            ->with('children')
            ->whereNull('parent_id')
            ->get();

        return Inertia::render('Component/Index', ['components' => $components]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Component::class);

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:components,id',
            'level' => 'required|in:housing,inner,critical',
            'equipment_id' => 'required|integer',
            'component_code' => 'required|string',
            'description' => 'required|string',
        ]);

        if ($validated['level'] === 'critical' && $request->filled('parent_id')) {
            $parent = Component::find($validated['parent_id']);
            if ($parent && $parent->level === 'critical') {
                return back()->withErrors(['level' => 'Critical components cannot have children.']);
            }
        }

        Component::create([
            ...$validated,
            'maintained_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Component registered.');
    }

    public function updateStatus(Request $request, Component $component): RedirectResponse
    {
        $this->authorize('update', $component);

        $validated = $request->validate([
            'status' => 'required|in:installed,removed,cannibalized,scrapped',
        ]);

        $component->update($validated);

        return back()->with('success', 'Component status updated.');
    }
}
