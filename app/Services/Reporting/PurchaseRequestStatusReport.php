<?php

namespace App\Services\Reporting;

use App\Models\SapPurchaseRequest;
use App\Services\Reporting\Concerns\AppliesProcurementReportFilters;

class PurchaseRequestStatusReport
{
    use AppliesProcurementReportFilters;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{from: string, to: string, project_code: string|null, dept_code: string|null},
     *     summary: array{document_count: int, total_amount: string},
     *     rows: list<array<string, mixed>>,
     *     csv_headers: list<string>
     * }
     */
    public function data(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        $aggregates = $this->applyPurchaseRequestFilters(SapPurchaseRequest::query(), $normalized)
            ->selectRaw('department_code, department_name, pr_status, closed_status, COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->groupBy('department_code', 'department_name', 'pr_status', 'closed_status')
            ->orderBy('department_name')
            ->orderBy('pr_status')
            ->orderBy('closed_status')
            ->get();

        $totals = $this->applyPurchaseRequestFilters(SapPurchaseRequest::query(), $normalized)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $rows = $aggregates->map(fn ($row) => [
            'department_code' => (string) ($row->department_code ?? ''),
            'department_name' => (string) ($row->department_name ?? ''),
            'pr_status' => (string) ($row->pr_status ?? ''),
            'closed_status' => (string) ($row->closed_status ?? ''),
            'document_count' => (int) $row->document_count,
            'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
        ])->values()->all();

        return [
            'filters' => $normalized,
            'summary' => [
                'document_count' => (int) ($totals->document_count ?? 0),
                'total_amount' => number_format((float) ($totals->total_amount ?? 0), 2, '.', ''),
            ],
            'rows' => $rows,
            'csv_headers' => [
                'Department Code',
                'Department Name',
                'PR Status',
                'Closed Status',
                'Document Count',
                'Total Amount',
            ],
        ];
    }
}
