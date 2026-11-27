<?php

namespace Tests\Feature\Procurement;

use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use App\Models\SapPurchaseRequest;
use App\Models\SapPurchaseRequestLine;
use App\Services\Procurement\PurchaseOrderRegisterSync;
use App\Services\Procurement\PurchaseRequestRegisterSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementRegisterSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'procurement.po_department_codes' => ['40', '200'],
            'procurement.pr_department_codes' => ['4', '5'],
        ]);
    }

    public function test_first_sync_persists_documents_and_lines_second_sync_does_not_duplicate(): void
    {
        $rows = collect([
            $this->poRow(sapDocEntry: 1001, lineNum: 0, visOrder: 0, deptCode: '40'),
            $this->poRow(sapDocEntry: 1001, lineNum: 1, visOrder: 1, deptCode: '40'),
        ]);

        $sync = app(PurchaseOrderRegisterSync::class);

        $first = $sync->sync($rows);
        $this->assertSame(1, $first['documents_created']);
        $this->assertSame(2, $first['lines_created']);

        $second = $sync->sync($rows);
        $this->assertSame(0, $second['documents_created']);
        $this->assertSame(0, $second['lines_created']);

        $this->assertSame(1, SapPurchaseOrder::query()->count());
        $this->assertSame(2, SapPurchaseOrderLine::query()->count());
    }

    public function test_po_sync_persists_total_amount_from_idr_local_columns_not_fc(): void
    {
        $rows = collect([
            $this->poRow(
                sapDocEntry: 5001,
                lineNum: 0,
                visOrder: 0,
                deptCode: '40',
                totalAmount: 1123320,
                vatAmount: 123456,
                discAmount: 0,
            ),
        ]);

        app(PurchaseOrderRegisterSync::class)->sync($rows);

        $order = SapPurchaseOrder::query()->where('sap_doc_entry', 5001)->first();
        $this->assertNotNull($order);
        $this->assertSame('1123320.00', $order->total_amount);
        $this->assertSame('123456.00', $order->vat_amount);
        $this->assertSame('0.00', $order->disc_amount);
    }

    public function test_po_sync_persists_fc_fallback_amounts_when_repository_returns_them(): void
    {
        $rows = collect([
            $this->poRow(
                sapDocEntry: 5002,
                lineNum: 0,
                visOrder: 0,
                deptCode: '40',
                totalAmount: 50000,
                vatAmount: 5000,
                discAmount: 250,
            ),
        ]);

        app(PurchaseOrderRegisterSync::class)->sync($rows);

        $order = SapPurchaseOrder::query()->where('sap_doc_entry', 5002)->first();
        $this->assertSame('50000.00', $order->total_amount);
        $this->assertSame('5000.00', $order->vat_amount);
        $this->assertSame('250.00', $order->disc_amount);
    }

    public function test_po_line_outside_department_scope_is_skipped(): void
    {
        $rows = collect([
            $this->poRow(sapDocEntry: 2001, lineNum: 0, visOrder: 0, deptCode: '40'),
            $this->poRow(sapDocEntry: 2002, lineNum: 0, visOrder: 0, deptCode: '99'),
        ]);

        $summary = app(PurchaseOrderRegisterSync::class)->sync($rows);

        $this->assertSame(1, $summary['documents_created']);
        $this->assertSame(1, $summary['lines_created']);
        $this->assertSame(1, SapPurchaseOrder::query()->count());
        $this->assertDatabaseMissing('sap_purchase_orders', ['sap_doc_entry' => 2002]);
    }

    public function test_second_sync_updates_qty_and_price_on_same_line(): void
    {
        $initial = collect([
            $this->poRow(
                sapDocEntry: 3001,
                lineNum: 0,
                visOrder: 0,
                deptCode: '40',
                qty: 2,
                unitPrice: 1000,
            ),
        ]);

        $sync = app(PurchaseOrderRegisterSync::class);
        $sync->sync($initial);

        $updated = collect([
            $this->poRow(
                sapDocEntry: 3001,
                lineNum: 0,
                visOrder: 0,
                deptCode: '40',
                qty: 5,
                unitPrice: 2500,
            ),
        ]);

        $summary = $sync->sync($updated);

        $this->assertSame(0, $summary['lines_created']);
        $this->assertSame(1, $summary['lines_updated']);
        $this->assertSame(1, SapPurchaseOrderLine::query()->count());

        $line = SapPurchaseOrderLine::query()->first();
        $this->assertSame('5.00', $line->qty);
        $this->assertSame('2500.00', $line->unit_price);
    }

    public function test_purchase_request_register_sync_is_idempotent(): void
    {
        $rows = collect([
            $this->prRow(sapDocEntry: 4001, lineNum: 0, visOrder: 0, departmentCode: '4'),
        ]);

        $sync = app(PurchaseRequestRegisterSync::class);

        $sync->sync($rows);
        $sync->sync($rows);

        $this->assertSame(1, SapPurchaseRequest::query()->count());
        $this->assertSame(1, SapPurchaseRequestLine::query()->count());
    }

    public function test_pr_sync_matches_by_doc_num_and_rehomes_sap_doc_entry(): void
    {
        $legacyEntry = 900_001;
        $sapEntry = 700_001;
        $docNum = 12345;

        $legacy = SapPurchaseRequest::query()->create([
            'sap_doc_entry' => $legacyEntry,
            'doc_num' => $docNum,
            'department_code' => '4',
            'department_name' => 'Plant',
            'requester' => 'Legacy',
            'synced_at' => now(),
        ]);

        SapPurchaseRequestLine::query()->create([
            'sap_purchase_request_id' => $legacy->id,
            'sap_doc_entry' => $legacyEntry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'OLD',
            'qty' => '1.00',
            'unit_price' => '10.00',
            'line_amount' => '10.00',
        ]);

        $rows = collect([
            $this->prRow(
                sapDocEntry: $sapEntry,
                lineNum: 0,
                visOrder: 0,
                departmentCode: '4',
                docNum: $docNum,
                requester: 'From SAP',
            ),
        ]);

        $summary = app(PurchaseRequestRegisterSync::class)->sync($rows);

        $this->assertSame(1, $summary['documents_matched_by_doc_num']);
        $this->assertSame(0, $summary['entry_conflicts_skipped']);
        $this->assertSame(1, $summary['documents_updated']);
        $this->assertSame(0, $summary['documents_created']);

        $legacy->refresh();
        $this->assertSame($sapEntry, (int) $legacy->sap_doc_entry);
        $this->assertSame('From SAP', $legacy->requester);

        $line = SapPurchaseRequestLine::query()->where('sap_purchase_request_id', $legacy->id)->first();
        $this->assertNotNull($line);
        $this->assertSame($sapEntry, (int) $line->sap_doc_entry);
    }

    public function test_pr_sync_skips_entry_change_when_target_entry_taken(): void
    {
        $legacyEntry = 900_002;
        $sapEntry = 700_002;
        $docNum = 22345;

        SapPurchaseRequest::query()->create([
            'sap_doc_entry' => $sapEntry,
            'doc_num' => 99999,
            'department_code' => '4',
            'department_name' => 'Other',
            'synced_at' => now(),
        ]);

        $legacy = SapPurchaseRequest::query()->create([
            'sap_doc_entry' => $legacyEntry,
            'doc_num' => $docNum,
            'department_code' => '4',
            'department_name' => 'Plant',
            'requester' => 'Before',
            'synced_at' => now(),
        ]);

        $rows = collect([
            $this->prRow(
                sapDocEntry: $sapEntry,
                lineNum: 0,
                visOrder: 0,
                departmentCode: '4',
                docNum: $docNum,
                requester: 'After sync',
            ),
        ]);

        $summary = app(PurchaseRequestRegisterSync::class)->sync($rows);

        $this->assertSame(1, $summary['entry_conflicts_skipped']);
        $this->assertSame(1, $summary['documents_matched_by_doc_num']);
        $this->assertSame(1, $summary['documents_updated']);

        $legacy->refresh();
        $this->assertSame($legacyEntry, (int) $legacy->sap_doc_entry);
        $this->assertSame('After sync', $legacy->requester);
    }

    public function test_pr_sync_updates_line_rows_after_header_entry_rehomes(): void
    {
        $legacyEntry = 900_003;
        $sapEntry = 700_003;
        $docNum = 32345;

        $legacy = SapPurchaseRequest::query()->create([
            'sap_doc_entry' => $legacyEntry,
            'doc_num' => $docNum,
            'department_code' => '4',
            'department_name' => 'Plant',
            'synced_at' => now(),
        ]);

        SapPurchaseRequestLine::query()->create([
            'sap_purchase_request_id' => $legacy->id,
            'sap_doc_entry' => $legacyEntry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'PN-A',
            'qty' => '2.00',
            'unit_price' => '100.00',
            'line_amount' => '200.00',
        ]);

        $rows = collect([
            $this->prRow(
                sapDocEntry: $sapEntry,
                lineNum: 0,
                visOrder: 0,
                departmentCode: '4',
                docNum: $docNum,
                qty: 5,
                unitPrice: 100,
            ),
        ]);

        app(PurchaseRequestRegisterSync::class)->sync($rows);

        $this->assertSame(1, SapPurchaseRequestLine::query()->count());
        $line = SapPurchaseRequestLine::query()->first();
        $this->assertSame($sapEntry, (int) $line->sap_doc_entry);
        $this->assertSame('5.00', $line->qty);
    }

    public function test_pr_sync_retries_on_duplicate_sap_doc_entry_during_create(): void
    {
        $sapEntry = 88050;
        $docNum = 488050;
        $seeded = false;

        SapPurchaseRequest::creating(function (SapPurchaseRequest $model) use ($sapEntry, &$seeded): void {
            if ($seeded || (int) $model->sap_doc_entry !== $sapEntry) {
                return;
            }

            if (SapPurchaseRequest::query()->where('sap_doc_entry', $sapEntry)->exists()) {
                return;
            }

            $seeded = true;

            SapPurchaseRequest::withoutEvents(function () use ($sapEntry): void {
                SapPurchaseRequest::query()->create([
                    'sap_doc_entry' => $sapEntry,
                    'doc_num' => 11111,
                    'department_code' => '4',
                    'department_name' => 'Plant',
                    'requester' => 'Racer',
                    'synced_at' => now(),
                ]);
            });
        });

        $rows = collect([
            $this->prRow(
                sapDocEntry: $sapEntry,
                lineNum: 0,
                visOrder: 0,
                departmentCode: '4',
                docNum: $docNum,
                requester: 'Winner',
            ),
        ]);

        $summary = app(PurchaseRequestRegisterSync::class)->sync($rows);

        $this->assertSame(1, $summary['duplicate_retries']);
        $this->assertSame(0, $summary['documents_created']);
        $this->assertSame(1, SapPurchaseRequest::query()->count());
        $request = SapPurchaseRequest::query()->first();
        $this->assertSame('Winner', $request->requester);
    }

    public function test_pr_sync_falls_back_to_sap_doc_entry_when_doc_num_empty(): void
    {
        $entry = 600_100;

        SapPurchaseRequest::query()->create([
            'sap_doc_entry' => $entry,
            'doc_num' => null,
            'department_code' => '4',
            'department_name' => 'Plant',
            'requester' => 'No doc num',
            'synced_at' => now(),
        ]);

        $rows = collect([
            $this->prRow(
                sapDocEntry: $entry,
                lineNum: 0,
                visOrder: 0,
                departmentCode: '4',
                docNum: null,
                requester: 'Updated',
            ),
        ]);

        $summary = app(PurchaseRequestRegisterSync::class)->sync($rows);

        $this->assertSame(0, $summary['documents_matched_by_doc_num']);
        $this->assertSame(0, $summary['documents_created']);
        $this->assertSame(1, $summary['documents_updated']);
        $this->assertSame(1, $summary['lines_created']);
    }

    public function test_register_sync_summary_counters_for_create_and_line_update(): void
    {
        $sync = app(PurchaseOrderRegisterSync::class);

        $rows = collect([
            $this->poRow(sapDocEntry: 33001, lineNum: 0, visOrder: 0, deptCode: '40', qty: 1, unitPrice: 100),
        ]);

        $first = $sync->sync($rows);
        $this->assertSame(1, $first['documents_created']);
        $this->assertSame(0, $first['documents_updated']);
        $this->assertSame(1, $first['lines_created']);
        $this->assertSame(0, $first['lines_updated']);
        $this->assertSame(0, $first['documents_matched_by_doc_num']);
        $this->assertSame(0, $first['entry_conflicts_skipped']);
        $this->assertSame(0, $first['duplicate_retries']);

        $updatedRows = collect([
            $this->poRow(sapDocEntry: 33001, lineNum: 0, visOrder: 0, deptCode: '40', qty: 4, unitPrice: 250),
        ]);

        $second = $sync->sync($updatedRows);
        $this->assertSame(0, $second['documents_created']);
        $this->assertSame(0, $second['documents_updated']);
        $this->assertSame(0, $second['lines_created']);
        $this->assertSame(1, $second['lines_updated']);
    }

    private function poRow(
        int $sapDocEntry,
        int $lineNum,
        int $visOrder,
        string $deptCode,
        float $qty = 1,
        float $unitPrice = 500,
        float $totalAmount = 1000,
        float $vatAmount = 100,
        float $discAmount = 0,
    ): object {
        return (object) [
            'sap_doc_entry' => $sapDocEntry,
            'line_num' => $lineNum,
            'vis_order' => $visOrder,
            'doc_num' => 9000 + $sapDocEntry,
            'doc_date' => '2026-09-01',
            'create_date' => '2026-09-01 08:00:00',
            'delivery_date' => '2026-09-10 00:00:00',
            'po_eta' => '2026-09-12 00:00:00',
            'pr_no' => 'PR-100',
            'vendor_code' => 'V001',
            'vendor_name' => 'Vendor One',
            'project_code' => '021C',
            'dept_code' => $deptCode,
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
            'vat_amount' => $vatAmount,
            'disc_amount' => $discAmount,
            'delivery_status' => 'N',
            'budget_type' => 'OPEX',
            'item_code' => 'ITEM-1',
            'description' => 'Filter',
            'qty' => $qty,
            'uom' => 'PCS',
            'unit_price' => $unitPrice,
            'item_amount' => $qty * $unitPrice,
            'unit_no' => 'DT-01',
            'remark1' => null,
            'remark2' => null,
        ];
    }

    private function prRow(
        int $sapDocEntry,
        int $lineNum,
        int $visOrder,
        string $departmentCode,
        mixed $docNum = null,
        ?string $requester = null,
        float $qty = 3,
        float $unitPrice = 750,
    ): object {
        return (object) [
            'sap_doc_entry' => $sapDocEntry,
            'line_num' => $lineNum,
            'vis_order' => $visOrder,
            'doc_num' => $docNum ?? 8000 + $sapDocEntry,
            'doc_date' => '2026-09-02',
            'create_date' => '2026-09-02 09:00:00',
            'pr_type' => 'I',
            'department_code' => $departmentCode,
            'department_name' => 'Plant',
            'requester' => $requester ?? 'John Planner',
            'mr_no' => 'MR-55',
            'project_code' => '021C',
            'item_code' => 'PN-1',
            'description' => 'Seal kit',
            'qty' => $qty,
            'uom' => 'SET',
            'unit_price' => $unitPrice,
            'line_vendor_code' => 'V002',
        ];
    }
}
