<?php

namespace Tests\Feature\TabulationBid;

use App\Models\TabulationBid;
use App\Models\TabulationBidVendor;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class TabulationBidCreationTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_buyer_can_create_tabulation_bid_with_two_vendors(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $response = $this->actingAsProject($buyer)
            ->post('/tabulation-bids', [
                'sap_pr_id' => 'PR-10001',
                'vendors' => [
                    [
                        'vendor_code' => 'V001',
                        'vendor_name' => 'Vendor Alpha',
                        'price' => 5000000,
                        'stock_availability' => 'ready',
                    ],
                    [
                        'vendor_code' => 'V002',
                        'vendor_name' => 'Vendor Beta',
                        'price' => 4800000,
                        'stock_availability' => 'indent',
                    ],
                ],
            ]);

        $bid = TabulationBid::query()->first();
        $response->assertRedirect(route('tabulation-bids.show', $bid));

        $this->assertDatabaseHas('tabulation_bids', [
            'sap_pr_id' => 'PR-10001',
            'status' => 'pending_proc_mgr',
            'created_by' => $buyer->id,
        ]);

        $this->assertCount(2, $bid->vendors);

        $ranks = $bid->vendors()->orderBy('rank')->pluck('price')->map(fn ($p) => (string) $p)->all();
        $this->assertSame(['4800000.00', '5000000.00'], $ranks);
    }

    public function test_buyer_can_create_tabulation_bid_with_three_vendors(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $this->actingAsProject($buyer)
            ->post('/tabulation-bids', [
                'sap_pr_id' => 'PR-10002',
                'vendors' => [
                    ['vendor_code' => 'V001', 'vendor_name' => 'A', 'price' => 3000000, 'stock_availability' => 'ready'],
                    ['vendor_code' => 'V002', 'vendor_name' => 'B', 'price' => 2900000, 'stock_availability' => 'ready'],
                    ['vendor_code' => 'V003', 'vendor_name' => 'C', 'price' => 3100000, 'stock_availability' => 'partial'],
                ],
            ])
            ->assertRedirect();

        $bid = TabulationBid::query()->where('sap_pr_id', 'PR-10002')->first();
        $this->assertCount(3, $bid->vendors);
        $this->assertSame(1, $bid->vendors()->where('vendor_code', 'V002')->value('rank'));
    }

    public function test_validation_rejects_single_vendor(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $this->actingAsProject($buyer)
            ->post('/tabulation-bids', [
                'sap_pr_id' => 'PR-10003',
                'vendors' => [
                    ['vendor_code' => 'V001', 'vendor_name' => 'Only One', 'price' => 1000000, 'stock_availability' => 'ready'],
                ],
            ])
            ->assertSessionHasErrors('vendors');

        $this->assertDatabaseCount('tabulation_bids', 0);
    }

    public function test_validation_rejects_more_than_three_vendors(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $this->actingAsProject($buyer)
            ->post('/tabulation-bids', [
                'sap_pr_id' => 'PR-10004',
                'vendors' => [
                    ['vendor_code' => 'V1', 'vendor_name' => 'A', 'price' => 100, 'stock_availability' => 'ready'],
                    ['vendor_code' => 'V2', 'vendor_name' => 'B', 'price' => 200, 'stock_availability' => 'ready'],
                    ['vendor_code' => 'V3', 'vendor_name' => 'C', 'price' => 300, 'stock_availability' => 'ready'],
                    ['vendor_code' => 'V4', 'vendor_name' => 'D', 'price' => 400, 'stock_availability' => 'ready'],
                ],
            ])
            ->assertSessionHasErrors('vendors');
    }

    public function test_non_buyer_cannot_create_tabulation_bid(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->post('/tabulation-bids', [
                'sap_pr_id' => 'PR-10005',
                'vendors' => [
                    ['vendor_code' => 'V001', 'vendor_name' => 'A', 'price' => 1000, 'stock_availability' => 'ready'],
                    ['vendor_code' => 'V002', 'vendor_name' => 'B', 'price' => 2000, 'stock_availability' => 'ready'],
                ],
            ])
            ->assertForbidden();
    }
}
