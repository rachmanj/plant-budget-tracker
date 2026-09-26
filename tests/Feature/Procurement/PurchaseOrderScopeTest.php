<?php

namespace Tests\Feature\Procurement;

use App\Models\ProjectCache;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PurchaseOrderScopeTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
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

    public function test_scoped_user_index_lists_only_their_project_documents(): void
    {
        $manager = $this->makeUserWithRole('project_manager', 'MBL');
        $own = $this->createPurchaseOrder(['project_code' => 'MBL', 'doc_num' => 700101]);
        $this->createPurchaseOrder(['project_code' => '022C', 'doc_num' => 700102]);

        $this->actingAsProject($manager)
            ->get('/procurement/purchase-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $own->id)
                ->where('projectScope', 'MBL')
                ->has('projects', 1)
                ->where('projects.0.project_code', 'MBL')
            );
    }

    public function test_scoped_user_cannot_bypass_scope_via_project_code_query_param(): void
    {
        $manager = $this->makeUserWithRole('project_manager', 'MBL');
        $own = $this->createPurchaseOrder(['project_code' => 'MBL', 'doc_num' => 700201]);
        $this->createPurchaseOrder(['project_code' => '022C', 'doc_num' => 700202]);

        $this->actingAsProject($manager)
            ->get('/procurement/purchase-orders?project_code=022C')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $own->id)
                ->where('purchaseOrders.data.0.project_code', 'MBL')
                ->where('filters.project_code', 'MBL')
            );
    }

    public function test_scoped_user_summary_reflects_only_their_project(): void
    {
        $manager = $this->makeUserWithRole('project_manager', 'MBL');
        $this->createPurchaseOrder(['project_code' => 'MBL', 'total_amount' => '1000000.00']);
        $this->createPurchaseOrder(['project_code' => 'MBL', 'total_amount' => '500000.00']);
        $this->createPurchaseOrder(['project_code' => '022C', 'total_amount' => '9000000.00']);

        $this->actingAsProject($manager)
            ->get('/procurement/purchase-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.document_count', 2)
                ->where('summary.total_amount', '1500000.00')
            );
    }

    public function test_scoped_user_gets_forbidden_when_viewing_other_project_detail(): void
    {
        $manager = $this->makeUserWithRole('project_manager', 'MBL');
        $otherProjectOrder = $this->createPurchaseOrder(['project_code' => '022C']);

        $this->actingAsProject($manager)
            ->get('/procurement/purchase-orders/'.$otherProjectOrder->id)
            ->assertForbidden();
    }

    public function test_scoped_user_can_view_own_project_detail(): void
    {
        $manager = $this->makeUserWithRole('project_manager', 'MBL');
        $order = $this->createPurchaseOrder(['project_code' => 'MBL']);

        $this->actingAsProject($manager)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show', false)
                ->where('purchaseOrder.id', $order->id)
            );
    }

    public function test_global_user_sees_all_projects_and_can_filter_by_project(): void
    {
        $admin = $this->makeGlobalUserWithRole('procurement_admin');
        $mbl = $this->createPurchaseOrder(['project_code' => 'MBL', 'doc_num' => 700301]);
        $this->createPurchaseOrder(['project_code' => '022C', 'doc_num' => 700302]);

        $this->actingAs($admin)
            ->withoutVite()
            ->get('/procurement/purchase-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 2)
                ->where('projectScope', null)
            );

        $this->actingAs($admin)
            ->withoutVite()
            ->get('/procurement/purchase-orders?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $mbl->id)
            );
    }

    private function makeGlobalUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => null,
        ]);
        setPermissionsTeamId('');
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $lineOverrides
     */
    private function createPurchaseOrder(array $overrides = [], array $lineOverrides = []): SapPurchaseOrder
    {
        static $sequence = 9100;

        $sequence++;
        $sapDocEntry = $overrides['sap_doc_entry'] ?? $sequence;

        $order = SapPurchaseOrder::create(array_merge([
            'sap_doc_entry' => $sapDocEntry,
            'doc_num' => 200000 + $sequence,
            'doc_date' => '2026-03-10',
            'pr_no' => 'PR-SCOPE',
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

        SapPurchaseOrderLine::create(array_merge([
            'sap_purchase_order_id' => $order->id,
            'sap_doc_entry' => $order->sap_doc_entry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'ITEM-SCOPE',
            'description' => 'Test item',
            'qty' => '1.00',
            'uom' => 'PCS',
            'unit_price' => '500000.00',
            'item_amount' => '500000.00',
            'project_code' => $order->project_code,
        ], $lineOverrides));

        return $order;
    }
}
