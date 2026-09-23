<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetPeriod;
use App\Services\Arkfleet\EquipmentCache;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class BudgetSettingEquipmentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesScopedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_setting_sends_equipment_only_for_selected_project_excluding_sold_and_scrap(): void
    {
        \App\Models\ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'MBL',
            'is_active' => true,
        ]);

        $finance = $this->makeFinanceDirector();

        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')
                ->once()
                ->with(['project_code' => 'MBL'])
                ->andReturn([
                    'stale' => false,
                    'data' => [
                        $this->item(['id' => 1, 'unit_no' => 'E-002', 'plant_type' => 'DIGGER', 'unitstatus' => 'ACTIVE']),
                        $this->item(['id' => 2, 'unit_no' => 'E-001', 'plant_type' => 'HAULER', 'unitstatus' => 'ACTIVE']),
                        $this->item(['id' => 3, 'unit_no' => 'E-003', 'plant_type' => 'SUPPORT', 'unitstatus' => 'SOLD']),
                        $this->item(['id' => 4, 'unit_no' => 'E-004', 'plant_type' => 'HEAVY EQUIPMENT', 'unitstatus' => 'SCRAP']),
                        $this->item(['id' => 5, 'unit_no' => 'E-005', 'plant_type' => 'HEAVY EQUIPMENT', 'unitstatus' => 'ACTIVE']),
                    ],
                ]);
        });

        $this->actingAs($finance)
            ->withoutVite()
            ->get('/budget/setting?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stale', false)
                ->where('equipment', [
                    [
                        'id' => 2,
                        'unit_code' => 'E-001',
                        'description' => 'Unit description',
                        'plant_type' => 'HAULER',
                        'unitstatus' => 'ACTIVE',
                        'plant_type_mapped' => 'HAULER',
                    ],
                    [
                        'id' => 1,
                        'unit_code' => 'E-002',
                        'description' => 'Unit description',
                        'plant_type' => 'DIGGER',
                        'unitstatus' => 'ACTIVE',
                        'plant_type_mapped' => 'DIGGER',
                    ],
                    [
                        'id' => 5,
                        'unit_code' => 'E-005',
                        'description' => 'Unit description',
                        'plant_type' => 'HEAVY EQUIPMENT',
                        'unitstatus' => 'ACTIVE',
                        'plant_type_mapped' => null,
                    ],
                ])
            );
    }

    public function test_setting_returns_stale_true_and_empty_equipment_when_arkfleet_unreachable(): void
    {
        $finance = $this->makeFinanceDirector();

        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andThrow(new \RuntimeException('ARKFLEET unreachable'));
        });

        $this->actingAs($finance)
            ->withoutVite()
            ->get('/budget/setting?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('equipment', [])
                ->where('stale', true)
            );
    }

    public function test_store_with_real_unit_persists_global_allocation_without_equipment_link(): void
    {
        $finance = $this->makeFinanceDirector();

        $response = $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocations' => [
                    [
                        'equipment_id' => 501,
                        'unit_code_cache' => 'E-501',
                        'plant_type_cache' => 'HAULER',
                        'allocated_amount' => 12000000,
                        'tolerance_pct' => 10,
                    ],
                ],
            ]);

        $response->assertRedirect(route('budget.index', ['project_code' => 'MBL']));

        $this->assertDatabaseHas('budget_allocations', [
            'equipment_id' => null,
            'unit_code_cache' => null,
            'plant_type_cache' => null,
            'allocated_amount' => '12000000.00',
        ]);
    }

    public function test_store_division_row_persists_as_global_allocation(): void
    {
        $finance = $this->makeFinanceDirector();

        $response = $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocations' => [
                    [
                        'equipment_id' => null,
                        'unit_code_cache' => null,
                        'plant_type_cache' => 'SUPPORT',
                        'allocated_amount' => 8000000,
                        'tolerance_pct' => 10,
                    ],
                ],
            ]);

        $response->assertRedirect(route('budget.index', ['project_code' => 'MBL']));

        $this->assertDatabaseHas('budget_allocations', [
            'equipment_id' => null,
            'unit_code_cache' => null,
            'plant_type_cache' => null,
            'allocated_amount' => '8000000.00',
        ]);
    }

    public function test_store_rejects_duplicate_equipment_in_same_payload(): void
    {
        $finance = $this->makeFinanceDirector();

        $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocations' => [
                    [
                        'equipment_id' => 601,
                        'unit_code_cache' => 'E-601',
                        'plant_type_cache' => 'HAULER',
                        'allocated_amount' => 1000000,
                    ],
                    [
                        'equipment_id' => 601,
                        'unit_code_cache' => 'E-601',
                        'plant_type_cache' => 'HAULER',
                        'allocated_amount' => 2000000,
                    ],
                ],
            ])
            ->assertInvalid(['allocations.1.equipment_id' => 'Unit ini dialokasikan lebih dari sekali dalam satu periode.']);
    }

    public function test_store_revises_existing_period_budget_instead_of_second_allocation_row(): void
    {
        $finance = $this->makeFinanceDirector();
        $periodMonth = now()->startOfMonth();

        $period = BudgetPeriod::factory()->create([
            'project_code' => 'MBL',
            'period_month' => $periodMonth,
            'created_by' => $finance->id,
            'status' => 'open',
        ]);

        app(\App\Services\Budget\BudgetEngine::class)->createAllocation($period, [
            'allocated_amount' => '5000000.00',
        ], $finance);

        $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => $periodMonth->toDateString(),
                'allocations' => [
                    [
                        'equipment_id' => 701,
                        'unit_code_cache' => 'E-701',
                        'plant_type_cache' => 'DIGGER',
                        'allocated_amount' => 8000000,
                    ],
                ],
            ])
            ->assertRedirect(route('budget.index', ['project_code' => 'MBL']));

        $period->refresh();
        $this->assertCount(1, $period->allocations);
        $this->assertSame('8000000.00', (string) $period->allocations->first()->allocated_amount);
    }

    public function test_store_rejects_unit_row_without_plant_type(): void
    {
        $finance = $this->makeFinanceDirector();

        $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocations' => [
                    [
                        'equipment_id' => 801,
                        'unit_code_cache' => 'E-801',
                        'allocated_amount' => 4000000,
                    ],
                ],
            ])
            ->assertInvalid(['allocations.0.plant_type_cache' => 'Tipe plant wajib dipilih untuk alokasi per unit.']);
    }

    private function item(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'unit_no' => 'E-001',
            'description' => 'Unit description',
            'plant_type' => 'DIGGER',
            'unitstatus' => 'ACTIVE',
            'project_code' => 'MBL',
        ], $overrides);
    }
}
