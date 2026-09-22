<?php

namespace Tests\Feature\Pricing;

use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
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

    public function test_two_different_part_numbers_get_different_sap_prices(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')
                ->with('PART-A')
                ->andReturn([
                    'price' => '100000.00',
                    'currency' => 'IDR',
                    'source' => 'po',
                    'reference' => 'PO 111 · 2026-08-01',
                ]);
            $mock->shouldReceive('getItemPurchasePrice')
                ->with('PART-B')
                ->andReturn([
                    'price' => '250000.00',
                    'currency' => 'IDR',
                    'source' => 'po',
                    'reference' => 'PO 222 · 2026-08-05',
                ]);
        });

        $resultA = app(PricingEstimator::class)->estimate('PART-A');
        $resultB = app(PricingEstimator::class)->estimate('PART-B');

        $this->assertSame('100000.00', $resultA['unit_price']);
        $this->assertSame('250000.00', $resultB['unit_price']);
        $this->assertNotSame($resultA['unit_price'], $resultB['unit_price']);
    }

    public function test_latest_po_price_in_idr_is_used_with_po_reference(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')
                ->with('PART-PO')
                ->andReturn([
                    'price' => '74253500.00',
                    'currency' => 'IDR',
                    'source' => 'po',
                    'reference' => 'PO 12345 · 2026-08-12',
                ]);
        });

        $result = app(PricingEstimator::class)->estimate('PART-PO');

        $this->assertSame('74253500.00', $result['unit_price']);
        $this->assertSame('sap_price', $result['source']);
        $this->assertStringContainsString('PO 12345', $result['reference']);
    }

    public function test_uses_pmb_historical_price_for_same_part_number_when_sap_has_no_data(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $request = PlantRequest::factory()->create([
            'status' => 'approved',
            'requested_by' => $buyer->id,
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $request->id,
            'part_number' => 'PART-HIST',
            'unit_price_est' => '325000.00',
            'price_source' => 'tabulation_bid',
        ]);

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')->andReturn(null);
        });

        $result = app(PricingEstimator::class)->estimate('PART-HIST');
        $otherResult = app(PricingEstimator::class)->estimate('PART-OTHER');

        $this->assertSame('325000.00', $result['unit_price']);
        $this->assertSame('tabulation_bid', $result['source']);
        $this->assertStringContainsString($request->request_no, $result['reference']);

        $this->assertSame('0.00', $otherResult['unit_price']);
        $this->assertSame('none', $otherResult['source']);
    }

    public function test_cancelled_and_rejected_requests_are_excluded_from_historical_price(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $allocation = $this->makeAllocation($this->makeFinanceDirector());

        foreach (['cancelled', 'rejected'] as $status) {
            $request = PlantRequest::factory()->create([
                'status' => $status,
                'requested_by' => $buyer->id,
                'budget_allocation_id' => $allocation->id,
            ]);
            PlantRequestLine::factory()->create([
                'plant_request_id' => $request->id,
                'part_number' => 'PART-VOID',
                'unit_price_est' => '999999.00',
                'price_source' => 'manual',
            ]);
        }

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')->andReturn(null);
        });

        $result = app(PricingEstimator::class)->estimate('PART-VOID');

        $this->assertSame('0.00', $result['unit_price']);
        $this->assertSame('none', $result['source']);
    }

    public function test_sap_connection_failure_does_not_throw_and_falls_back_to_none(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')
                ->andThrow(new \RuntimeException('connection unavailable'));
        });

        $result = app(PricingEstimator::class)->estimate('PART-DOWN');

        $this->assertSame('0.00', $result['unit_price']);
        $this->assertSame('none', $result['source']);
    }
}
