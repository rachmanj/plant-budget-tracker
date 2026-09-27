<?php

namespace App\Services\Reporting;

use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseRequest;
use App\Services\Reporting\Concerns\AppliesProcurementReportFilters;
use Illuminate\Support\Collection;

class ApprovalTurnaroundReport
{
    use AppliesProcurementReportFilters;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{from: string, to: string, project_code: string|null, dept_code: string|null},
     *     summary: array<string, mixed>,
     *     rows: list<array<string, mixed>>,
     *     csv_headers: list<string>
     * }
     */
    public function data(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        $pairs = $this->matchingPairs($normalized);
        $byDepartment = $pairs->groupBy(fn (array $row) => $row['department_code'].'|'.$row['department_name']);

        $rows = $byDepartment->map(function (Collection $items, string $key) {
            [$deptCode, $deptName] = array_pad(explode('|', $key, 2), 2, '');
            $days = $items->pluck('turnaround_days')->sort()->values();
            $stats = $this->statsForDays($days->all());

            $buckets = $this->bucketCounts($days->all());

            return array_merge([
                'department_code' => $deptCode,
                'department_name' => $deptName,
            ], $stats, ['buckets' => $buckets]);
        })->sortBy('department_name')->values()->all();

        $allDays = $pairs->pluck('turnaround_days')->sort()->values()->all();
        $overall = $this->statsForDays($allDays);
        $overall['buckets'] = $this->bucketCounts($allDays);

        return [
            'filters' => $normalized,
            'summary' => $overall,
            'rows' => $rows,
            'csv_headers' => [
                'Department Code',
                'Department Name',
                'Document Count',
                'Average Days',
                'Median Days',
                '0-3 Days',
                '4-7 Days',
                '8-14 Days',
                'Over 14 Days',
            ],
        ];
    }

    /**
     * @param  array{from: string, to: string, project_code: string|null, dept_code: string|null}  $filters
     * @return Collection<int, array{department_code: string, department_name: string, turnaround_days: int}>
     */
    private function matchingPairs(array $filters): Collection
    {
        $orders = $this->applyPurchaseOrderFilters(SapPurchaseOrder::query(), $filters)
            ->whereNotNull('pr_no')
            ->where('pr_no', '!=', '')
            ->get(['id', 'pr_no', 'doc_date', 'dept_code', 'dept_name']);

        if ($orders->isEmpty()) {
            return collect();
        }

        $prNumbers = $orders->pluck('pr_no')->unique()->values();

        $requests = $this->applyPurchaseRequestScope(SapPurchaseRequest::query(), $filters)
            ->whereIn('doc_num', $prNumbers)
            ->get(['doc_num', 'doc_date', 'department_code', 'department_name'])
            ->keyBy(fn (SapPurchaseRequest $pr) => (string) $pr->doc_num);

        return $orders->map(function (SapPurchaseOrder $po) use ($requests) {
            $pr = $requests->get((string) $po->pr_no);
            if ($pr === null || $po->doc_date === null || $pr->doc_date === null) {
                return null;
            }

            $days = $pr->doc_date->diffInDays($po->doc_date, false);
            if ($days < 0) {
                $days = 0;
            }

            return [
                'department_code' => (string) ($pr->department_code ?? $po->dept_code ?? ''),
                'department_name' => (string) ($pr->department_name ?? $po->dept_name ?? ''),
                'turnaround_days' => (int) $days,
            ];
        })->filter()->values();
    }

    /**
     * @param  list<int>  $days
     * @return array{document_count: int, average_days: string, median_days: string}
     */
    private function statsForDays(array $days): array
    {
        $count = count($days);
        if ($count === 0) {
            return [
                'document_count' => 0,
                'average_days' => '0.00',
                'median_days' => '0.00',
            ];
        }

        sort($days);
        $average = array_sum($days) / $count;
        $middle = (int) floor($count / 2);
        if ($count % 2 === 1) {
            $median = $days[$middle];
        } else {
            $median = ($days[$middle - 1] + $days[$middle]) / 2;
        }

        return [
            'document_count' => $count,
            'average_days' => number_format($average, 2, '.', ''),
            'median_days' => number_format($median, 2, '.', ''),
        ];
    }

    /**
     * @param  list<int>  $days
     * @return array{0_3: int, 4_7: int, 8_14: int, over_14: int}
     */
    private function bucketCounts(array $days): array
    {
        $buckets = ['0_3' => 0, '4_7' => 0, '8_14' => 0, 'over_14' => 0];

        foreach ($days as $day) {
            if ($day <= 3) {
                $buckets['0_3']++;
            } elseif ($day <= 7) {
                $buckets['4_7']++;
            } elseif ($day <= 14) {
                $buckets['8_14']++;
            } else {
                $buckets['over_14']++;
            }
        }

        return $buckets;
    }
}
