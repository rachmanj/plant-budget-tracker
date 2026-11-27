<?php

namespace App\Services\Sap;

use App\Contracts\Sap\SapProcurementReadRepositoryContract;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SapProcurementReadRepository implements SapProcurementReadRepositoryContract
{
    private const MAX_INCLUDE_PER_QUERY = 500;

    public function fetchPurchaseOrderRows(CarbonInterface $from, CarbonInterface $to, array $includeDocEntries = []): Collection
    {
        $departmentCodes = config('procurement.po_department_codes', []);

        if ($departmentCodes === []) {
            return collect();
        }

        [$scopeClause, $scopeBindings] = $this->departmentScopeClause(
            'cc.[Code]',
            $departmentCodes,
            'A.[DocEntry]',
            $this->cappedIncludeList($includeDocEntries),
        );

        $sql = <<<SQL
            SELECT DISTINCT
                A.[DocNum] AS doc_num,
                A.[DocDate] AS doc_date,
                A.[CreateDate] AS create_date,
                A.[U_MIS_DeliveryTime] AS delivery_date,
                A.[U_MIS_EstArrival] AS po_eta,
                A.[U_MIS_PRNo] AS pr_no,
                A.[CardCode] AS vendor_code,
                A.[CardName] AS vendor_name,
                B.[Project] AS project_code,
                cc.[Code] AS dept_code,
                cc.[Name] AS dept_name,
                B.[Currency] AS currency,
                COALESCE(NULLIF(A.[DocTotal], 0), A.[DocTotalFC]) AS total_amount,
                COALESCE(NULLIF(A.[VatSum], 0), A.[VatSumFC]) AS vat_amount,
                COALESCE(NULLIF(A.[DiscSum], 0), A.[DiscSumFC]) AS disc_amount,
                A.[U_ARK_DelivStat] AS delivery_status,
                A.[U_ARK_BudgetType] AS budget_type,
                B.[DocEntry] AS sap_doc_entry,
                B.[LineNum] AS line_num,
                B.[VisOrder] AS vis_order,
                B.[ItemCode] AS item_code,
                B.[Dscription] AS description,
                B.[Quantity] AS qty,
                B.[unitMsr] AS uom,
                B.[Price] AS unit_price,
                (B.[Quantity] * B.[Price]) AS item_amount,
                B.[U_MIS_UnitNo] AS unit_no,
                B.[U_MIS_ConsRe1] AS remark1,
                B.[U_MIS_ConsRe2] AS remark2
            FROM [OPOR] A
            INNER JOIN [POR1] B ON A.[DocEntry] = B.[DocEntry]
            LEFT JOIN [@MIS_CCDPT] cc ON A.[U_MIS_CCDepartement] = cc.[Code]
            WHERE A.[CreateDate] >= ?
              AND A.[CreateDate] <= ?
              AND {$scopeClause}
            SQL;

        $bindings = [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
            ...$scopeBindings,
        ];

        return collect(DB::connection('sap_sql')->select($sql, $bindings));
    }

    public function fetchPurchaseRequestRows(CarbonInterface $from, CarbonInterface $to, array $includeDocNums = []): Collection
    {
        $departmentCodes = config('procurement.pr_department_codes', []);

        if ($departmentCodes === []) {
            return collect();
        }

        [$scopeClause, $scopeBindings] = $this->departmentScopeClause(
            'D.[Code]',
            $departmentCodes,
            'A.[DocNum]',
            $this->cappedIncludeList($includeDocNums),
        );

        $sql = <<<SQL
            SELECT DISTINCT
                A.[DocEntry] AS sap_doc_entry,
                B.[LineNum] AS line_num,
                B.[VisOrder] AS vis_order,
                A.[DocNum] AS doc_num,
                A.[DocDate] AS doc_date,
                A.[CreateDate] AS create_date,
                A.[DocType] AS pr_type,
                D.[Code] AS department_code,
                D.[Name] AS department_name,
                F.[U_NAME] AS requester,
                A.[U_MIS_MRNO] AS mr_no,
                COALESCE(A.[U_MIS_RequiredDate], A.[ReqDate]) AS required_date,
                A.[Comments] AS remarks,
                A.[DocStatus] AS pr_status,
                A.[U_MIS_CLOSESTAT] AS closed_status,
                A.[U_MIS_PRRevNo] AS pr_rev_no,
                A.[U_MIS_UnitNo] AS unit_no,
                A.[U_MIS_HoursMeter] AS hours_meter,
                N.[Name] AS project_code,
                B.[ItemCode] AS item_code,
                B.[Dscription] AS description,
                B.[Quantity] AS qty,
                B.[unitMsr] AS uom,
                B.[Price] AS unit_price,
                (B.[Quantity] * B.[Price]) AS line_amount,
                B.[LineVendor] AS line_vendor_code
            FROM [OPRQ] A
            INNER JOIN [PRQ1] B ON A.[DocEntry] = B.[DocEntry]
            LEFT JOIN [OUDP] D ON A.[Department] = D.[Code]
            LEFT JOIN [OUSR] F ON A.[Requester] = F.[USER_CODE]
            LEFT JOIN [OUBR] N ON A.[Branch] = N.[Code]
            WHERE A.[CreateDate] >= ?
              AND A.[CreateDate] <= ?
              AND {$scopeClause}
            SQL;

        $bindings = [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
            ...$scopeBindings,
        ];

        return collect(DB::connection('sap_sql')->select($sql, $bindings));
    }

    /**
     * @param  array<int|string>  $values
     * @return list<int|string>
     */
    private function cappedIncludeList(array $values): array
    {
        return collect($values)
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->unique()
            ->sortByDesc(static fn ($value) => (int) $value)
            ->take(self::MAX_INCLUDE_PER_QUERY)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $departmentCodes
     * @param  list<int|string>  $includeValues
     * @return array{0: string, 1: list<int|string>}
     */
    private function departmentScopeClause(
        string $departmentColumn,
        array $departmentCodes,
        string $includeColumn,
        array $includeValues,
    ): array {
        $deptPlaceholders = implode(',', array_fill(0, count($departmentCodes), '?'));
        $deptClause = "{$departmentColumn} IN ({$deptPlaceholders})";

        if ($includeValues === []) {
            return [$deptClause, $departmentCodes];
        }

        $includePlaceholders = implode(',', array_fill(0, count($includeValues), '?'));
        $clause = "({$deptClause} OR {$includeColumn} IN ({$includePlaceholders}))";

        return [$clause, [...$departmentCodes, ...$includeValues]];
    }
}