<?php

namespace Tests\Feature;

use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Models\RequestApproval;
use App\Services\Arkfleet\EquipmentCache;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PlantRequestDraftEditNavTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function mockEquipmentList(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')
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
    }

    public function test_requester_can_open_edit_page_for_own_draft(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'sap_mr_id' => 0,
            'estimated_total' => '100000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        $this->mockEquipmentList();

        $this->actingAsProject($planner)
            ->get(route('plant-requests.edit', $plantRequest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PlantRequest/Edit', false)
                ->has('request')
                ->where('request.id', $plantRequest->id)
                ->has('request.lines', 1)
            );
    }

    public function test_other_planner_in_same_project_gets_forbidden_on_edit(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $owner = $this->makeUserWithRole('planner');
        $other = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $owner->id,
            'status' => 'draft',
            'sap_mr_id' => 9001,
        ]);

        $this->mockEquipmentList();

        $this->actingAsProject($other)
            ->get(route('plant-requests.edit', $plantRequest))
            ->assertForbidden();
    }

    public function test_put_updates_sap_mr_id_lines_and_estimated_total(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'sap_mr_id' => 0,
            'estimated_total' => '100000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $plantRequest->id,
            'part_number' => 'OLD-PN',
            'qty' => 1,
            'unit_price_est' => '100000.00',
        ]);

        $this->actingAsProject($planner)
            ->put(route('plant-requests.update', $plantRequest), [
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'sap_mr_id' => 88001,
                'lines' => [
                    [
                        'part_number' => 'NEW-PN',
                        'material_name' => 'Updated Part',
                        'uom' => 'EA',
                        'qty' => 2,
                        'unit_price_est' => 75000,
                        'price_source' => 'manual',
                    ],
                ],
            ])
            ->assertRedirect(route('plant-requests.show', $plantRequest));

        $plantRequest->refresh();
        $this->assertSame(88001, $plantRequest->sap_mr_id);
        $this->assertSame('150000.00', $plantRequest->estimated_total);

        $this->assertDatabaseHas('plant_request_lines', [
            'plant_request_id' => $plantRequest->id,
            'part_number' => 'NEW-PN',
        ]);
        $this->assertDatabaseMissing('plant_request_lines', [
            'plant_request_id' => $plantRequest->id,
            'part_number' => 'OLD-PN',
        ]);
    }

    public function test_put_on_non_draft_returns_forbidden(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'pending_pm',
            'sap_mr_id' => 9002,
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        $this->actingAsProject($planner)
            ->put(route('plant-requests.update', $plantRequest), [
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'sap_mr_id' => 9003,
                'lines' => [
                    [
                        'part_number' => 'PN-X',
                        'material_name' => 'Part',
                        'uom' => 'EA',
                        'qty' => 1,
                        'unit_price_est' => 100000,
                        'price_source' => 'manual',
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_draft_with_zero_mr_id_can_be_submitted_after_edit(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, amount: '10000000.00');
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'sap_mr_id' => 0,
            'estimated_total' => '500000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $plantRequest->id,
            'unit_price_est' => '500000.00',
        ]);

        $this->actingAsProject($planner)
            ->put(route('plant-requests.update', $plantRequest), [
                'budget_allocation_id' => $allocation->id,
                'equipment_id' => 42,
                'unit_code_cache' => 'E-042',
                'sap_mr_id' => 99001,
                'lines' => [
                    [
                        'part_number' => 'PN-SUB',
                        'material_name' => 'Submit Part',
                        'uom' => 'EA',
                        'qty' => 1,
                        'unit_price_est' => 500000,
                        'price_source' => 'manual',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/submit")
            ->assertRedirect(route('plant-requests.show', $plantRequest));

        $plantRequest->refresh();
        $this->assertSame('pending_pm', $plantRequest->status);
    }

    public function test_shared_nav_props_for_approver_and_sap_dashboard(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $projectManager = $this->makeUserWithRole('project_manager');
        $itManager = $this->makeUserWithRole('it_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'pending_pm',
            'sap_mr_id' => 12000,
        ]);

        RequestApproval::factory()->create([
            'approvable_type' => PlantRequest::class,
            'approvable_id' => $plantRequest->id,
            'step_order' => 1,
            'required_role' => 'project_manager',
            'decision' => 'pending',
        ]);

        $this->actingAsProject($projectManager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('nav.isApprover', true)
                ->where('nav.pendingApprovals', 1)
            );

        $this->actingAsProject($planner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('nav.isApprover', false)
                ->where('nav.viewSapDashboard', false)
            );

        $this->actingAsProject($itManager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('nav.viewSapDashboard', true)
            );
    }
}
