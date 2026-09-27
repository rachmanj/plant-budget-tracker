<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSapSuppliers;
use App\Models\SapSupplier;
use App\Services\Procurement\SapSupplierSync;
use App\Services\Sap\SapReadRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SapSupplierController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('supplier.view'), 403);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $search = trim((string) $request->input('search', ''));
        $query = SapSupplier::query()->orderBy('card_code');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('card_code', 'like', '%'.$search.'%')
                    ->orWhere('card_name', 'like', '%'.$search.'%');
            });
        }

        $suppliers = $query->paginate($perPage)->withQueryString();

        $suppliers->getCollection()->transform(fn (SapSupplier $supplier) => [
            'id' => $supplier->id,
            'card_code' => $supplier->card_code,
            'card_name' => $supplier->card_name,
            'payment_terms' => $supplier->payment_terms,
            'currency' => $supplier->currency,
            'is_active' => $supplier->is_active,
            'synced_at' => $supplier->synced_at?->toIso8601String(),
        ]);

        return Inertia::render('Procurement/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search !== '' ? $search : null,
            ],
            'perPage' => $perPage,
            'can' => [
                'sync' => $request->user()->can('procurement.sync'),
            ],
            'syncSummary' => session('syncSummary'),
        ]);
    }

    public function sync(Request $request, SapReadRepository $repository, SapSupplierSync $sync): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.sync'), 403);

        $summary = app(SyncSapSuppliers::class)->handle($repository, $sync);
        SyncSapSuppliers::dispatch();

        return redirect()
            ->route('procurement.suppliers.index')
            ->with('syncSummary', $summary);
    }
}
