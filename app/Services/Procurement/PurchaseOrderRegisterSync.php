<?php

namespace App\Services\Procurement;

use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use Illuminate\Support\Collection;

class PurchaseOrderRegisterSync
{
    /**
     * @param  Collection<int, object>  $rows
     * @return array{documents_created: int, documents_updated: int, lines_created: int, lines_updated: int}
     */
    public function sync(Collection $rows): array
    {
        $allowedDepartments = array_map(
            static fn ($code): string => (string) $code,
            config('procurement.po_department_codes', [])
        );

        $summary = [
            'documents_created' => 0,
            'documents_updated' => 0,
            'lines_created' => 0,
            'lines_updated' => 0,
        ];

        $syncedAt = now();

        foreach ($rows->groupBy('sap_doc_entry') as $docEntry => $documentRows) {
            $headerRow = $documentRows->first();

            if ($headerRow === null) {
                continue;
            }

            if (! in_array((string) ($headerRow->dept_code ?? ''), $allowedDepartments, true)) {
                continue;
            }

            $headerAttributes = [
                'doc_num' => $headerRow->doc_num ?? null,
                'doc_date' => $this->nullableDate($headerRow->doc_date ?? null),
                'create_date' => $this->nullableDateTime($headerRow->create_date ?? null),
                'delivery_date' => $this->nullableDateTime($headerRow->delivery_date ?? null),
                'po_eta' => $this->nullableDateTime($headerRow->po_eta ?? null),
                'pr_no' => $headerRow->pr_no ?? null,
                'vendor_code' => $headerRow->vendor_code ?? null,
                'vendor_name' => $headerRow->vendor_name ?? null,
                'project_code' => $headerRow->project_code ?? null,
                'dept_code' => $headerRow->dept_code ?? null,
                'dept_name' => $headerRow->dept_name ?? null,
                'currency' => $headerRow->currency ?? null,
                'total_amount' => $this->nullableDecimal($headerRow->total_amount ?? null),
                'vat_amount' => $this->nullableDecimal($headerRow->vat_amount ?? null),
                'disc_amount' => $this->nullableDecimal($headerRow->disc_amount ?? null),
                'delivery_status' => $headerRow->delivery_status ?? null,
                'budget_type' => $headerRow->budget_type ?? null,
                'synced_at' => $syncedAt,
            ];

            $order = SapPurchaseOrder::query()->where('sap_doc_entry', $docEntry)->first();

            if ($order === null) {
                $order = SapPurchaseOrder::query()->create([
                    'sap_doc_entry' => $docEntry,
                    ...$headerAttributes,
                ]);
                $summary['documents_created']++;
            } else {
                $order->fill($headerAttributes);
                if ($order->isDirty()) {
                    $order->save();
                    $summary['documents_updated']++;
                } else {
                    $order->touch();
                }
            }

            foreach ($documentRows as $lineRow) {
                if (! in_array((string) ($lineRow->dept_code ?? ''), $allowedDepartments, true)) {
                    continue;
                }

                $lineAttributes = [
                    'sap_purchase_order_id' => $order->id,
                    'sap_doc_entry' => $docEntry,
                    'line_num' => (int) $lineRow->line_num,
                    'vis_order' => (int) $lineRow->vis_order,
                    'item_code' => $lineRow->item_code ?? null,
                    'description' => $lineRow->description ?? null,
                    'qty' => $this->nullableDecimal($lineRow->qty ?? null),
                    'uom' => $lineRow->uom ?? null,
                    'unit_price' => $this->nullableDecimal($lineRow->unit_price ?? null),
                    'item_amount' => $this->nullableDecimal($lineRow->item_amount ?? null),
                    'project_code' => $lineRow->project_code ?? null,
                    'unit_no' => $lineRow->unit_no ?? null,
                    'remark1' => $lineRow->remark1 ?? null,
                    'remark2' => $lineRow->remark2 ?? null,
                ];

                $existingLine = SapPurchaseOrderLine::query()
                    ->where('sap_doc_entry', $docEntry)
                    ->where('line_num', $lineRow->line_num)
                    ->where('vis_order', $lineRow->vis_order)
                    ->first();

                if ($existingLine === null) {
                    SapPurchaseOrderLine::query()->create($lineAttributes);
                    $summary['lines_created']++;
                } else {
                    $existingLine->fill($lineAttributes);
                    if ($existingLine->isDirty()) {
                        $existingLine->save();
                        $summary['lines_updated']++;
                    }
                }
            }
        }

        return $summary;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return date('Y-m-d', strtotime((string) $value));
    }

    private function nullableDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime((string) $value));
    }
}
