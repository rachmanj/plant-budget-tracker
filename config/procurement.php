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

    // Default department sent on SAP writes from PMB. Must be one of pr_department_codes / po_department_codes
    // so sync pulls the document back into the procurement register (PR: 4/5 Plant/Logistic; PO: 40/200 Plant & Warehouse).
    'default_pr_department_code' => env('PROCUREMENT_PR_DEPARTMENT_CODE', '4'),

    'default_po_department_code' => env('PROCUREMENT_PO_DEPARTMENT_CODE', '40'),
];
