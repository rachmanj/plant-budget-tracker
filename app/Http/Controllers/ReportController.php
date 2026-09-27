<?php

namespace App\Http\Controllers;

use App\Models\ProjectCache;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseRequest;
use App\Models\User;
use App\Services\Reporting\ApprovalTurnaroundReport;
use App\Services\Reporting\BudgetConsumptionReport;
use App\Services\Reporting\EquipmentCostReport;
use App\Services\Reporting\PurchaseOrderTrendReport;
use App\Services\Reporting\PurchaseRequestByDepartmentReport;
use App\Services\Reporting\PurchaseRequestStatusReport;
use App\Services\Reporting\TopSupplierReport;
use App\Services\Reporting\VendorPerformanceReport;
use App\Support\ProjectContext;
use App\Support\RoleLabels;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly BudgetConsumptionReport $budgetReport,
        private readonly VendorPerformanceReport $vendorReport,
        private readonly EquipmentCostReport $equipmentReport,
        private readonly PurchaseRequestStatusReport $purchaseRequestStatusReport,
        private readonly PurchaseOrderTrendReport $purchaseOrderTrendReport,
        private readonly TopSupplierReport $topSupplierReport,
        private readonly ApprovalTurnaroundReport $approvalTurnaroundReport,
        private readonly PurchaseRequestByDepartmentReport $purchaseRequestByDepartmentReport,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Index', [
            'reports' => [
                [
                    'key' => 'budget-consumption',
                    'title' => 'Budget Consumption',
                    'description' => 'Project budget ceiling, commitment, actuals, and request breakdown by unit.',
                    'href' => route('reports.budget-consumption'),
                ],
                [
                    'key' => 'vendor-performance',
                    'title' => 'Vendor Performance',
                    'description' => 'Indent frequency and supplier performance.',
                    'href' => route('reports.vendor-performance'),
                ],
                [
                    'key' => 'equipment-cost',
                    'title' => 'Equipment Cost',
                    'description' => 'Fleet equipment cost summary.',
                    'href' => route('reports.equipment-cost'),
                ],
                [
                    'key' => 'purchase-request-status',
                    'title' => 'Purchase Request Status',
                    'description' => 'PR volume and value grouped by status and department.',
                    'href' => route('reports.purchase-request-status'),
                ],
                [
                    'key' => 'purchase-order-trend',
                    'title' => 'Purchase Order Trend',
                    'description' => 'PO counts and values over time, split by PMB vs SAP origin.',
                    'href' => route('reports.purchase-order-trend'),
                ],
                [
                    'key' => 'top-supplier',
                    'title' => 'Top Suppliers',
                    'description' => 'Suppliers ranked by PO value with share of total spend.',
                    'href' => route('reports.top-supplier'),
                ],
                [
                    'key' => 'approval-turnaround',
                    'title' => 'Approval Turnaround',
                    'description' => 'Days from PR to linked PO, by department.',
                    'href' => route('reports.approval-turnaround'),
                ],
                [
                    'key' => 'purchase-request-by-department',
                    'title' => 'PR by Department',
                    'description' => 'PR volume and value by department and project.',
                    'href' => route('reports.purchase-request-by-department'),
                ],
            ],
            'can' => [
                'export' => $request->user()->can('reports.export'),
            ],
        ]);
    }

    public function budgetConsumption(Request $request): Response
    {
        $this->authorize('viewBudgetConsumption');

        $projectCode = $this->resolveProjectCode($request);
        $month = Carbon::parse($request->get('month', now()->format('Y-m')));

        return Inertia::render('Reports/BudgetConsumption', [
            'data' => $this->budgetReport->byProject($projectCode, $month),
            'projectCode' => $projectCode,
            'month' => $month->format('Y-m'),
            'can' => [
                'export' => $request->user()->can('reports.export'),
            ],
        ]);
    }

    public function vendorPerformance(Request $request): Response
    {
        $this->authorize('viewVendorPerformance');

        return Inertia::render('Reports/VendorPerformance', [
            'data' => $this->vendorReport->indentFrequency(),
            'can' => [
                'export' => $request->user()->can('reports.export'),
            ],
        ]);
    }

    public function equipmentCost(Request $request): Response
    {
        $this->authorize('viewEquipmentCost');

        $projectCode = $this->resolveProjectCode($request);
        $month = Carbon::parse($request->get('month', now()->format('Y-m')));

        return Inertia::render('Reports/EquipmentCost', [
            'data' => $this->equipmentReport->fleetSummary($projectCode, $month),
            'projectCode' => $projectCode,
            'month' => $month->format('Y-m'),
            'can' => [
                'export' => $request->user()->can('reports.export'),
            ],
        ]);
    }

    public function purchaseRequestStatus(Request $request): Response
    {
        $this->authorize('viewPurchaseRequestStatus');

        return $this->renderProcurementReport($request, 'Reports/PurchaseRequestStatus', function (array $filters) {
            return $this->purchaseRequestStatusReport->data($filters);
        });
    }

    public function purchaseOrderTrend(Request $request): Response
    {
        $this->authorize('viewPurchaseOrderTrend');

        return $this->renderProcurementReport($request, 'Reports/PurchaseOrderTrend', function (array $filters) {
            return $this->purchaseOrderTrendReport->data($filters);
        });
    }

    public function topSupplier(Request $request): Response
    {
        $this->authorize('viewTopSupplier');

        return $this->renderProcurementReport($request, 'Reports/TopSupplier', function (array $filters) {
            return $this->topSupplierReport->data($filters);
        });
    }

    public function approvalTurnaround(Request $request): Response
    {
        $this->authorize('viewApprovalTurnaround');

        return $this->renderProcurementReport($request, 'Reports/ApprovalTurnaround', function (array $filters) {
            return $this->approvalTurnaroundReport->data($filters);
        });
    }

    public function purchaseRequestByDepartment(Request $request): Response
    {
        $this->authorize('viewPurchaseRequestByDepartment');

        return $this->renderProcurementReport($request, 'Reports/PurchaseRequestByDepartment', function (array $filters) {
            return $this->purchaseRequestByDepartmentReport->data($filters);
        });
    }

    public function exportPdf(Request $request, string $reportType): HttpResponse
    {
        $this->authorizeReportExport($reportType);

        $data = $this->reportData($request, $reportType);

        $pdf = Pdf::loadView("pdf.{$reportType}", ['data' => $data]);

        return $pdf->download("{$reportType}.pdf");
    }

    public function exportCsv(Request $request, string $reportType): StreamedResponse
    {
        $this->authorizeReportExport($reportType);

        $data = $this->reportData($request, $reportType);

        return response()->streamDownload(function () use ($reportType, $data) {
            $handle = fopen('php://output', 'w');
            [$headers, $rows] = $this->csvPayload($reportType, $data);
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, "{$reportType}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $loader
     */
    private function renderProcurementReport(Request $request, string $component, callable $loader): Response
    {
        $user = $request->user();
        $filterInputs = $this->procurementFilterInputs($request, $user);
        $data = $loader($filterInputs);

        return Inertia::render($component, [
            'data' => $data,
            'filters' => [
                'from' => $filterInputs['from'],
                'to' => $filterInputs['to'],
                'project_code' => $filterInputs['project_code'],
                'dept_code' => $filterInputs['dept_code'],
            ],
            'projects' => $this->projectOptions($user),
            'departments' => $this->departmentOptions($user, $filterInputs),
            'projectScope' => $this->projectScope($user),
            'can' => [
                'export' => $user?->can('reports.export') ?? false,
            ],
        ]);
    }

    private function resolveProjectCode(Request $request): string
    {
        return ProjectContext::resolve($request);
    }

    private function authorizeReportExport(string $reportType): void
    {
        match ($reportType) {
            'budget-consumption' => $this->authorize('exportBudgetConsumption'),
            'vendor-performance' => $this->authorize('exportVendorPerformance'),
            'equipment-cost' => $this->authorize('exportEquipmentCost'),
            'purchase-request-status' => $this->authorize('exportPurchaseRequestStatus'),
            'purchase-order-trend' => $this->authorize('exportPurchaseOrderTrend'),
            'top-supplier' => $this->authorize('exportTopSupplier'),
            'approval-turnaround' => $this->authorize('exportApprovalTurnaround'),
            'purchase-request-by-department' => $this->authorize('exportPurchaseRequestByDepartment'),
            default => $this->abortUnknownReportType(),
        };
    }

    /**
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    private function reportData(Request $request, string $reportType): array
    {
        $projectCode = $this->resolveProjectCode($request);
        $month = Carbon::parse($request->get('month', now()->format('Y-m')));

        return match ($reportType) {
            'budget-consumption' => $this->budgetReport->byProject($projectCode, $month),
            'vendor-performance' => $this->vendorReport->indentFrequency()->all(),
            'equipment-cost' => $this->equipmentReport->fleetSummary($projectCode, $month),
            'purchase-request-status' => $this->purchaseRequestStatusReport->data(
                $this->procurementFilterInputs($request, $request->user())
            ),
            'purchase-order-trend' => $this->purchaseOrderTrendReport->data(
                $this->procurementFilterInputs($request, $request->user())
            ),
            'top-supplier' => $this->topSupplierReport->data(
                $this->procurementFilterInputs($request, $request->user())
            ),
            'approval-turnaround' => $this->approvalTurnaroundReport->data(
                $this->procurementFilterInputs($request, $request->user())
            ),
            'purchase-request-by-department' => $this->purchaseRequestByDepartmentReport->data(
                $this->procurementFilterInputs($request, $request->user())
            ),
            default => $this->abortUnknownReportType(),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $data
     * @return array{0: list<string>, 1: list<list<string|int|bool>>}
     */
    private function csvPayload(string $reportType, array $data): array
    {
        return match ($reportType) {
            'budget-consumption' => $this->budgetConsumptionCsvRows($data),
            'vendor-performance' => [
                ['Vendor Code', 'Vendor Name', 'Indent %'],
                array_map(fn (array $row) => [
                    $row['vendor_code'] ?? '',
                    $row['vendor_name'] ?? '',
                    $row['indent_pct'] ?? '',
                ], $data),
            ],
            'equipment-cost' => [
                ['Equipment ID', 'Cost/Hour', 'Total Spend', 'Delta HM', 'Stale'],
                array_map(fn (array $row) => [
                    $row['equipment_id'] ?? '',
                    $row['cost_per_hour'] ?? '',
                    $row['total_spend'] ?? '',
                    $row['delta_hm'] ?? '',
                    ! empty($row['stale']) ? '1' : '0',
                ], $data),
            ],
            'purchase-request-status' => $this->procurementCsvFromService($data, [
                'department_code',
                'department_name',
                'pr_status',
                'closed_status',
                'document_count',
                'total_amount',
            ]),
            'purchase-order-trend' => $this->procurementCsvFromService($data, [
                'period',
                'document_count',
                'total_amount',
                'pmb_count',
                'sap_count',
                'pmb_amount',
                'sap_amount',
            ]),
            'top-supplier' => $this->procurementCsvFromService($data, [
                'rank',
                'vendor_code',
                'vendor_name',
                'document_count',
                'total_amount',
                'share_pct',
            ]),
            'approval-turnaround' => $this->approvalTurnaroundCsvRows($data),
            'purchase-request-by-department' => $this->procurementCsvFromService($data, [
                'department_code',
                'department_name',
                'project_code',
                'document_count',
                'total_amount',
            ]),
            default => $this->abortUnknownReportType(),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function procurementCsvFromService(array $data, array $keys): array
    {
        $headers = $data['csv_headers'] ?? [];
        $rows = collect($data['rows'] ?? [])
            ->map(fn (array $row) => array_map(fn (string $key) => $row[$key] ?? '', $keys))
            ->values()
            ->all();

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function approvalTurnaroundCsvRows(array $data): array
    {
        $headers = $data['csv_headers'] ?? [];
        $rows = collect($data['rows'] ?? [])
            ->map(fn (array $row) => [
                $row['department_code'] ?? '',
                $row['department_name'] ?? '',
                $row['document_count'] ?? '',
                $row['average_days'] ?? '',
                $row['median_days'] ?? '',
                $row['buckets']['0_3'] ?? '',
                $row['buckets']['4_7'] ?? '',
                $row['buckets']['8_14'] ?? '',
                $row['buckets']['over_14'] ?? '',
            ])
            ->values()
            ->all();

        return [$headers, $rows];
    }

    /**
     * @param  array{summary: array<string, mixed>|null, units: list<array<string, mixed>>}  $data
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function budgetConsumptionCsvRows(array $data): array
    {
        $headers = [
            'Section',
            'Unit Code',
            'Budget Ceiling',
            'Committed',
            'Actual',
            'Remaining',
            'Utilization %',
            'Request Count',
            'Total Estimated',
            'Last Status',
        ];
        $rows = [];

        if (! empty($data['summary'])) {
            $summary = $data['summary'];
            $rows[] = [
                'project',
                $summary['project_code'] ?? '',
                $summary['pagu'] ?? '',
                $summary['committed'] ?? '',
                $summary['actual'] ?? '',
                $summary['remaining'] ?? '',
                $summary['utilization_pct'] ?? '',
                '',
                '',
                '',
            ];
        }

        foreach ($data['units'] ?? [] as $unit) {
            $rows[] = [
                'unit',
                $unit['unit_code'] ?? '',
                '',
                '',
                '',
                '',
                '',
                $unit['request_count'] ?? '',
                $unit['total_estimated'] ?? '',
                $unit['last_status'] ? RoleLabels::status((string) $unit['last_status']) : '',
            ];
        }

        return [$headers, $rows];
    }

    /**
     * @return array{from: string, to: string, project_code: string|null, dept_code: string|null}
     */
    private function procurementFilterInputs(Request $request, ?User $user): array
    {
        $from = $request->input('from', now()->subDays(30)->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        if (! is_string($from) || $from === '') {
            $from = now()->subDays(30)->format('Y-m-d');
        }
        if (! is_string($to) || $to === '') {
            $to = now()->format('Y-m-d');
        }

        $projectCode = null;
        if ($user !== null && ! ProjectContext::allowSwitch($user)) {
            $scope = $user->project_code_scope;
            if (is_string($scope) && $scope !== '') {
                $projectCode = $scope;
            }
        } else {
            $inputProject = $request->input('project_code');
            if (is_string($inputProject) && $inputProject !== ''
                && ProjectContext::isAccessibleProjectCode($user, $inputProject)) {
                $projectCode = $inputProject;
            }
        }

        $deptCode = $request->input('dept_code');
        $deptCode = is_string($deptCode) && $deptCode !== '' ? $deptCode : null;

        return [
            'from' => $from,
            'to' => $to,
            'project_code' => $projectCode,
            'dept_code' => $deptCode,
        ];
    }

    private function projectScope(?User $user): ?string
    {
        if ($user === null || ProjectContext::allowSwitch($user)) {
            return null;
        }

        $scope = $user->project_code_scope;

        return is_string($scope) && $scope !== '' ? $scope : null;
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
            return ProjectCache::query()
                ->where('is_active', true)
                ->orderBy('project_code')
                ->get(['project_code', 'project_name'])
                ->map(fn (ProjectCache $row) => [
                    'project_code' => $row->project_code,
                    'project_name' => $row->project_name,
                ])
                ->values()
                ->all();
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
     * @param  array{from: string, to: string, project_code: string|null, dept_code: string|null}  $filterInputs
     * @return array<int, array{dept_code: string, dept_name: string}>
     */
    private function departmentOptions(?User $user, array $filterInputs): array
    {
        unset($user);

        $prCodes = config('procurement.pr_department_codes', []);
        $poCodes = config('procurement.po_department_codes', []);
        $codes = array_values(array_unique(array_merge($prCodes, $poCodes)));

        $query = SapPurchaseRequest::query()->whereNotNull('department_code');
        if ($codes !== []) {
            $query->whereIn('department_code', $codes);
        }
        if (is_string($filterInputs['project_code']) && $filterInputs['project_code'] !== '') {
            $query->where('project_code', $filterInputs['project_code']);
        }

        $fromPr = $query
            ->select('department_code', 'department_name')
            ->distinct()
            ->get()
            ->mapWithKeys(fn (SapPurchaseRequest $row) => [
                (string) $row->department_code => (string) ($row->department_name ?? $row->department_code),
            ]);

        $poQuery = SapPurchaseOrder::query()->whereNotNull('dept_code');
        if ($codes !== []) {
            $poQuery->whereIn('dept_code', $codes);
        }
        if (is_string($filterInputs['project_code']) && $filterInputs['project_code'] !== '') {
            $poQuery->where('project_code', $filterInputs['project_code']);
        }

        $fromPo = $poQuery
            ->select('dept_code', 'dept_name')
            ->distinct()
            ->get()
            ->mapWithKeys(fn (SapPurchaseOrder $row) => [
                (string) $row->dept_code => (string) ($row->dept_name ?? $row->dept_code),
            ]);

        return $fromPr->merge($fromPo)
            ->sort()
            ->map(fn (string $name, string $code) => ['dept_code' => $code, 'dept_name' => $name])
            ->values()
            ->all();
    }

    private function abortUnknownReportType(): never
    {
        throw new HttpResponseException(
            response('Report type not found.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ])
        );
    }
}
