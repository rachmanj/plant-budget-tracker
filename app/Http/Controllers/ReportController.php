<?php

namespace App\Http\Controllers;

use App\Services\Reporting\BudgetConsumptionReport;
use App\Support\ProjectContext;
use App\Support\RoleLabels;
use App\Services\Reporting\EquipmentCostReport;
use App\Services\Reporting\VendorPerformanceReport;
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
            default => $this->abortUnknownReportType(),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportData(Request $request, string $reportType): array
    {
        $projectCode = $this->resolveProjectCode($request);
        $month = Carbon::parse($request->get('month', now()->format('Y-m')));

        return match ($reportType) {
            'budget-consumption' => $this->budgetReport->byProject($projectCode, $month),
            'vendor-performance' => $this->vendorReport->indentFrequency()->all(),
            'equipment-cost' => $this->equipmentReport->fleetSummary($projectCode, $month),
            default => $this->abortUnknownReportType(),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
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
            default => $this->abortUnknownReportType(),
        };
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

    private function abortUnknownReportType(): never
    {
        throw new HttpResponseException(
            response('Report type not found.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ])
        );
    }
}
