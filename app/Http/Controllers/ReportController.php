<?php

namespace App\Http\Controllers;

use App\Models\SapSyncLog;
use App\Services\Reporting\BudgetConsumptionReport;
use App\Services\Reporting\EquipmentCostReport;
use App\Services\Reporting\VendorPerformanceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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

    public function index(): Response
    {
        return Inertia::render('Reports/BudgetConsumption', ['reports' => []]);
    }

    public function budgetConsumption(Request $request): Response
    {
        $this->authorize('viewBudgetConsumption');

        $projectCode = $request->get('project_code', $request->user()->project_code_scope ?? 'MBL');
        $month = Carbon::parse($request->get('month', now()->format('Y-m')));

        return Inertia::render('Reports/BudgetConsumption', [
            'data' => $this->budgetReport->byProject($projectCode, $month),
            'projectCode' => $projectCode,
            'month' => $month->format('Y-m'),
        ]);
    }

    public function vendorPerformance(Request $request): Response
    {
        return Inertia::render('Reports/VendorPerformance', [
            'data' => $this->vendorReport->indentFrequency(),
        ]);
    }

    public function equipmentCost(Request $request): Response
    {
        $projectCode = $request->get('project_code', $request->user()->project_code_scope ?? 'MBL');
        $month = Carbon::parse($request->get('month', now()->format('Y-m')));

        return Inertia::render('Reports/EquipmentCost', [
            'data' => $this->equipmentReport->fleetSummary($projectCode, $month),
        ]);
    }

    public function exportPdf(Request $request, string $reportType): HttpResponse
    {
        $data = match ($reportType) {
            'budget-consumption' => $this->budgetReport->byProject(
                $request->get('project_code', 'MBL'),
                Carbon::parse($request->get('month', now()->format('Y-m')))
            ),
            'vendor-performance' => $this->vendorReport->indentFrequency()->all(),
            'equipment-cost' => $this->equipmentReport->fleetSummary(
                $request->get('project_code', 'MBL'),
                Carbon::parse($request->get('month', now()->format('Y-m')))
            ),
            default => [],
        };

        $pdf = Pdf::loadView("pdf.{$reportType}", ['data' => $data]);

        return $pdf->download("{$reportType}.pdf");
    }

    public function exportCsv(Request $request, string $reportType): StreamedResponse
    {
        $data = match ($reportType) {
            'budget-consumption' => $this->budgetReport->byProject(
                $request->get('project_code', 'MBL'),
                Carbon::parse($request->get('month', now()->format('Y-m')))
            ),
            default => [],
        };

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            if (! empty($data)) {
                fputcsv($handle, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($handle, $row);
                }
            }
            fclose($handle);
        }, "{$reportType}.csv", ['Content-Type' => 'text/csv']);
    }
}
