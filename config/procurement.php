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
];
