<?php

namespace App\Services\Reporting\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesProcurementReportFilters
{
    /**
     * @param  array{from: string, to: string, project_code: string|null, dept_code: string|null}  $filters
     */
    protected function applyPurchaseRequestFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('doc_date', [$filters['from'], $filters['to']]);

        $allowedDepts = config('procurement.pr_department_codes', []);
        if ($allowedDepts !== []) {
            $query->whereIn('department_code', $allowedDepts);
        }

        if (is_string($filters['project_code']) && $filters['project_code'] !== '') {
            $query->where('project_code', $filters['project_code']);
        }

        if (is_string($filters['dept_code']) && $filters['dept_code'] !== '') {
            $query->where('department_code', $filters['dept_code']);
        }

        return $query;
    }

    /**
     * Project and department scope without doc_date window (e.g. PR linked to a PO in period).
     *
     * @param  array{from: string, to: string, project_code: string|null, dept_code: string|null}  $filters
     */
    protected function applyPurchaseRequestScope(Builder $query, array $filters): Builder
    {
        $allowedDepts = config('procurement.pr_department_codes', []);
        if ($allowedDepts !== []) {
            $query->whereIn('department_code', $allowedDepts);
        }

        if (is_string($filters['project_code']) && $filters['project_code'] !== '') {
            $query->where('project_code', $filters['project_code']);
        }

        if (is_string($filters['dept_code']) && $filters['dept_code'] !== '') {
            $query->where('department_code', $filters['dept_code']);
        }

        return $query;
    }

    /**
     * @param  array{from: string, to: string, project_code: string|null, dept_code: string|null}  $filters
     */
    protected function applyPurchaseOrderFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('doc_date', [$filters['from'], $filters['to']]);

        $allowedDepts = config('procurement.po_department_codes', []);
        if ($allowedDepts !== []) {
            $query->whereIn('dept_code', $allowedDepts);
        }

        if (is_string($filters['project_code']) && $filters['project_code'] !== '') {
            $query->where('project_code', $filters['project_code']);
        }

        if (is_string($filters['dept_code']) && $filters['dept_code'] !== '') {
            $query->where('dept_code', $filters['dept_code']);
        }

        return $query;
    }

    /**
     * @return array{from: string, to: string, project_code: string|null, dept_code: string|null}
     */
    protected function normalizeFilters(array $filters): array
    {
        return [
            'from' => (string) ($filters['from'] ?? ''),
            'to' => (string) ($filters['to'] ?? ''),
            'project_code' => isset($filters['project_code']) && $filters['project_code'] !== ''
                ? (string) $filters['project_code']
                : null,
            'dept_code' => isset($filters['dept_code']) && $filters['dept_code'] !== ''
                ? (string) $filters['dept_code']
                : null,
        ];
    }
}
