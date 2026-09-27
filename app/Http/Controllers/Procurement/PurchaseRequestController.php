<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\PlantRequest;
use App\Models\ProjectCache;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseRequest;
use App\Models\User;
use App\Support\ProjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseRequestController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.view'), 403);

        $user = $request->user();
        $forcedProjectCode = null;
        $projectScope = null;
        if (! ProjectContext::allowSwitch($user)) {
            $projectScope = is_string($user->project_code_scope) && $user->project_code_scope !== ''
                ? $user->project_code_scope
                : null;
            $forcedProjectCode = $projectScope;
        }

        $filtered = $this->filteredQuery($request, $forcedProjectCode);

        $summaryRow = (clone $filtered)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $purchaseRequests = (clone $filtered)
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Procurement/PurchaseRequests/Index', [
            'purchaseRequests' => $purchaseRequests,
            'filters' => [
                'q' => $request->input('q'),
                'project_code' => $forcedProjectCode ?? $request->input('project_code'),
                'dept_code' => $request->input('dept_code'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'pr_status' => $request->input('pr_status'),
                'per_page' => $perPage,
            ],
            'summary' => [
                'document_count' => (int) ($summaryRow->document_count ?? 0),
                'total_amount' => (string) ($summaryRow->total_amount ?? '0.00'),
            ],
            'projects' => $this->projectOptions($user),
            'projectScope' => $projectScope,
            'departments' => $this->departmentOptions(),
            'prStatusOptions' => $this->prStatusOptions($forcedProjectCode, $request),
        ]);
    }

    public function show(Request $request, SapPurchaseRequest $sapPurchaseRequest): Response
    {
        abort_unless($request->user()?->can('procurement.view'), 403);

        $user = $request->user();
        $this->assertUserCanAccessPurchaseRequest($user, $sapPurchaseRequest);

        $sapPurchaseRequest->load([
            'lines' => fn ($query) => $query->orderBy('line_num')->orderBy('vis_order'),
        ]);

        $prNumber = $sapPurchaseRequest->doc_num !== null ? (string) $sapPurchaseRequest->doc_num : null;

        $relatedPurchaseOrders = $prNumber === null
            ? collect()
            : SapPurchaseOrder::query()
                ->where('pr_no', $prNumber)
                ->orderByDesc('doc_date')
                ->orderByDesc('id')
                ->get(['id', 'doc_num', 'doc_date', 'vendor_name', 'total_amount', 'delivery_status']);

        $relatedPlantRequests = $prNumber === null
            ? collect()
            : PlantRequest::query()
                ->where('sap_pr_no', $prNumber)
                ->orderByDesc('id')
                ->get(['id', 'request_no', 'status']);

        return Inertia::render('Procurement/PurchaseRequests/Show', [
            'purchaseRequest' => $sapPurchaseRequest,
            'relatedPurchaseOrders' => $relatedPurchaseOrders,
            'relatedPlantRequests' => $relatedPlantRequests,
        ]);
    }

    public function daily(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.view'), 403);

        $user = $request->user();
        $forcedProjectCode = null;
        $projectScope = null;
        if (! ProjectContext::allowSwitch($user)) {
            $projectScope = is_string($user->project_code_scope) && $user->project_code_scope !== ''
                ? $user->project_code_scope
                : null;
            $forcedProjectCode = $projectScope;
        }

        $date = $request->input('date', now()->toDateString());
        $parsed = Carbon::parse($date)->startOfDay();
        $dayStart = $parsed->copy();
        $dayEnd = $parsed->copy()->endOfDay();

        $query = SapPurchaseRequest::query()
            ->whereBetween('create_date', [$dayStart, $dayEnd]);

        if (is_string($forcedProjectCode) && $forcedProjectCode !== '') {
            $query->where('project_code', $forcedProjectCode);
        }

        $summaryRow = (clone $query)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $rows = (clone $query)
            ->orderByDesc('create_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Procurement/DailyPr/Index', [
            'date' => $parsed->toDateString(),
            'purchaseRequests' => $rows,
            'summary' => [
                'document_count' => (int) ($summaryRow->document_count ?? 0),
                'total_amount' => (string) ($summaryRow->total_amount ?? '0.00'),
            ],
            'projectScope' => $projectScope,
            'canExport' => (bool) $request->user()?->can('reports.export'),
        ]);
    }

    public function exportDailyCsv(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('procurement.view'), 403);
        abort_unless($request->user()?->can('reports.export'), 403);

        $user = $request->user();
        $forcedProjectCode = null;
        if (! ProjectContext::allowSwitch($user)) {
            $forcedProjectCode = is_string($user->project_code_scope) && $user->project_code_scope !== ''
                ? $user->project_code_scope
                : null;
        }

        $date = $request->input('date', now()->toDateString());
        $parsed = Carbon::parse($date)->startOfDay();
        $dayStart = $parsed->copy();
        $dayEnd = $parsed->copy()->endOfDay();

        $query = SapPurchaseRequest::query()
            ->whereBetween('create_date', [$dayStart, $dayEnd]);

        if (is_string($forcedProjectCode) && $forcedProjectCode !== '') {
            $query->where('project_code', $forcedProjectCode);
        }

        $rows = $query
            ->orderByDesc('create_date')
            ->orderByDesc('id')
            ->get();

        $filename = 'daily-pr-'.$parsed->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Doc Num',
                'Doc Date',
                'Department',
                'Project',
                'Requester',
                'Lines',
                'Total',
                'Status',
            ]);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->doc_num,
                    $row->doc_date?->format('Y-m-d'),
                    $row->department_name,
                    $row->project_code,
                    $row->requester,
                    $row->line_count,
                    $row->total_amount,
                    $row->pr_status,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function assertUserCanAccessPurchaseRequest(?User $user, SapPurchaseRequest $sapPurchaseRequest): void
    {
        if ($user !== null && ProjectContext::allowSwitch($user)) {
            return;
        }

        abort_unless(
            $user !== null
                && is_string($user->project_code_scope)
                && $user->project_code_scope !== ''
                && $sapPurchaseRequest->project_code === $user->project_code_scope,
            403
        );
    }

    private function filteredQuery(Request $request, ?string $forcedProjectCode = null): Builder
    {
        $query = SapPurchaseRequest::query();

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $outer) use ($like, $search) {
                $outer->where('mr_no', 'like', $like)
                    ->orWhere('requester', 'like', $like)
                    ->orWhereHas('lines', fn (Builder $lines) => $lines->where('item_code', 'like', $like));

                if (ctype_digit($search)) {
                    $outer->orWhere('doc_num', (int) $search);
                } else {
                    $outer->orWhereRaw('CAST(doc_num AS CHAR) LIKE ?', [$like]);
                }
            });
        }

        if (is_string($forcedProjectCode) && $forcedProjectCode !== '') {
            $query->where('project_code', $forcedProjectCode);
        } else {
            $projectCode = $request->input('project_code');
            if (is_string($projectCode) && $projectCode !== '') {
                $query->where('project_code', $projectCode);
            }
        }

        $deptCode = $request->input('dept_code');
        if (is_string($deptCode) && $deptCode !== '') {
            $query->where('department_code', $deptCode);
        }

        $from = $request->input('from');
        $to = $request->input('to');
        if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
            $query->docDateBetween($from, $to);
        } elseif (is_string($from) && $from !== '') {
            $query->whereDate('doc_date', '>=', $from);
        } elseif (is_string($to) && $to !== '') {
            $query->whereDate('doc_date', '<=', $to);
        }

        $prStatus = $request->input('pr_status');
        if (is_string($prStatus) && $prStatus !== '') {
            $query->where('pr_status', $prStatus);
        }

        return $query;
    }

    /**
     * @return array<int, array{project_code: string, project_name: string}>
     */
    private function projectOptions(?User $user): array
    {
        if ($user !== null && ! ProjectContext::allowSwitch($user)) {
            $scope = $user->project_code_scope;
            if (! is_string($scope) || $scope === '') {
                return [];
            }

            $name = ProjectCache::query()
                ->where('project_code', $scope)
                ->value('project_name');

            return [[
                'project_code' => $scope,
                'project_name' => (string) ($name ?? $scope),
            ]];
        }

        $codes = SapPurchaseRequest::query()
            ->whereNotNull('project_code')
            ->distinct()
            ->orderBy('project_code')
            ->pluck('project_code');

        if ($codes->isEmpty()) {
            return [];
        }

        $names = ProjectCache::query()
            ->whereIn('project_code', $codes)
            ->pluck('project_name', 'project_code');

        return $codes
            ->map(fn (string $code) => [
                'project_code' => $code,
                'project_name' => (string) ($names[$code] ?? $code),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{dept_code: string, dept_name: string}>
     */
    private function departmentOptions(): array
    {
        return SapPurchaseRequest::query()
            ->whereNotNull('department_code')
            ->select('department_code', 'department_name')
            ->distinct()
            ->orderBy('department_name')
            ->get()
            ->map(fn (SapPurchaseRequest $row) => [
                'dept_code' => (string) $row->department_code,
                'dept_name' => (string) ($row->department_name ?? $row->department_code),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function prStatusOptions(?string $forcedProjectCode, Request $request): array
    {
        $query = SapPurchaseRequest::query()->whereNotNull('pr_status');

        if (is_string($forcedProjectCode) && $forcedProjectCode !== '') {
            $query->where('project_code', $forcedProjectCode);
        } else {
            $projectCode = $request->input('project_code');
            if (is_string($projectCode) && $projectCode !== '') {
                $query->where('project_code', $projectCode);
            }
        }

        return $query
            ->distinct()
            ->orderBy('pr_status')
            ->pluck('pr_status')
            ->filter()
            ->map(fn (string $status) => ['value' => $status, 'label' => $status])
            ->values()
            ->all();
    }
}
