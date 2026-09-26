<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ProcurementSetting;
use App\Support\ProcurementSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementSettingsController extends Controller
{
    private const PO_DIRECTOR_THRESHOLD_KEY = 'po_director_threshold_idr';

    public function index(Request $request): Response
    {
        $this->authorize('procurement.settings');

        $setting = ProcurementSetting::query()
            ->with('updatedBy:id,name')
            ->where('key', self::PO_DIRECTOR_THRESHOLD_KEY)
            ->first();

        return Inertia::render('Procurement/Settings', [
            'threshold' => number_format(ProcurementSettings::poDirectorThreshold(), 2, '.', ''),
            'lastUpdated' => $setting ? [
                'name' => $setting->updatedBy?->name,
                'at' => $setting->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('procurement.settings');

        $validated = $request->validate([
            'po_director_threshold_idr' => 'required|numeric|min:0',
        ], [
            'po_director_threshold_idr.min' => 'Threshold must be zero or greater.',
        ]);

        ProcurementSettings::setPoDirectorThreshold(
            (string) $validated['po_director_threshold_idr'],
            $request->user()
        );

        return back()->with('success', 'Procurement settings saved.');
    }
}
