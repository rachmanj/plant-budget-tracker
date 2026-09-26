<?php

namespace Tests\Feature\Procurement;

use App\Models\PlantRequest;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use App\Models\SapPurchaseRequest;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PurchaseOrderRegisterTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_procurement_admin_can_open_purchase_order_list(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $this->createPurchaseOrder(['doc_num' => 700001]);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Index', false)
                ->has('purchaseOrders.data', 1)
            );
    }

    public function test_mechanic_is_denied_on_list_and_show(): void
    {
        $mechanic = $this->makeUserWithRole('mechanic');
        $order = $this->createPurchaseOrder();

        $this->actingAsProject($mechanic)
            ->get('/procurement/purchase-orders')
            ->assertForbidden();

        $this->actingAsProject($mechanic)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertForbidden();
    }

    public function test_q_filter_matches_doc_num_vendor_and_line_item_code(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $match = $this->createPurchaseOrder(
            ['doc_num' => 811122, 'vendor_name' => 'Alpha Supply'],
            ['item_code' => 'FILTER-99']
        );
        $this->createPurchaseOrder(['doc_num' => 899999, 'vendor_name' => 'Other Vendor']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?q=811122')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $match->id)
            );

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?q=FILTER-99')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $match->id)
            );
    }

    public function test_project_code_filter_narrows_results(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $mbl = $this->createPurchaseOrder(['project_code' => 'MBL']);
        $this->createPurchaseOrder(['project_code' => '022C']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $mbl->id)
            );
    }

    public function test_dept_code_filter_narrows_results(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $plant = $this->createPurchaseOrder(['dept_code' => '40', 'dept_name' => 'Plant']);
        $this->createPurchaseOrder(['dept_code' => '200', 'dept_name' => 'Logistic and Warehouse']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?dept_code=40')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $plant->id)
            );
    }

    public function test_origin_filter_narrows_results(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $pmb = $this->createPurchaseOrder(['origin' => 'pmb']);
        $this->createPurchaseOrder(['origin' => 'sap']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?origin=pmb')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $pmb->id)
            );
    }

    public function test_doc_date_range_filter_narrows_results(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $inRange = $this->createPurchaseOrder(['doc_date' => '2026-06-15']);
        $this->createPurchaseOrder(['doc_date' => '2026-01-01']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $inRange->id)
            );
    }

    public function test_summary_reflects_entire_filtered_result_set(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $this->createPurchaseOrder(['project_code' => 'MBL', 'total_amount' => '1000000.00']);
        $this->createPurchaseOrder(['project_code' => 'MBL', 'total_amount' => '2500000.00']);
        $this->createPurchaseOrder(['project_code' => '022C', 'total_amount' => '9999999.00']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.document_count', 2)
                ->where('summary.total_amount', '3500000.00')
            );
    }

    public function test_show_page_includes_order_lines(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder([], ['line_num' => 1, 'vis_order' => 2, 'item_code' => 'PN-100']);
        $this->createLine($order, ['line_num' => 0, 'vis_order' => 0, 'item_code' => 'PN-001']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show', false)
                ->has('purchaseOrder.lines', 2)
                ->where('purchaseOrder.lines.0.line_num', 0)
                ->where('purchaseOrder.lines.1.line_num', 1)
            );
    }

    public function test_show_links_plant_request_when_plant_request_id_is_set(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $plantRequest = PlantRequest::factory()->create();
        $order = $this->createPurchaseOrder(['plant_request_id' => $plantRequest->id]);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchaseOrder.plant_request.id', $plantRequest->id)
                ->where('purchaseOrder.plant_request.request_no', $plantRequest->request_no)
            );
    }

    public function test_show_includes_related_purchase_request_when_pr_no_matches(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $pr = SapPurchaseRequest::create([
            'sap_doc_entry' => 55001,
            'doc_num' => 88001,
            'doc_date' => '2026-05-01',
            'project_code' => 'MBL',
        ]);
        $order = $this->createPurchaseOrder(['pr_no' => '88001']);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedPurchaseRequest.id', $pr->id)
                ->where('relatedPurchaseRequest.doc_num', 88001)
            );
    }

    public function test_per_page_fifty_is_honored(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');

        for ($i = 0; $i < 55; $i++) {
            $this->createPurchaseOrder(['sap_doc_entry' => 60000 + $i, 'doc_num' => 900000 + $i]);
        }

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?per_page=50')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 50)
                ->where('purchaseOrders.per_page', 50)
                ->where('purchaseOrders.total', 55)
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $lineOverrides
     */
    private function createPurchaseOrder(array $overrides = [], array $lineOverrides = []): SapPurchaseOrder
    {
        static $sequence = 9000;

        $sequence++;
        $sapDocEntry = $overrides['sap_doc_entry'] ?? $sequence;

        $order = SapPurchaseOrder::create(array_merge([
            'sap_doc_entry' => $sapDocEntry,
            'doc_num' => 100000 + $sequence,
            'doc_date' => '2026-03-10',
            'pr_no' => 'PR-TEST',
            'vendor_code' => 'V001',
            'vendor_name' => 'Vendor Test',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '500000.00',
            'vat_amount' => '0.00',
            'disc_amount' => '0.00',
            'delivery_status' => 'N',
            'budget_type' => 'OPEX',
            'origin' => 'sap',
            'synced_at' => now(),
        ], $overrides));

        $this->createLine($order, $lineOverrides);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLine(SapPurchaseOrder $order, array $overrides = []): SapPurchaseOrderLine
    {
        return SapPurchaseOrderLine::create(array_merge([
            'sap_purchase_order_id' => $order->id,
            'sap_doc_entry' => $order->sap_doc_entry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'ITEM-1',
            'description' => 'Test item',
            'qty' => '1.00',
            'uom' => 'PCS',
            'unit_price' => '500000.00',
            'item_amount' => '500000.00',
            'project_code' => $order->project_code,
        ], $overrides));
    }
}
