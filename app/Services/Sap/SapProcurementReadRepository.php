<?php

namespace App\Services\Sap;

use App\Contracts\Sap\SapProcurementReadRepositoryContract;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SapProcurementReadRepository implements SapProcurementReadRepositoryContract
{
    public function fetchPurchaseOrderRows(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $departmentCodes = config('procurement.po_department_codes', []);

        if ($departmentCodes === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($departmentCodes), '?'));

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
                A.[DocTotalFC] AS total_amount,
                A.[VatSumFC] AS vat_amount,
                A.[DiscSumFC] AS disc_amount,
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
              AND cc.[Code] IN ({$placeholders})
            SQL;

        $bindings = [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
            ...$departmentCodes,
        ];

        return collect(DB::connection('sap_sql')->select($sql, $bindings));
    }

    public function fetchPurchaseRequestRows(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $departmentCodes = config('procurement.pr_department_codes', []);

        if ($departmentCodes === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($departmentCodes), '?'));

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
                N.[Name] AS project_code,
                B.[ItemCode] AS item_code,
                B.[Dscription] AS description,
                B.[Quantity] AS qty,
                B.[unitMsr] AS uom,
                B.[Price] AS unit_price,
                B.[LineVendor] AS line_vendor_code
            FROM [OPRQ] A
            INNER JOIN [PRQ1] B ON A.[DocEntry] = B.[DocEntry]
            LEFT JOIN [OUDP] D ON A.[Department] = D.[Code]
            LEFT JOIN [OUSR] F ON A.[Requester] = F.[USER_CODE]
            LEFT JOIN [OUBR] N ON A.[Branch] = N.[Code]
            WHERE A.[CreateDate] >= ?
              AND A.[CreateDate] <= ?
              AND D.[Code] IN ({$placeholders})
            SQL;

        $bindings = [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
            ...$departmentCodes,
        ];

        return collect(DB::connection('sap_sql')->select($sql, $bindings));
    }
}
