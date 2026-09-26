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

    private function poRow(
        int $sapDocEntry,
        int $lineNum,
        int $visOrder,
        string $deptCode,
        float $qty = 1,
        float $unitPrice = 500,
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
            'total_amount' => 1000,
            'vat_amount' => 100,
            'disc_amount' => 0,
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
    ): object {
        return (object) [
            'sap_doc_entry' => $sapDocEntry,
            'line_num' => $lineNum,
            'vis_order' => $visOrder,
            'doc_num' => 8000 + $sapDocEntry,
            'doc_date' => '2026-09-02',
            'create_date' => '2026-09-02 09:00:00',
            'pr_type' => 'I',
            'department_code' => $departmentCode,
            'department_name' => 'Plant',
            'requester' => 'John Planner',
            'mr_no' => 'MR-55',
            'project_code' => '021C',
            'item_code' => 'PN-1',
            'description' => 'Seal kit',
            'qty' => 3,
            'uom' => 'SET',
            'unit_price' => 750,
            'line_vendor_code' => 'V002',
        ];
    }
}
