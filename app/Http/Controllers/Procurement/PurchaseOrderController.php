<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ProjectCache;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    /** @var array<string, string> */
    private const DEPARTMENT_OPTIONS = [
        '40' => 'Plant',
        '200' => 'Logistic and Warehouse',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.view'), 403);

        $filtered = $this->filteredQuery($request);

        $summaryRow = (clone $filtered)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $purchaseOrders = (clone $filtered)
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => [
                'q' => $request->input('q'),
                'project_code' => $request->input('project_code'),
                'dept_code' => $request->input('dept_code'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'delivery_status' => $request->input('delivery_status'),
                'origin' => $request->input('origin'),
                'per_page' => $perPage,
            ],
            'summary' => [
                'document_count' => (int) ($summaryRow->document_count ?? 0),
                'total_amount' => (string) ($summaryRow->total_amount ?? '0.00'),
            ],
            'projects' => $this->projectOptions(),
            'departments' => collect(self::DEPARTMENT_OPTIONS)
                ->map(fn (string $name, string $code) => ['dept_code' => $code, 'dept_name' => $name])
                ->values()
                ->all(),
        ]);
    }

    public function show(Request $request, SapPurchaseOrder $sapPurchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.view'), 403);

        $sapPurchaseOrder->load([
            'lines' => fn ($query) => $query->orderBy('line_num')->orderBy('vis_order'),
            'plantRequest',
        ]);

        $relatedPurchaseRequest = null;
        if ($sapPurchaseOrder->pr_no !== null && $sapPurchaseOrder->pr_no !== '') {
            $relatedPurchaseRequest = SapPurchaseRequest::query()
                ->where('doc_num', $sapPurchaseOrder->pr_no)
                ->first();
        }

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'purchaseOrder' => $sapPurchaseOrder,
            'relatedPurchaseRequest' => $relatedPurchaseRequest,
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = SapPurchaseOrder::query();

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $outer) use ($like, $search) {
                $outer->where('pr_no', 'like', $like)
                    ->orWhere('vendor_name', 'like', $like)
                    ->orWhereHas('lines', fn (Builder $lines) => $lines->where('item_code', 'like', $like));

                if (ctype_digit($search)) {
                    $outer->orWhere('doc_num', (int) $search);
                } else {
                    $outer->orWhereRaw('CAST(doc_num AS CHAR) LIKE ?', [$like]);
                }
            });
        }

        $projectCode = $request->input('project_code');
        if (is_string($projectCode) && $projectCode !== '') {
            $query->where('project_code', $projectCode);
        }

        $deptCode = $request->input('dept_code');
        if (is_string($deptCode) && $deptCode !== '') {
            $query->where('dept_code', $deptCode);
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

        $deliveryStatus = $request->input('delivery_status');
        if (is_string($deliveryStatus) && $deliveryStatus !== '') {
            $query->where('delivery_status', $deliveryStatus);
        }

        $origin = $request->input('origin');
        if (in_array($origin, ['sap', 'pmb'], true)) {
            $query->where('origin', $origin);
        }

        return $query;
    }

    /**
     * @return array<int, array{project_code: string, project_name: string}>
     */
    private function projectOptions(): array
    {
        $codes = SapPurchaseOrder::query()
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
}
