<?php

return [
    'pr_department_codes' => array_values(array_filter(array_map(
        static fn (string $code): string => trim($code),
        explode(',', (string) env('PROCUREMENT_PR_DEPARTMENT_CODES', '4,5'))
    ))),

    'po_department_codes' => array_values(array_filter(array_map(
        static fn (string $code): string => trim($code),
        explode(',', (string) env('PROCUREMENT_PO_DEPARTMENT_CODES', '40,200'))
    ))),

    // Sent as PR header Department (OUDP code; sync reads OPRQ.Department — 4 = Plant, 5 = Logistic).
    // Must be included in pr_department_codes so PMB procurement sync picks up the document.
    'default_pr_department_code' => env('PROCUREMENT_PR_DEPARTMENT_CODE', '4'),

    // Sent as PO U_MIS_CCDepartement (40 = Plant, 200 = Logistic & Warehouse); must be in po_department_codes.
    'default_po_department_code' => env('PROCUREMENT_PO_DEPARTMENT_CODE', '40'),
];
