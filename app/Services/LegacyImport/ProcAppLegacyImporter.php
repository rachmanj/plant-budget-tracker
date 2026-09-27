<?php

namespace App\Services\LegacyImport;

use App\Models\DocumentComment;
use App\Models\DocumentCommentMention;
use App\Models\DocumentFollow;
use App\Models\ItemPrice;
use App\Models\ItemPriceHistory;
use App\Models\LegacyPurchaseOrderApproval;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use App\Models\SapPurchaseRequest;
use App\Models\SapPurchaseRequestLine;
use App\Support\LegacyImport\ImportSummary;
use App\Support\LegacyImport\LegacyCsvReader;
use Illuminate\Support\Carbon;
class ProcAppLegacyImporter
{
    public const LEGACY_SOURCE = 'proc_app';

    public const PRICE_SOURCE = 'legacy_import';

    private const PO_SYNTHETIC_BASE = 9000000000000;

    private const PR_SYNTHETIC_BASE = 8000000000000;

    public function __construct(
        private readonly LegacyCsvReader $csvReader,
    ) {}

    /**
     * @return array<string, ImportSummary>
     */
    public function import(string $directory, bool $dryRun = false): array
    {
        $summaries = [];

        $summaries['purchase_orders'] = $this->importPurchaseOrders($directory, $dryRun);
        $summaries['purchase_order_lines'] = $this->importPurchaseOrderLines($directory, $dryRun);
        $summaries['purchase_requests'] = $this->importPurchaseRequests($directory, $dryRun);
        $summaries['purchase_request_lines'] = $this->importPurchaseRequestLines($directory, $dryRun);
        $summaries['purchase_order_approvals'] = $this->importPurchaseOrderApprovals($directory, $dryRun);
        $summaries['item_prices'] = $this->importItemPrices($directory, $dryRun);
        $summaries['item_price_histories'] = $this->importItemPriceHistories($directory, $dryRun);
        $summaries['comments'] = $this->importOptionalFile($directory, 'comments.csv', 'comments', $dryRun, fn () => $this->importComments($directory, $dryRun));
        $summaries['comment_mentions'] = $this->importOptionalFile($directory, 'comment_mentions.csv', 'comment_mentions', $dryRun, fn () => new ImportSummary('comment_mentions'));
        $summaries['po_follows'] = $this->importOptionalFile($directory, 'po_follows.csv', 'po_follows', $dryRun, fn () => $this->importPoFollows($directory, $dryRun));
        $summaries['pr_follows'] = $this->importOptionalFile($directory, 'pr_follows.csv', 'pr_follows', $dryRun, fn () => new ImportSummary('pr_follows'));

        return $summaries;
    }

    private function importPurchaseOrders(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('purchase_orders');
        $path = rtrim($directory, '/').'/purchase_orders.csv';

        if (! $this->csvReader->exists($directory, 'purchase_orders.csv')) {
            return $summary;
        }

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $docNum = $this->stringValue($row, 'doc_num', 'po_no', 'po_number');
                $procAppId = (int) ($row['id'] ?? 0);
                $sapDocEntry = $this->nullableInt($row, 'sap_doc_entry', 'doc_entry');

                if ($docNum === null && $sapDocEntry === null && $procAppId === 0) {
                    $summary->recordFailure($line, 'missing doc_num and sap_doc_entry');

                    continue;
                }

                if ($this->findExistingPurchaseOrder($sapDocEntry, $docNum) !== null) {
                    $summary->skipped++;

                    continue;
                }

                $resolvedDocEntry = $this->resolveSapDocEntry($sapDocEntry, $procAppId, self::PO_SYNTHETIC_BASE);

                if (SapPurchaseOrder::query()->where('sap_doc_entry', $resolvedDocEntry)->exists()) {
                    $summary->skipped++;

                    continue;
                }

                $legacyDocNum = $docNum ?? (string) ($row['po_no'] ?? $procAppId);

                $attributes = [
                    'sap_doc_entry' => $resolvedDocEntry,
                    'doc_num' => $this->nullableIntValue($docNum),
                    'doc_date' => $this->nullableDate($row, 'doc_date', 'po_date'),
                    'create_date' => $this->nullableDateTime($row, 'create_date', 'created_at'),
                    'delivery_date' => $this->nullableDateTime($row, 'delivery_date'),
                    'po_eta' => $this->nullableDateTime($row, 'po_eta'),
                    'pr_no' => $this->stringValue($row, 'pr_no', 'pr_number'),
                    'vendor_code' => $this->stringValue($row, 'vendor_code', 'supplier_code'),
                    'vendor_name' => $this->stringValue($row, 'vendor_name', 'supplier_name'),
                    'project_code' => $this->stringValue($row, 'project_code'),
                    'dept_code' => $this->stringValue($row, 'dept_code', 'department_code'),
                    'dept_name' => $this->stringValue($row, 'dept_name', 'department_name'),
                    'currency' => $this->stringValue($row, 'currency') ?? 'IDR',
                    'total_amount' => $this->nullableDecimal($row, 'total_amount', 'total_po_price'),
                    'vat_amount' => $this->nullableDecimal($row, 'vat_amount', 'po_with_vat'),
                    'disc_amount' => $this->nullableDecimal($row, 'disc_amount'),
                    'delivery_status' => $this->stringValue($row, 'delivery_status', 'po_status'),
                    'budget_type' => $this->stringValue($row, 'budget_type'),
                    'origin' => 'sap',
                    'legacy_source' => self::LEGACY_SOURCE,
                    'legacy_doc_num' => $legacyDocNum,
                    'synced_at' => null,
                ];

                if (! $dryRun) {
                    SapPurchaseOrder::query()->create($attributes);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function importPurchaseOrderLines(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('purchase_order_lines');
        $path = rtrim($directory, '/').'/purchase_order_lines.csv';

        if (! $this->csvReader->exists($directory, 'purchase_order_lines.csv')) {
            return $summary;
        }

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $order = $this->resolveOrderForLine($row);
                if ($order === null) {
                    $summary->recordFailure($line, 'purchase order not found in register');

                    continue;
                }

                $sapDocEntry = $this->nullableInt($row, 'sap_doc_entry', 'doc_entry') ?? (int) $order->sap_doc_entry;
                $lineNum = (int) ($row['line_num'] ?? $row['sap_line_num'] ?? 0);
                $visOrder = (int) ($row['vis_order'] ?? $row['sap_vis_order'] ?? $lineNum);

                if (SapPurchaseOrderLine::query()
                    ->where('sap_doc_entry', $sapDocEntry)
                    ->where('line_num', $lineNum)
                    ->where('vis_order', $visOrder)
                    ->exists()) {
                    $summary->skipped++;

                    continue;
                }

                $lineAttributes = [
                    'sap_purchase_order_id' => $order->id,
                    'sap_doc_entry' => $sapDocEntry,
                    'line_num' => $lineNum,
                    'vis_order' => $visOrder,
                    'item_code' => $this->stringValue($row, 'item_code'),
                    'description' => $this->stringValue($row, 'description', 'item_description'),
                    'qty' => $this->nullableDecimal($row, 'qty', 'quantity'),
                    'uom' => $this->stringValue($row, 'uom'),
                    'unit_price' => $this->nullableDecimal($row, 'unit_price'),
                    'item_amount' => $this->nullableDecimal($row, 'item_amount', 'line_amount'),
                    'project_code' => $this->stringValue($row, 'project_code') ?? $order->project_code,
                    'unit_no' => $this->stringValue($row, 'unit_no', 'for_unit'),
                    'remark1' => $this->stringValue($row, 'remark1'),
                    'remark2' => $this->stringValue($row, 'remark2'),
                ];

                if (! $dryRun) {
                    SapPurchaseOrderLine::query()->create($lineAttributes);
                    $this->refreshOrderTotalFromLines($order);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function importPurchaseRequests(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('purchase_requests');
        $path = rtrim($directory, '/').'/purchase_requests.csv';

        if (! $this->csvReader->exists($directory, 'purchase_requests.csv')) {
            return $summary;
        }

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $docNum = $this->stringValue($row, 'doc_num', 'pr_no', 'pr_number');
                $procAppId = (int) ($row['id'] ?? 0);
                $sapDocEntry = $this->nullableInt($row, 'sap_doc_entry', 'doc_entry');

                if ($docNum === null && $sapDocEntry === null && $procAppId === 0) {
                    $summary->recordFailure($line, 'missing doc_num and sap_doc_entry');

                    continue;
                }

                if ($this->findExistingPurchaseRequest($sapDocEntry, $docNum) !== null) {
                    $summary->skipped++;

                    continue;
                }

                $resolvedDocEntry = $this->resolveSapDocEntry($sapDocEntry, $procAppId, self::PR_SYNTHETIC_BASE);

                if (SapPurchaseRequest::query()->where('sap_doc_entry', $resolvedDocEntry)->exists()) {
                    $summary->skipped++;

                    continue;
                }

                $legacyDocNum = $docNum ?? (string) $procAppId;

                $attributes = [
                    'sap_doc_entry' => $resolvedDocEntry,
                    'doc_num' => $this->nullableIntValue($docNum),
                    'doc_date' => $this->nullableDate($row, 'doc_date', 'pr_date'),
                    'create_date' => $this->nullableDateTime($row, 'create_date', 'created_at'),
                    'pr_type' => $this->stringValue($row, 'pr_type'),
                    'department_code' => $this->stringValue($row, 'department_code', 'dept_code'),
                    'department_name' => $this->stringValue($row, 'department_name', 'dept_name'),
                    'requester' => $this->stringValue($row, 'requester', 'requestor'),
                    'mr_no' => $this->stringValue($row, 'mr_no'),
                    'required_date' => $this->nullableDate($row, 'required_date'),
                    'remarks' => $this->stringValue($row, 'remarks'),
                    'pr_status' => $this->stringValue($row, 'pr_status'),
                    'closed_status' => $this->stringValue($row, 'closed_status'),
                    'pr_rev_no' => $this->stringValue($row, 'pr_rev_no'),
                    'unit_no' => $this->stringValue($row, 'unit_no', 'for_unit'),
                    'hours_meter' => $this->nullableDecimal($row, 'hours_meter'),
                    'line_count' => $this->nullableInt($row, 'line_count') ?? 0,
                    'total_amount' => $this->nullableDecimal($row, 'total_amount'),
                    'project_code' => $this->stringValue($row, 'project_code'),
                    'legacy_source' => self::LEGACY_SOURCE,
                    'legacy_doc_num' => $legacyDocNum,
                    'synced_at' => null,
                ];

                if (! $dryRun) {
                    SapPurchaseRequest::query()->create($attributes);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function importPurchaseRequestLines(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('purchase_request_lines');
        $path = rtrim($directory, '/').'/purchase_request_lines.csv';

        if (! $this->csvReader->exists($directory, 'purchase_request_lines.csv')) {
            return $summary;
        }

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $request = $this->resolveRequestForLine($row);
                if ($request === null) {
                    $summary->recordFailure($line, 'purchase request not found in register');

                    continue;
                }

                $sapDocEntry = $this->nullableInt($row, 'sap_doc_entry', 'doc_entry') ?? (int) $request->sap_doc_entry;
                $lineNum = (int) ($row['line_num'] ?? $row['sap_line_num'] ?? 0);
                $visOrder = (int) ($row['vis_order'] ?? $row['sap_vis_order'] ?? $lineNum);

                if (SapPurchaseRequestLine::query()
                    ->where('sap_doc_entry', $sapDocEntry)
                    ->where('line_num', $lineNum)
                    ->where('vis_order', $visOrder)
                    ->exists()) {
                    $summary->skipped++;

                    continue;
                }

                $lineAttributes = [
                    'sap_purchase_request_id' => $request->id,
                    'sap_doc_entry' => $sapDocEntry,
                    'line_num' => $lineNum,
                    'vis_order' => $visOrder,
                    'item_code' => $this->stringValue($row, 'item_code'),
                    'description' => $this->stringValue($row, 'description', 'item_description'),
                    'qty' => $this->nullableDecimal($row, 'qty', 'quantity'),
                    'uom' => $this->stringValue($row, 'uom'),
                    'unit_price' => $this->nullableDecimal($row, 'unit_price'),
                    'line_vendor_code' => $this->stringValue($row, 'line_vendor_code', 'vendor_code'),
                ];

                if (! $dryRun) {
                    SapPurchaseRequestLine::query()->create($lineAttributes);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function importPurchaseOrderApprovals(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('purchase_order_approvals');
        $path = rtrim($directory, '/').'/purchase_order_approvals.csv';

        if (! $this->csvReader->exists($directory, 'purchase_order_approvals.csv')) {
            return $summary;
        }

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $docNum = $this->stringValue($row, 'doc_num', 'po_no', 'po_number');
                if ($docNum === null) {
                    $summary->recordFailure($line, 'missing doc_num');

                    continue;
                }

                $level = $this->nullableInt($row, 'level', 'approval_level');
                $decision = $this->stringValue($row, 'decision', 'status') ?? 'unknown';
                $actedAt = $this->nullableDateTime($row, 'acted_at', 'approved_at', 'created_at');

                $exists = LegacyPurchaseOrderApproval::query()
                    ->where('doc_num', $docNum)
                    ->where('level', $level)
                    ->where('decision', $decision)
                    ->where(function ($query) use ($actedAt) {
                        if ($actedAt === null) {
                            $query->whereNull('acted_at');
                        } else {
                            $query->where('acted_at', $actedAt);
                        }
                    })
                    ->exists();

                if ($exists) {
                    $summary->skipped++;

                    continue;
                }

                if (! $dryRun) {
                    LegacyPurchaseOrderApproval::query()->create([
                        'doc_num' => $docNum,
                        'sap_doc_entry' => $this->nullableInt($row, 'sap_doc_entry', 'doc_entry'),
                        'level' => $level,
                        'required_role' => $this->stringValue($row, 'required_role', 'role'),
                        'approver_name' => $this->stringValue($row, 'approver_name', 'approved_by'),
                        'decision' => $decision,
                        'remarks' => $this->stringValue($row, 'remarks', 'comment'),
                        'acted_at' => $actedAt,
                        'legacy_source' => self::LEGACY_SOURCE,
                    ]);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function importItemPrices(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('item_prices');
        $path = rtrim($directory, '/').'/item_prices.csv';

        if (! $this->csvReader->exists($directory, 'item_prices.csv')) {
            return $summary;
        }

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $itemCode = $this->stringValue($row, 'item_code');
                $vendorCode = $this->stringValue($row, 'vendor_code') ?? '';
                $incomingPrice = $this->nullableDecimal($row, 'price');
                $incomingEffective = $this->nullableDate($row, 'effective_date');

                if ($itemCode === null || $incomingPrice === null) {
                    $summary->recordFailure($line, 'missing item_code or price');

                    continue;
                }

                $existing = ItemPrice::query()
                    ->where('item_code', $itemCode)
                    ->where('vendor_code', $vendorCode)
                    ->first();

                if ($existing !== null && $this->existingPriceIsNewerThanLegacy($existing, $incomingEffective)) {
                    $summary->skipped++;

                    continue;
                }

                $attributes = [
                    'uom' => $this->stringValue($row, 'uom'),
                    'price' => $incomingPrice,
                    'currency' => $this->stringValue($row, 'currency') ?? 'IDR',
                    'effective_date' => $incomingEffective,
                    'source' => self::PRICE_SOURCE,
                    'note' => $this->stringValue($row, 'note'),
                ];

                if ($existing === null) {
                    if (! $dryRun) {
                        ItemPrice::query()->create([
                            'item_code' => $itemCode,
                            'vendor_code' => $vendorCode,
                            ...$attributes,
                        ]);
                    }
                    $summary->created++;
                } else {
                    if (! $dryRun) {
                        $existing->fill($attributes);
                        if ($existing->isDirty()) {
                            $existing->save();
                            $summary->updated++;
                        } else {
                            $summary->skipped++;
                        }
                    } else {
                        $summary->updated++;
                    }
                }
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function importItemPriceHistories(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('item_price_histories');
        $path = rtrim($directory, '/').'/item_price_histories.csv';

        if (! $this->csvReader->exists($directory, 'item_price_histories.csv')) {
            return $summary;
        }

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $itemCode = $this->stringValue($row, 'item_code');
                $vendorCode = $this->stringValue($row, 'vendor_code') ?? '';
                $newPrice = $this->nullableDecimal($row, 'new_price', 'price');
                $effectiveDate = $this->nullableDate($row, 'effective_date');

                if ($itemCode === null || $newPrice === null) {
                    $summary->recordFailure($line, 'missing item_code or price');

                    continue;
                }

                $exists = ItemPriceHistory::query()
                    ->where('item_code', $itemCode)
                    ->where('vendor_code', $vendorCode)
                    ->where('new_price', $newPrice)
                    ->where('source', self::PRICE_SOURCE)
                    ->where(function ($query) use ($effectiveDate) {
                        if ($effectiveDate === null) {
                            $query->whereNull('effective_date');
                        } else {
                            $query->whereDate('effective_date', $effectiveDate);
                        }
                    })
                    ->exists();

                if ($exists) {
                    $summary->skipped++;

                    continue;
                }

                if (! $dryRun) {
                    ItemPriceHistory::query()->create([
                        'item_code' => $itemCode,
                        'vendor_code' => $vendorCode,
                        'old_price' => $this->nullableDecimal($row, 'old_price'),
                        'new_price' => $newPrice,
                        'currency' => $this->stringValue($row, 'currency') ?? 'IDR',
                        'source' => self::PRICE_SOURCE,
                        'effective_date' => $effectiveDate,
                        'note' => $this->stringValue($row, 'note'),
                    ]);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function importPoFollows(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('po_follows');
        $path = rtrim($directory, '/').'/po_follows.csv';

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $docNum = $this->stringValue($row, 'doc_num', 'po_no');
                $userId = $this->nullableInt($row, 'user_id');
                if ($docNum === null || $userId === null) {
                    $summary->recordFailure($line, 'missing doc_num or user_id');

                    continue;
                }

                $order = $this->findExistingPurchaseOrder(null, $docNum);
                if ($order === null) {
                    $summary->recordFailure($line, 'purchase order not found');

                    continue;
                }

                $exists = DocumentFollow::query()
                    ->where('followable_type', 'purchase_order')
                    ->where('followable_id', $order->id)
                    ->where('user_id', $userId)
                    ->exists();

                if ($exists) {
                    $summary->skipped++;

                    continue;
                }

                if (! $dryRun) {
                    DocumentFollow::query()->create([
                        'followable_type' => 'purchase_order',
                        'followable_id' => $order->id,
                        'user_id' => $userId,
                    ]);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * @param  callable(): ImportSummary  $importer
     */
    private function importOptionalFile(
        string $directory,
        string $filename,
        string $label,
        bool $dryRun,
        callable $importer,
    ): ImportSummary {
        if (! $this->csvReader->exists($directory, $filename)) {
            return new ImportSummary($label);
        }

        return $importer();
    }

    private function importComments(string $directory, bool $dryRun): ImportSummary
    {
        $summary = new ImportSummary('comments');
        $path = rtrim($directory, '/').'/comments.csv';

        foreach ($this->csvReader->rows($path) as $line => $row) {
            $summary->read++;

            try {
                $docType = $this->stringValue($row, 'doc_type', 'document_type');
                $docNum = $this->stringValue($row, 'doc_num');
                $body = $this->stringValue($row, 'body', 'comment');
                $userId = $this->nullableInt($row, 'user_id');

                if ($docType === null || $docNum === null || $body === null || $userId === null) {
                    $summary->recordFailure($line, 'incomplete comment row');

                    continue;
                }

                $attachable = $docType === 'pr'
                    ? $this->findExistingPurchaseRequest(null, $docNum)
                    : $this->findExistingPurchaseOrder(null, $docNum);

                if ($attachable === null) {
                    $summary->recordFailure($line, 'document not found');

                    continue;
                }

                $commentableType = $docType === 'pr' ? 'purchase_request' : 'purchase_order';

                if (! $dryRun) {
                    DocumentComment::query()->create([
                        'commentable_type' => $commentableType,
                        'commentable_id' => $attachable->id,
                        'user_id' => $userId,
                        'body' => $body,
                    ]);
                }

                $summary->created++;
            } catch (\Throwable $e) {
                $summary->recordFailure($line, $e->getMessage());
            }
        }

        return $summary;
    }

    private function findExistingPurchaseOrder(?int $sapDocEntry, ?string $docNum): ?SapPurchaseOrder
    {
        if ($sapDocEntry !== null && $sapDocEntry > 0) {
            $byEntry = SapPurchaseOrder::query()->where('sap_doc_entry', $sapDocEntry)->first();
            if ($byEntry !== null) {
                return $byEntry;
            }
        }

        if ($docNum === null || $docNum === '') {
            return null;
        }

        $numeric = ctype_digit($docNum) ? (int) $docNum : null;

        return SapPurchaseOrder::query()
            ->where(function ($query) use ($docNum, $numeric) {
                $query->where('legacy_doc_num', $docNum);
                if ($numeric !== null) {
                    $query->orWhere('doc_num', $numeric);
                }
            })
            ->first();
    }

    private function findExistingPurchaseRequest(?int $sapDocEntry, ?string $docNum): ?SapPurchaseRequest
    {
        if ($sapDocEntry !== null && $sapDocEntry > 0) {
            $byEntry = SapPurchaseRequest::query()->where('sap_doc_entry', $sapDocEntry)->first();
            if ($byEntry !== null) {
                return $byEntry;
            }
        }

        if ($docNum === null || $docNum === '') {
            return null;
        }

        $numeric = ctype_digit($docNum) ? (int) $docNum : null;

        return SapPurchaseRequest::query()
            ->where(function ($query) use ($docNum, $numeric) {
                $query->where('legacy_doc_num', $docNum);
                if ($numeric !== null) {
                    $query->orWhere('doc_num', $numeric);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function resolveOrderForLine(array $row): ?SapPurchaseOrder
    {
        $sapDocEntry = $this->nullableInt($row, 'sap_doc_entry', 'doc_entry');
        $docNum = $this->stringValue($row, 'doc_num', 'po_no', 'po_number');
        $procAppPoId = $this->stringValue($row, 'purchase_order_id', 'po_id');

        $order = $this->findExistingPurchaseOrder($sapDocEntry, $docNum);
        if ($order !== null) {
            return $order;
        }

        if ($procAppPoId !== null) {
            $synthetic = self::PO_SYNTHETIC_BASE + (int) $procAppPoId;

            return SapPurchaseOrder::query()->where('sap_doc_entry', $synthetic)->first();
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function resolveRequestForLine(array $row): ?SapPurchaseRequest
    {
        $sapDocEntry = $this->nullableInt($row, 'sap_doc_entry', 'doc_entry');
        $docNum = $this->stringValue($row, 'doc_num', 'pr_no', 'pr_number');
        $procAppPrId = $this->stringValue($row, 'purchase_request_id', 'pr_id');

        $request = $this->findExistingPurchaseRequest($sapDocEntry, $docNum);
        if ($request !== null) {
            return $request;
        }

        if ($procAppPrId !== null) {
            $synthetic = self::PR_SYNTHETIC_BASE + (int) $procAppPrId;

            return SapPurchaseRequest::query()->where('sap_doc_entry', $synthetic)->first();
        }

        return null;
    }

    private function resolveSapDocEntry(?int $sapDocEntry, int $procAppId, int $syntheticBase): int
    {
        if ($sapDocEntry !== null && $sapDocEntry > 0) {
            return $sapDocEntry;
        }

        return $syntheticBase + max($procAppId, 1);
    }

    private function refreshOrderTotalFromLines(SapPurchaseOrder $order): void
    {
        if ($order->legacy_source !== self::LEGACY_SOURCE) {
            return;
        }

        $total = SapPurchaseOrderLine::query()
            ->where('sap_purchase_order_id', $order->id)
            ->sum('item_amount');

        if ($total === null) {
            return;
        }

        $current = (float) ($order->total_amount ?? 0);
        if ($current > 0) {
            return;
        }

        $order->total_amount = $total;
        $order->save();
    }

    private function existingPriceIsNewerThanLegacy(ItemPrice $existing, ?Carbon $legacyEffective): bool
    {
        if ($existing->source !== self::PRICE_SOURCE && $existing->updated_at !== null) {
            if ($legacyEffective === null) {
                return true;
            }

            if ($existing->effective_date !== null && $existing->effective_date->gt($legacyEffective)) {
                return true;
            }

            return $existing->updated_at->gt($legacyEffective);
        }

        if ($existing->effective_date !== null && $legacyEffective !== null) {
            return $existing->effective_date->gt($legacyEffective);
        }

        return false;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  string  ...$keys
     */
    private function stringValue(array $row, ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  string  ...$keys
     */
    private function nullableInt(array $row, ...$keys): ?int
    {
        $value = $this->stringValue($row, ...$keys);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    private function nullableIntValue(?string $value): ?int
    {
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  string  ...$keys
     */
    private function nullableDecimal(array $row, ...$keys): ?string
    {
        $value = $this->stringValue($row, ...$keys);
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  string  ...$keys
     */
    private function nullableDate(array $row, ...$keys): ?Carbon
    {
        $value = $this->stringValue($row, ...$keys);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  string  ...$keys
     */
    private function nullableDateTime(array $row, ...$keys): ?Carbon
    {
        $value = $this->stringValue($row, ...$keys);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
