<?php

namespace Tests\Feature\PlantRequest;

use App\Models\DmbdEntry;
use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Services\Arkfleet\EquipmentCache;
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

    public function test_create_returns_equipment_and_project_budget_props(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, 'MBL', '5000000.00', 42, 'E-042');
        $planner = $this->makeUserWithRole('planner');

        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')
                ->once()
                ->with(['project_code' => 'MBL'])
                ->andReturn([
                    'data' => [
                        [
                            'id' => 42,
                            'unit_code' => 'E-042',
                            'description' => 'Excavator PC200',
                            'plant_type' => 'EXCAVATOR',
                            'project_code' => 'MBL',
                            'unitstatus' => 'ACTIVE',
                            'is_active' => true,
                        ],
                    ],
                    'stale' => false,
                ]);
        });

        $this->actingAsProject($planner)
            ->get('/plant-requests/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PlantRequest/Create', false)
                ->where('projectCode', 'MBL')
                ->has('equipment', 1)
                ->where('equipment.0.unit_code', 'E-042')
                ->where('projectBudget.allocation_id', $allocation->id)
                ->where('projectBudget.pagu', '5000000.00')
            );
    }

    public function test_estimate_part_returns_sap_price_for_known_part(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')
                ->once()
                ->with('PN-EST')
                ->andReturn([
                    'price' => '99000.00',
                    'currency' => 'IDR',
                    'source' => 'item_master',
                    'reference' => 'Harga beli terakhir (item master SAP)',
                ]);
        });

        $this->actingAsProject($planner)
            ->postJson('/plant-requests/estimate-part', ['part_number' => 'PN-EST'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'unit_price' => '99000.00',
                'source' => 'sap_price',
            ]);
    }

    public function test_estimate_part_returns_ok_false_for_empty_part_number(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->postJson('/plant-requests/estimate-part', ['part_number' => ''])
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
            ]);
    }

    public function test_store_auto_assigns_project_allocation_without_user_selection(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, 'MBL');
        $planner = $this->makeUserWithRole('planner', 'MBL');

        $this->actingAsProject($planner, 'MBL')
            ->post('/plant-requests', [
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'sap_mr_id' => 5001,
                'lines' => [
                    [
                        'part_number' => 'PN-001',
                        'material_name' => 'Filter',
                        'uom' => 'EA',
                        'qty' => 1,
                        'unit_price_est' => 100000,
                        'price_source' => 'manual',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plant_requests', [
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
        ]);
    }

    public function test_store_fails_when_project_has_no_budget_period(): void
    {
        $planner = $this->makeUserWithRole('planner', 'MBL');

        $this->actingAsProject($planner, 'MBL')
            ->post('/plant-requests', [
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'sap_mr_id' => 5001,
                'lines' => [
                    [
                        'part_number' => 'PN-001',
                        'material_name' => 'Filter',
                        'uom' => 'EA',
                        'qty' => 1,
                        'unit_price_est' => 100000,
                        'price_source' => 'manual',
                    ],
                ],
            ])
            ->assertSessionHasErrors(['equipment_id']);
    }

    public function test_planner_can_create_draft_plant_request(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $response = $this->actingAsProject($planner)
            ->post('/plant-requests', [
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

    public function test_pricing_uses_pmb_historical_price_when_sap_has_no_data(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $buyer = $this->makeUserWithRole('buyer');
        $priorRequest = PlantRequest::factory()->create([
            'status' => 'po_created',
            'requested_by' => $buyer->id,
            'budget_allocation_id' => $allocation->id,
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $priorRequest->id,
            'part_number' => 'PN-TAB',
            'unit_price_est' => '275000.00',
            'price_source' => 'tabulation_bid',
        ]);

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')->andReturn(null);
        });

        $estimator = app(PricingEstimator::class);
        $result = $estimator->estimate('PN-TAB');

        $this->assertSame('275000.00', $result['unit_price']);
        $this->assertSame('tabulation_bid', $result['source']);
    }

    public function test_pricing_falls_back_to_sap_price(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getItemPurchasePrice')
                ->once()
                ->with('PN-SAP')
                ->andReturn([
                    'price' => '125000.50',
                    'currency' => 'IDR',
                    'source' => 'po',
                    'reference' => 'PO 9001 · 2026-07-01',
                ]);
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
            $mock->shouldReceive('getItemPurchasePrice')
                ->andReturn([
                    'price' => '100000.00',
                    'currency' => 'IDR',
                    'source' => 'item_master',
                    'reference' => 'Harga beli terakhir (item master SAP)',
                ]);
        });

        $this->actingAsProject($planner)
            ->post('/plant-requests', [
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
