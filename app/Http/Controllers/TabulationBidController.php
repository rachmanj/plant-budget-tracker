<?php

namespace App\Http\Controllers;

use App\Jobs\CreateSapPurchaseOrder;
use App\Models\TabulationBid;
use App\Models\TabulationBidAward;
use App\Support\ApprovalChains;
use App\Services\Approval\ApprovalEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TabulationBidController extends Controller
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    public function index(): Response
    {
        $bids = TabulationBid::query()
            ->with(['buyer', 'vendors', 'award'])
            ->latest()
            ->paginate(20);

        return Inertia::render('TabulationBid/Index', ['bids' => $bids]);
    }

    public function create(): Response
    {
        return Inertia::render('TabulationBid/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TabulationBid::class);

        $validated = $request->validate([
            'sap_pr_id' => 'required|string',
            'vendors' => 'required|array|min:2|max:3',
            'vendors.*.vendor_code' => 'required|string',
            'vendors.*.vendor_name' => 'required|string',
            'vendors.*.price' => 'required|numeric|min:0',
            'vendors.*.payment_terms' => 'nullable|string',
            'vendors.*.stock_availability' => 'required|in:ready,indent,partial',
            'vendors.*.remarks' => 'nullable|string',
        ]);

        $bid = DB::transaction(function () use ($validated, $request) {
            $bid = TabulationBid::create([
                'sap_pr_id' => $validated['sap_pr_id'],
                'created_by' => $request->user()->id,
                'status' => 'pending_proc_mgr',
            ]);

            $sorted = collect($validated['vendors'])->sortBy('price')->values();
            foreach ($sorted as $index => $vendor) {
                $bid->vendors()->create(array_merge($vendor, ['rank' => $index + 1]));
            }

            $this->approvalEngine->initiate($bid, ApprovalChains::for('TabulationBid'));

            return $bid;
        });

        return redirect()->route('tabulation-bids.show', $bid)
            ->with('success', 'Tabulation bid created.');
    }

    public function show(TabulationBid $tabulationBid): Response
    {
        $tabulationBid->load(['vendors', 'award.vendor', 'buyer', 'approvals']);

        return Inertia::render('TabulationBid/Review', ['bid' => $tabulationBid]);
    }

    public function review(TabulationBid $tabulationBid): Response
    {
        $this->authorize('review', $tabulationBid);

        return $this->show($tabulationBid);
    }

    public function award(Request $request, TabulationBid $tabulationBid): RedirectResponse
    {
        $this->authorize('award', $tabulationBid);

        $validated = $request->validate([
            'tabulation_bid_vendor_id' => 'required|exists:tabulation_bid_vendors,id',
            'justification' => 'nullable|string',
        ]);

        $vendor = $tabulationBid->vendors()->findOrFail($validated['tabulation_bid_vendor_id']);
        $lowest = $tabulationBid->vendors()->orderBy('price')->first();

        if ($vendor->id !== $lowest->id && empty($validated['justification'])) {
            return back()->withErrors(['justification' => 'Justification required when not awarding lowest price.']);
        }

        DB::transaction(function () use ($tabulationBid, $validated, $vendor, $request) {
            TabulationBidAward::create([
                'tabulation_bid_id' => $tabulationBid->id,
                'tabulation_bid_vendor_id' => $vendor->id,
                'justification' => $validated['justification'] ?? null,
                'awarded_by' => $request->user()->id,
                'awarded_at' => now(),
            ]);

            $tabulationBid->update([
                'status' => 'forwarded_admin',
                'reviewed_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Vendor awarded.');
    }

    public function createPo(TabulationBid $tabulationBid): RedirectResponse
    {
        $this->authorize('createPo', $tabulationBid);

        if ($tabulationBid->sap_po_id) {
            return back()->with('error', 'PO already created.');
        }

        CreateSapPurchaseOrder::dispatch($tabulationBid->id);

        return back()->with('success', 'PO creation queued.');
    }
}
