<?php

namespace App\Http\Controllers;

use App\Services\Reporting\BudgetConsumptionReport;
use App\Support\ProjectContext;
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
                    'title' => 'Konsumsi Anggaran',
                    'description' => 'Alokasi, komitmen, dan aktual per unit.',
                    'href' => route('reports.budget-consumption'),
                ],
                [
                    'key' => 'vendor-performance',
                    'title' => 'Kinerja Vendor',
                    'description' => 'Frekuensi indent dan performa pemasok.',
                    'href' => route('reports.vendor-performance'),
                ],
                [
                    'key' => 'equipment-cost',
                    'title' => 'Biaya Peralatan',
                    'description' => 'Ringkasan biaya peralatan fleet.',
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
            'budget-consumption' => [
                ['unit_code', 'allocated', 'committed', 'actual', 'carry_forward', 'variance'],
                array_map(fn (array $row) => [
                    $row['unit_code'] ?? '',
                    $row['allocated'] ?? '',
                    $row['committed'] ?? '',
                    $row['actual'] ?? '',
                    $row['carry_forward'] ?? '',
                    $row['variance'] ?? '',
                ], $data),
            ],
            'vendor-performance' => [
                ['vendor_code', 'vendor_name', 'indent_pct'],
                array_map(fn (array $row) => [
                    $row['vendor_code'] ?? '',
                    $row['vendor_name'] ?? '',
                    $row['indent_pct'] ?? '',
                ], $data),
            ],
            'equipment-cost' => [
                ['equipment_id', 'cost_per_hour', 'total_spend', 'delta_hm', 'stale'],
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

    private function abortUnknownReportType(): never
    {
        throw new HttpResponseException(
            response('Jenis laporan tidak ditemukan.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ])
        );
    }
}
