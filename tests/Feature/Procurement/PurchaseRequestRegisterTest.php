<?php

namespace Tests\Feature\Procurement;

use App\Models\ProjectCache;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseRequest;
use App\Models\SapPurchaseRequestLine;
use App\Services\Procurement\PurchaseRequestRegisterSync;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert as PHPUnit;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PurchaseRequestRegisterTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config(['procurement.pr_department_codes' => ['4', '5']]);

        ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'Maruwai',
            'is_active' => true,
        ]);
        ProjectCache::create([
            'project_code' => '022C',
            'project_name' => 'Project 022C',
            'is_active' => true,
        ]);
    }

    public function test_procurement_admin_can_open_purchase_request_list(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $this->createPurchaseRequest(['doc_num' => 770001]);

        $response = $this->actingAsProject($admin)
            ->get('/procurement/purchase-requests')
            ->assertOk();

        $page = $this->inertiaPageFrom($response);
        $this->assertSame('Procurement/PurchaseRequests/Index', $page['component']);
        $this->assertCount(1, $page['props']['purchaseRequests']['data']);
    }

    public function test_mechanic_is_denied_on_list_and_show(): void
    {
        $mechanic = $this->makeUserWithRole('mechanic');
        $pr = $this->createPurchaseRequest();

        $this->actingAsProject($mechanic)
            ->get('/procurement/purchase-requests')
            ->assertForbidden();

        $this->actingAsProject($mechanic)
            ->get('/procurement/purchase-requests/'.$pr->id)
            ->assertForbidden();
    }

    public function test_q_filter_matches_doc_num_mr_requester_and_line_item_code(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $match = $this->createPurchaseRequest(
            ['doc_num' => 881122, 'mr_no' => 'MR-ALPHA', 'requester' => 'Alice'],
            ['item_code' => 'FILTER-77']
        );
        $this->createPurchaseRequest(['doc_num' => 889999, 'requester' => 'Bob']);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)->get('/procurement/purchase-requests?q=881122')->assertOk()
        );
        $this->assertCount(1, $page['props']['purchaseRequests']['data']);
        $this->assertSame($match->id, $page['props']['purchaseRequests']['data'][0]['id']);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)->get('/procurement/purchase-requests?q=MR-ALPHA')->assertOk()
        );
        $this->assertSame($match->id, $page['props']['purchaseRequests']['data'][0]['id']);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)->get('/procurement/purchase-requests?q=FILTER-77')->assertOk()
        );
        $this->assertSame($match->id, $page['props']['purchaseRequests']['data'][0]['id']);
    }

    public function test_department_filter_narrows_results(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $plant = $this->createPurchaseRequest(['department_code' => '4', 'department_name' => 'Plant']);
        $this->createPurchaseRequest(['department_code' => '5', 'department_name' => 'Logistics']);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)->get('/procurement/purchase-requests?dept_code=4')->assertOk()
        );
        $this->assertSame($plant->id, $page['props']['purchaseRequests']['data'][0]['id']);
    }

    public function test_doc_date_range_filter_narrows_results(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $inRange = $this->createPurchaseRequest(['doc_date' => '2026-06-15']);
        $this->createPurchaseRequest(['doc_date' => '2026-01-01']);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)
                ->get('/procurement/purchase-requests?from=2026-06-01&to=2026-06-30')
                ->assertOk()
        );
        $this->assertSame($inRange->id, $page['props']['purchaseRequests']['data'][0]['id']);
    }

    public function test_summary_reflects_entire_filtered_result_set(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $this->createPurchaseRequest(['project_code' => 'MBL', 'total_amount' => '1000000.00']);
        $this->createPurchaseRequest(['project_code' => 'MBL', 'total_amount' => '2500000.00']);
        $this->createPurchaseRequest(['project_code' => '022C', 'total_amount' => '9999999.00']);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)
                ->get('/procurement/purchase-requests?project_code=MBL')
                ->assertOk()
        );
        $this->assertSame(2, $page['props']['summary']['document_count']);
        $this->assertSame('3500000.00', $page['props']['summary']['total_amount']);
    }

    public function test_show_page_includes_request_lines(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $pr = $this->createPurchaseRequest();
        $this->createLine($pr, ['line_num' => 1, 'item_code' => 'PN-200']);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)->get('/procurement/purchase-requests/'.$pr->id)->assertOk()
        );
        $this->assertSame('Procurement/PurchaseRequests/Show', $page['component']);
        $this->assertCount(2, $page['props']['purchaseRequest']['lines']);
    }

    public function test_show_links_purchase_orders_that_reference_pr_number(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $pr = $this->createPurchaseRequest(['doc_num' => 99001]);
        $po = SapPurchaseOrder::create([
            'sap_doc_entry' => 88001,
            'doc_num' => 300001,
            'doc_date' => '2026-05-01',
            'pr_no' => '99001',
            'vendor_code' => 'V001',
            'vendor_name' => 'Vendor Test',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '100000.00',
            'vat_amount' => '0.00',
            'disc_amount' => '0.00',
            'delivery_status' => 'N',
            'budget_type' => 'OPEX',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)->get('/procurement/purchase-requests/'.$pr->id)->assertOk()
        );
        $this->assertCount(1, $page['props']['relatedPurchaseOrders']);
        $this->assertSame($po->id, $page['props']['relatedPurchaseOrders'][0]['id']);
    }

    public function test_scoped_user_sees_only_own_project_and_gets_forbidden_on_other_detail(): void
    {
        $manager = $this->makeUserWithRole('project_manager', 'MBL');
        $own = $this->createPurchaseRequest(['project_code' => 'MBL', 'doc_num' => 700101]);
        $other = $this->createPurchaseRequest(['project_code' => '022C', 'doc_num' => 700102]);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($manager)->get('/procurement/purchase-requests')->assertOk()
        );
        $this->assertSame($own->id, $page['props']['purchaseRequests']['data'][0]['id']);

        $this->actingAsProject($manager)
            ->get('/procurement/purchase-requests/'.$other->id)
            ->assertForbidden();
    }

    public function test_daily_pr_lists_only_documents_created_on_selected_date(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $onDay = $this->createPurchaseRequest([
            'create_date' => '2026-09-10 09:30:00',
            'doc_num' => 800001,
        ]);
        $this->createPurchaseRequest([
            'create_date' => '2026-09-09 15:00:00',
            'doc_num' => 800002,
        ]);

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($admin)->get('/procurement/daily-pr?date=2026-09-10')->assertOk()
        );
        $this->assertSame('Procurement/DailyPr/Index', $page['component']);
        $this->assertCount(1, $page['props']['purchaseRequests']);
        $this->assertSame($onDay->id, $page['props']['purchaseRequests'][0]['id']);
    }

    public function test_daily_pr_csv_export_matches_table_rows(): void
    {
        $user = $this->makeFinanceDirector();
        $pr = $this->createPurchaseRequest([
            'create_date' => '2026-09-12 08:00:00',
            'doc_num' => 810001,
            'doc_date' => '2026-09-12',
            'department_name' => 'Plant',
            'project_code' => 'MBL',
            'requester' => 'Tester',
            'line_count' => 2,
            'total_amount' => '1500.00',
            'pr_status' => 'O',
        ]);

        $response = $this->actingAs($user)
            ->get('/procurement/daily-pr/export?date=2026-09-12');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Doc Num', $content);
        $this->assertStringContainsString('810001', $content);
        $this->assertStringContainsString('Tester', $content);
        $this->assertStringContainsString('1500.00', $content);
        $this->assertSame(2, count(array_filter(explode("\n", trim($content)))));
    }

    public function test_register_sync_remains_idempotent_with_enriched_columns(): void
    {
        $rows = collect([
            $this->syncRow(sapDocEntry: 4101, lineNum: 0, visOrder: 0),
            $this->syncRow(sapDocEntry: 4101, lineNum: 1, visOrder: 1, qty: 2, unitPrice: 500),
        ]);

        $sync = app(PurchaseRequestRegisterSync::class);
        $sync->sync($rows);
        $sync->sync($rows);

        $this->assertSame(1, SapPurchaseRequest::query()->count());
        $this->assertSame(2, SapPurchaseRequestLine::query()->count());

        $request = SapPurchaseRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame(2, $request->line_count);
        $this->assertSame('1750.00', $request->total_amount);
        $this->assertSame('2026-09-15', $request->required_date?->format('Y-m-d'));
        $this->assertSame('O', $request->pr_status);
        $this->assertSame('DT-01', $request->unit_no);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $lineOverrides
     */
    private function createPurchaseRequest(array $overrides = [], array $lineOverrides = []): SapPurchaseRequest
    {
        static $sequence = 7100;

        $sequence++;
        $sapDocEntry = $overrides['sap_doc_entry'] ?? $sequence;

        $pr = SapPurchaseRequest::create(array_merge([
            'sap_doc_entry' => $sapDocEntry,
            'doc_num' => 600000 + $sequence,
            'doc_date' => '2026-03-10',
            'create_date' => '2026-03-10 10:00:00',
            'pr_type' => 'I',
            'department_code' => '4',
            'department_name' => 'Plant',
            'requester' => 'Requester Test',
            'mr_no' => 'MR-TEST',
            'required_date' => '2026-03-15',
            'remarks' => 'Test remarks',
            'pr_status' => 'O',
            'closed_status' => null,
            'line_count' => 1,
            'total_amount' => '750.00',
            'project_code' => 'MBL',
            'synced_at' => now(),
        ], $overrides));

        $this->createLine($pr, $lineOverrides);

        return $pr;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLine(SapPurchaseRequest $pr, array $overrides = []): SapPurchaseRequestLine
    {
        return SapPurchaseRequestLine::create(array_merge([
            'sap_purchase_request_id' => $pr->id,
            'sap_doc_entry' => $pr->sap_doc_entry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'ITEM-PR',
            'description' => 'Line item',
            'qty' => '1.00',
            'uom' => 'PCS',
            'unit_price' => '750.00',
            'line_amount' => '750.00',
            'line_vendor_code' => 'V001',
        ], $overrides));
    }

    /**
     * @return array{component: string, props: array<string, mixed>, url: string, version: string}
     */
    private function inertiaPageFrom(TestResponse $response): array
    {
        $content = $response->getContent();
        PHPUnit::assertNotFalse(
            preg_match(
                '/<script data-page="app" type="application\/json">(.+?)<\/script>/',
                $content,
                $matches
            ),
            'Not a valid Inertia response.'
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        PHPUnit::assertIsArray($page);
        PHPUnit::assertArrayHasKey('component', $page);
        PHPUnit::assertArrayHasKey('props', $page);

        return $page;
    }

    private function syncRow(
        int $sapDocEntry,
        int $lineNum,
        int $visOrder,
        float $qty = 1,
        float $unitPrice = 750,
    ): object {
        return (object) [
            'sap_doc_entry' => $sapDocEntry,
            'line_num' => $lineNum,
            'vis_order' => $visOrder,
            'doc_num' => 9000 + $sapDocEntry,
            'doc_date' => '2026-09-02',
            'create_date' => '2026-09-02 09:00:00',
            'pr_type' => 'I',
            'department_code' => '4',
            'department_name' => 'Plant',
            'requester' => 'John Planner',
            'mr_no' => 'MR-55',
            'required_date' => '2026-09-15',
            'remarks' => 'Sync remarks',
            'pr_status' => 'O',
            'closed_status' => null,
            'pr_rev_no' => null,
            'unit_no' => 'DT-01',
            'hours_meter' => 1200,
            'project_code' => '021C',
            'item_code' => 'PN-1',
            'description' => 'Seal kit',
            'qty' => $qty,
            'uom' => 'SET',
            'unit_price' => $unitPrice,
            'line_amount' => $qty * $unitPrice,
            'line_vendor_code' => 'V002',
        ];
    }
}
