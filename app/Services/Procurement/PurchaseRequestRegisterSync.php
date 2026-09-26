<?php

namespace App\Services\Procurement;

use App\Models\SapPurchaseRequest;
use App\Models\SapPurchaseRequestLine;
use Illuminate\Support\Collection;

class PurchaseRequestRegisterSync
{
    /**
     * @param  Collection<int, object>  $rows
     * @return array{documents_created: int, documents_updated: int, lines_created: int, lines_updated: int}
     */
    public function sync(Collection $rows): array
    {
        $allowedDepartments = array_map(
            static fn ($code): string => (string) $code,
            config('procurement.pr_department_codes', [])
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

            if (! in_array((string) ($headerRow->department_code ?? ''), $allowedDepartments, true)) {
                continue;
            }

            $headerAttributes = [
                'doc_num' => $headerRow->doc_num ?? null,
                'doc_date' => $this->nullableDate($headerRow->doc_date ?? null),
                'create_date' => $this->nullableDateTime($headerRow->create_date ?? null),
                'pr_type' => $headerRow->pr_type ?? null,
                'department_code' => $headerRow->department_code ?? null,
                'department_name' => $headerRow->department_name ?? null,
                'requester' => $headerRow->requester ?? null,
                'mr_no' => $headerRow->mr_no ?? null,
                'project_code' => $headerRow->project_code ?? null,
                'synced_at' => $syncedAt,
            ];

            $request = SapPurchaseRequest::query()->where('sap_doc_entry', $docEntry)->first();

            if ($request === null) {
                $request = SapPurchaseRequest::query()->create([
                    'sap_doc_entry' => $docEntry,
                    ...$headerAttributes,
                ]);
                $summary['documents_created']++;
            } else {
                $request->fill($headerAttributes);
                if ($request->isDirty()) {
                    $request->save();
                    $summary['documents_updated']++;
                } else {
                    $request->touch();
                }
            }

            foreach ($documentRows as $lineRow) {
                if (! in_array((string) ($lineRow->department_code ?? ''), $allowedDepartments, true)) {
                    continue;
                }

                $lineAttributes = [
                    'sap_purchase_request_id' => $request->id,
                    'sap_doc_entry' => $docEntry,
                    'line_num' => (int) $lineRow->line_num,
                    'vis_order' => (int) $lineRow->vis_order,
                    'item_code' => $lineRow->item_code ?? null,
                    'description' => $lineRow->description ?? null,
                    'qty' => $this->nullableDecimal($lineRow->qty ?? null),
                    'uom' => $lineRow->uom ?? null,
                    'unit_price' => $this->nullableDecimal($lineRow->unit_price ?? null),
                    'line_vendor_code' => $lineRow->line_vendor_code ?? null,
                ];

                $existingLine = SapPurchaseRequestLine::query()
                    ->where('sap_doc_entry', $docEntry)
                    ->where('line_num', $lineRow->line_num)
                    ->where('vis_order', $lineRow->vis_order)
                    ->first();

                if ($existingLine === null) {
                    SapPurchaseRequestLine::query()->create($lineAttributes);
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
