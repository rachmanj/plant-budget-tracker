<?php

namespace App\Http\Controllers;

use App\Models\RequestApproval;
use App\Services\Approval\ApprovalEngine;
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
        $user = $request->user();
        $roleNames = $user->getRoleNames();

        $approvals = RequestApproval::query()
            ->with('approvable')
            ->where('decision', 'pending')
            ->whereIn('required_role', $roleNames)
            ->latest()
            ->paginate(20);

        return Inertia::render('Approvals/Index', [
            'approvals' => $approvals,
        ]);
    }

    public function decide(Request $request, RequestApproval $approval): RedirectResponse
    {
        $this->authorize('decide', $approval);

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected,returned',
            'remarks' => 'nullable|string',
        ]);

        $this->approvalEngine->decide(
            $approval,
            $request->user(),
            $validated['decision'],
            $validated['remarks'] ?? null
        );

        return back()->with('success', 'Decision recorded.');
    }
}
