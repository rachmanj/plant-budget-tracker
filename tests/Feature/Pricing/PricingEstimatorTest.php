<?php

namespace Tests\Feature\Pricing;

use App\Models\TabulationBid;
use App\Models\TabulationBidAward;
use App\Models\TabulationBidVendor;
use App\Models\User;
use App\Services\Pricing\PricingEstimator;
use App\Services\Sap\SapReadRepository;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PricingEstimatorTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Cache::flush();
    }

    public function test_cascade_prefers_tabulation_bid_over_sap(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $bid = TabulationBid::factory()->create([
            'status' => 'po_created',
            'created_by' => $buyer->id,
        ]);
        $vendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $bid->id,
            'price' => '450000.00',
        ]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $bid->id,
            'tabulation_bid_vendor_id' => $vendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now()->subDay(),
        ]);

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getPriceList')->never();
        });

        $result = app(PricingEstimator::class)->estimate('PART-ABC');

        $this->assertSame('450000.00', $result['unit_price']);
        $this->assertSame('tabulation_bid', $result['source']);
    }

    public function test_cascade_uses_sap_when_no_tabulation_award(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getPriceList')
                ->once()
                ->with(['PART-SAP'])
                ->andReturn(collect([(object) ['Price' => 88000]]));
        });

        $result = app(PricingEstimator::class)->estimate('PART-SAP');

        $this->assertSame('88000.00', $result['unit_price']);
        $this->assertSame('sap_price', $result['source']);
    }

    public function test_cascade_returns_none_when_no_sources(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getPriceList')
                ->once()
                ->andReturn(collect());
        });

        $result = app(PricingEstimator::class)->estimate('PART-MISSING');

        $this->assertSame('0.00', $result['unit_price']);
        $this->assertSame('none', $result['source']);
    }

    public function test_latest_tabulation_award_wins_over_older_award(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $olderBid = TabulationBid::factory()->create([
            'status' => 'closed',
            'created_by' => $buyer->id,
        ]);
        $olderVendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $olderBid->id,
            'price' => '100000.00',
        ]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $olderBid->id,
            'tabulation_bid_vendor_id' => $olderVendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now()->subWeek(),
        ]);

        $newerBid = TabulationBid::factory()->create([
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
        ]);
        $newerVendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $newerBid->id,
            'price' => '250000.00',
        ]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $newerBid->id,
            'tabulation_bid_vendor_id' => $newerVendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now(),
        ]);

        $result = app(PricingEstimator::class)->estimate('ANY-PART');

        $this->assertSame('250000.00', $result['unit_price']);
        $this->assertSame('tabulation_bid', $result['source']);
    }
}
