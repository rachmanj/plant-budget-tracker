<?php

namespace Tests\Feature\PlantRequest;

use App\Models\BudgetAllocation;
use App\Models\DmbdEntry;
use App\Models\PlantRequest;
use App\Models\TabulationBid;
use App\Models\TabulationBidAward;
use App\Models\TabulationBidVendor;
use App\Models\User;
use App\Services\Pricing\PricingEstimator;
use App\Services\Sap\SapReadRepository;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PlantRequestCreationTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_planner_can_create_draft_plant_request(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $response = $this->actingAsProject($planner)
            ->post('/plant-requests', [
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'sap_mr_id' => 5001,
                'lines' => [
                    [
                        'part_number' => 'PN-001',
                        'material_name' => 'Hydraulic Filter',
                        'uom' => 'EA',
                        'qty' => 2,
                        'unit_price_est' => 150000,
                        'price_source' => 'manual',
                    ],
                ],
            ]);

        $plantRequest = PlantRequest::query()->first();
        $response->assertRedirect(route('plant-requests.show', $plantRequest));

        $this->assertDatabaseHas('plant_requests', [
            'id' => $plantRequest->id,
            'status' => 'draft',
            'sap_mr_id' => 5001,
            'requested_by' => $planner->id,
            'estimated_total' => '300000.00',
        ]);

        $this->assertDatabaseHas('plant_request_lines', [
            'plant_request_id' => $plantRequest->id,
            'part_number' => 'PN-001',
            'price_source' => 'manual',
        ]);
    }

    public function test_draft_can_link_dmbd_entry(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $dmbd = DmbdEntry::factory()->create([
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'reported_by' => $planner->id,
        ]);

        $this->actingAsProject($planner)
            ->post('/plant-requests', [
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'dmbd_entry_id' => $dmbd->id,
                'sap_mr_id' => 6002,
                'lines' => [
                    [
                        'part_number' => 'PN-DMBD',
                        'material_name' => 'Seal Kit',
                        'uom' => 'EA',
                        'qty' => 1,
                        'unit_price_est' => 500000,
                        'price_source' => 'manual',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plant_requests', [
            'dmbd_entry_id' => $dmbd->id,
            'equipment_id' => 42,
        ]);
    }

    public function test_pricing_uses_tabulation_bid_award_when_available(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $bid = TabulationBid::factory()->create([
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
        ]);
        $vendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $bid->id,
            'price' => '275000.00',
            'rank' => 1,
        ]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $bid->id,
            'tabulation_bid_vendor_id' => $vendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now(),
        ]);

        $estimator = app(PricingEstimator::class);
        $result = $estimator->estimate('PN-TAB');

        $this->assertSame('275000.00', $result['unit_price']);
        $this->assertSame('tabulation_bid', $result['source']);
    }

    public function test_pricing_falls_back_to_sap_price_list(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getPriceList')
                ->once()
                ->with(['PN-SAP'])
                ->andReturn(collect([(object) ['Price' => 125000.50]]));
        });

        $estimator = app(PricingEstimator::class);
        $result = $estimator->estimate('PN-SAP');

        $this->assertSame('125000.50', $result['unit_price']);
        $this->assertSame('sap_price', $result['source']);
    }

    public function test_store_applies_auto_pricing_when_no_price_given(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getPriceList')
                ->andReturn(collect([(object) ['Price' => 100000]]));
        });

        $this->actingAsProject($planner)
            ->post('/plant-requests', [
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'sap_mr_id' => 7003,
                'lines' => [
                    [
                        'part_number' => 'PN-AUTO',
                        'material_name' => 'Bearing',
                        'uom' => 'EA',
                        'qty' => 3,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plant_request_lines', [
            'part_number' => 'PN-AUTO',
            'unit_price_est' => '100000.00',
            'price_source' => 'sap_price',
        ]);

        $this->assertDatabaseHas('plant_requests', [
            'estimated_total' => '300000.00',
        ]);
    }
}
