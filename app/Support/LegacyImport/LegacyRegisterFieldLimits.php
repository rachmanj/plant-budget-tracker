<?php

namespace App\Support\LegacyImport;

class LegacyRegisterFieldLimits
{
    /** @var array<string, int> */
    public const PURCHASE_REQUEST_STRINGS = [
        'pr_type' => 32,
        'department_code' => 255,
        'department_name' => 255,
        'requester' => 255,
        'mr_no' => 255,
        'remarks' => 65535,
        'pr_status' => 32,
        'closed_status' => 32,
        'pr_rev_no' => 255,
        'unit_no' => 255,
        'project_code' => 255,
        'legacy_doc_num' => 255,
    ];

    /** @var array<string, int> */
    public const PURCHASE_ORDER_STRINGS = [
        'pr_no' => 255,
        'vendor_code' => 255,
        'vendor_name' => 255,
        'project_code' => 255,
        'dept_code' => 255,
        'dept_name' => 255,
        'currency' => 8,
        'delivery_status' => 32,
        'budget_type' => 255,
        'legacy_doc_num' => 255,
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, int>  $limits
     * @return array<string, mixed>|null  null when a protected key exceeds its limit
     */
    public static function apply(
        array $attributes,
        array $limits,
        int $line,
        ImportSummary $summary,
        array $protectedKeys = [],
    ): ?array {
        $truncatedColumns = [];

        foreach ($limits as $field => $maxLength) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $value = $attributes[$field];
            if ($value === null || ! is_string($value)) {
                continue;
            }

            if (mb_strlen($value) <= $maxLength) {
                continue;
            }

            if (in_array($field, $protectedKeys, true)) {
                $summary->recordFailure(
                    $line,
                    "column {$field} exceeds maximum length {$maxLength}",
                );

                return null;
            }

            $attributes[$field] = mb_substr($value, 0, $maxLength);
            $truncatedColumns[] = $field;
        }

        foreach ($truncatedColumns as $column) {
            $summary->recordFailure(
                $line,
                "column {$column} truncated to fit register (line {$line})",
            );
        }

        return $attributes;
    }
}
