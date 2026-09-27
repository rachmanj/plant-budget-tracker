<?php

namespace App\Services\Reporting;

use App\Models\SapPurchaseRequest;
use App\Services\Reporting\Concerns\AppliesProcurementReportFilters;

class PurchaseRequestByDepartmentReport
{
    use AppliesProcurementReportFilters;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{from: string, to: string, project_code: string|null, dept_code: string|null},
     *     summary: array{document_count: int, total_amount: string, department_count: int},
     *     rows: list<array<string, mixed>>,
     *     csv_headers: list<string>
     * }
     */
    public function data(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        $aggregates = $this->applyPurchaseRequestFilters(SapPurchaseRequest::query(), $normalized)
            ->selectRaw('department_code, department_name, project_code, COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->groupBy('department_code', 'department_name', 'project_code')
            ->orderBy('department_name')
            ->orderBy('project_code')
            ->get();

        $totals = $this->applyPurchaseRequestFilters(SapPurchaseRequest::query(), $normalized)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $rows = $aggregates->map(fn ($row) => [
            'department_code' => (string) ($row->department_code ?? ''),
            'department_name' => (string) ($row->department_name ?? ''),
            'project_code' => (string) ($row->project_code ?? ''),
            'document_count' => (int) $row->document_count,
            'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
        ])->values()->all();

        $departmentCount = collect($rows)->pluck('department_code')->unique()->count();

        return [
            'filters' => $normalized,
            'summary' => [
                'document_count' => (int) ($totals->document_count ?? 0),
                'total_amount' => number_format((float) ($totals->total_amount ?? 0), 2, '.', ''),
                'department_count' => $departmentCount,
            ],
            'rows' => $rows,
            'csv_headers' => [
                'Department Code',
                'Department Name',
                'Project Code',
                'Document Count',
                'Total Amount',
            ],
        ];
    }
}
