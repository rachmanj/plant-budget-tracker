<?php

namespace Tests\Feature\Procurement;

use App\Models\DocumentFollow;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PurchaseOrderFollowTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_follow_creates_document_follow_row(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/follow')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('document_follows', [
            'followable_type' => 'purchase_order',
            'followable_id' => $order->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_second_follow_toggle_removes_document_follow_row(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/follow')
            ->assertRedirect();

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/follow')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('document_follows', [
            'followable_type' => 'purchase_order',
            'followable_id' => $order->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_following_filter_lists_only_followed_purchase_orders(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $followed = $this->createPurchaseOrder(['doc_num' => 930001]);
        $this->createPurchaseOrder(['doc_num' => 930002]);

        DocumentFollow::create([
            'followable_type' => 'purchase_order',
            'followable_id' => $followed->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders?following=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('purchaseOrders.data', 1)
                ->where('purchaseOrders.data.0.id', $followed->id)
                ->where('purchaseOrders.data.0.isFollowed', true)
                ->where('filters.following', true)
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPurchaseOrder(array $overrides = []): SapPurchaseOrder
    {
        static $sequence = 14000;

        $sequence++;
        $sapDocEntry = $overrides['sap_doc_entry'] ?? $sequence;

        $order = SapPurchaseOrder::create(array_merge([
            'sap_doc_entry' => $sapDocEntry,
            'doc_num' => 200000 + $sequence,
            'doc_date' => '2026-03-10',
            'pr_no' => 'PR-FOLLOW',
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

        SapPurchaseOrderLine::create([
            'sap_purchase_order_id' => $order->id,
            'sap_doc_entry' => $order->sap_doc_entry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'ITEM-F',
            'description' => 'Test item',
            'qty' => '1.00',
            'uom' => 'PCS',
            'unit_price' => '500000.00',
            'item_amount' => '500000.00',
            'project_code' => $order->project_code,
        ]);

        return $order;
    }
}
