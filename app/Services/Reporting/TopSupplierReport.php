<?php

namespace App\Services\Reporting;

use App\Models\SapPurchaseOrder;
use App\Services\Reporting\Concerns\AppliesProcurementReportFilters;

class TopSupplierReport
{
    use AppliesProcurementReportFilters;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{from: string, to: string, project_code: string|null, dept_code: string|null},
     *     summary: array{document_count: int, total_amount: string, supplier_count: int},
     *     rows: list<array<string, mixed>>,
     *     csv_headers: list<string>
     * }
     */
    public function data(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        $totals = $this->applyPurchaseOrderFilters(SapPurchaseOrder::query(), $normalized)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $grandTotal = (float) ($totals->total_amount ?? 0);

        $aggregates = $this->applyPurchaseOrderFilters(SapPurchaseOrder::query(), $normalized)
            ->selectRaw('vendor_code, vendor_name, COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->groupBy('vendor_code', 'vendor_name')
            ->orderByDesc('total_amount')
            ->get();

        $rows = $aggregates->values()->map(function ($row, int $index) use ($grandTotal) {
            $amount = (float) $row->total_amount;
            $share = $grandTotal > 0 ? ($amount / $grandTotal) * 100 : 0.0;

            return [
                'rank' => $index + 1,
                'vendor_code' => (string) ($row->vendor_code ?? ''),
                'vendor_name' => (string) ($row->vendor_name ?? ''),
                'document_count' => (int) $row->document_count,
                'total_amount' => number_format($amount, 2, '.', ''),
                'share_pct' => number_format($share, 2, '.', ''),
            ];
        })->values()->all();

        return [
            'filters' => $normalized,
            'summary' => [
                'document_count' => (int) ($totals->document_count ?? 0),
                'total_amount' => number_format($grandTotal, 2, '.', ''),
                'supplier_count' => count($rows),
            ],
            'rows' => $rows,
            'csv_headers' => [
                'Rank',
                'Vendor Code',
                'Vendor Name',
                'PO Count',
                'Total Amount',
                'Share %',
            ],
        ];
    }
}
