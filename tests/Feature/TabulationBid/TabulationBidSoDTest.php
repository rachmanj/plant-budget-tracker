<?php

namespace Tests\Feature\TabulationBid;

use App\Jobs\CreateSapPurchaseOrder;
use App\Models\TabulationBid;
use App\Models\TabulationBidAward;
use App\Models\TabulationBidVendor;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class TabulationBidSoDTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_buyer_cannot_create_po_even_when_they_created_the_bid(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $bid = $this->createAwardedBid($buyer);

        $this->actingAsProject($buyer)
            ->post("/tabulation-bids/{$bid->id}/create-po")
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_procurement_admin_can_create_po_when_not_the_buyer(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $bid = $this->createAwardedBid($buyer);

        $this->actingAsProject($admin)
            ->post("/tabulation-bids/{$bid->id}/create-po")
            ->assertRedirect();

        Queue::assertPushed(CreateSapPurchaseOrder::class, function ($job) use ($bid) {
            return $job->tabulationBidId === $bid->id;
        });
    }

    public function test_procurement_manager_cannot_create_po(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');
        $bid = $this->createAwardedBid($buyer);

        $this->actingAsProject($procMgr)
            ->post("/tabulation-bids/{$bid->id}/create-po")
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    private function createAwardedBid(\App\Models\User $buyer): TabulationBid
    {
        $bid = TabulationBid::factory()->create([
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
        ]);
        $vendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $bid->id,
            'price' => '1500000.00',
            'rank' => 1,
        ]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $bid->id,
            'tabulation_bid_vendor_id' => $vendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now(),
        ]);

        return $bid;
    }
}
