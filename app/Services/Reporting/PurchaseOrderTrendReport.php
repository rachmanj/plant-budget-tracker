<?php

namespace App\Services\Reporting;

use App\Models\SapPurchaseOrder;
use App\Services\Reporting\Concerns\AppliesProcurementReportFilters;
use Carbon\Carbon;

class PurchaseOrderTrendReport
{
    use AppliesProcurementReportFilters;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{from: string, to: string, project_code: string|null, dept_code: string|null},
     *     summary: array{document_count: int, total_amount: string, pmb_count: int, sap_count: int},
     *     rows: list<array<string, mixed>>,
     *     csv_headers: list<string>,
     *     granularity: string
     * }
     */
    public function data(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $from = Carbon::parse($normalized['from'])->startOfDay();
        $to = Carbon::parse($normalized['to'])->startOfDay();
        $granularity = $from->diffInDays($to) <= 14 ? 'day' : 'month';

        $format = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m';
        $periodLabel = $granularity === 'day' ? 'period_day' : 'period_month';

        $aggregates = $this->applyPurchaseOrderFilters(SapPurchaseOrder::query(), $normalized)
            ->selectRaw("DATE_FORMAT(doc_date, '{$format}') as {$periodLabel}")
            ->selectRaw('COUNT(*) as document_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
            ->selectRaw("SUM(CASE WHEN origin = 'pmb' THEN 1 ELSE 0 END) as pmb_count")
            ->selectRaw("SUM(CASE WHEN origin = 'sap' OR origin IS NULL OR origin = '' THEN 1 ELSE 0 END) as sap_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN origin = 'pmb' THEN total_amount ELSE 0 END), 0) as pmb_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN origin = 'sap' OR origin IS NULL OR origin = '' THEN total_amount ELSE 0 END), 0) as sap_amount")
            ->groupBy($periodLabel)
            ->orderBy($periodLabel)
            ->get();

        $totals = $this->applyPurchaseOrderFilters(SapPurchaseOrder::query(), $normalized)
            ->selectRaw('COUNT(*) as document_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
            ->selectRaw("SUM(CASE WHEN origin = 'pmb' THEN 1 ELSE 0 END) as pmb_count")
            ->selectRaw("SUM(CASE WHEN origin = 'sap' OR origin IS NULL OR origin = '' THEN 1 ELSE 0 END) as sap_count")
            ->first();

        $rows = $aggregates->map(function ($row) use ($periodLabel, $granularity) {
            $period = (string) ($row->{$periodLabel} ?? '');

            return [
                'period' => $period,
                'period_label' => $granularity === 'month'
                    ? Carbon::createFromFormat('Y-m', $period)->format('M Y')
                    : Carbon::parse($period)->format('D MMM YYYY'),
                'document_count' => (int) $row->document_count,
                'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
                'pmb_count' => (int) $row->pmb_count,
                'sap_count' => (int) $row->sap_count,
                'pmb_amount' => number_format((float) $row->pmb_amount, 2, '.', ''),
                'sap_amount' => number_format((float) $row->sap_amount, 2, '.', ''),
            ];
        })->values()->all();

        return [
            'filters' => $normalized,
            'granularity' => $granularity,
            'summary' => [
                'document_count' => (int) ($totals->document_count ?? 0),
                'total_amount' => number_format((float) ($totals->total_amount ?? 0), 2, '.', ''),
                'pmb_count' => (int) ($totals->pmb_count ?? 0),
                'sap_count' => (int) ($totals->sap_count ?? 0),
            ],
            'rows' => $rows,
            'csv_headers' => [
                'Period',
                'PO Count',
                'Total Amount',
                'PMB PO Count',
                'SAP PO Count',
                'PMB Amount',
                'SAP Amount',
            ],
        ];
    }
}
