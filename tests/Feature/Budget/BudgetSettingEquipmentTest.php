<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetPeriod;
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

    public function test_setting_does_not_load_equipment_list(): void
    {
        \App\Models\ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'MBL',
            'is_active' => true,
        ]);

        $finance = $this->makeFinanceDirector();

        $this->actingAs($finance)
            ->withoutVite()
            ->get('/budget/setting?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Setting', false)
                ->missing('equipment')
                ->missing('stale')
                ->has('existingAllocation')
            );
    }

    public function test_setting_returns_existing_allocation_for_project_and_month(): void
    {
        \App\Models\ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'MBL',
            'is_active' => true,
        ]);

        $finance = $this->makeFinanceDirector();
        $periodMonth = now()->startOfMonth();

        $period = BudgetPeriod::factory()->create([
            'project_code' => 'MBL',
            'period_month' => $periodMonth,
            'created_by' => $finance->id,
            'status' => 'open',
        ]);

        app(\App\Services\Budget\BudgetEngine::class)->createAllocation($period, [
            'allocated_amount' => '12500000.00',
            'tolerance_pct' => '12.00',
        ], $finance);

        $this->actingAs($finance)
            ->withoutVite()
            ->get('/budget/setting?project_code=MBL&period_month='.$periodMonth->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('existingAllocation.allocated_amount', '12500000.00')
                ->where('existingAllocation.tolerance_pct', '12.00')
            );
    }

    public function test_store_creates_single_global_allocation_for_new_period(): void
    {
        $finance = $this->makeFinanceDirector();

        $response = $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocated_amount' => 12000000,
                'tolerance_pct' => 10,
            ]);

        $response->assertRedirect(route('budget.index', ['project_code' => 'MBL']));

        $this->assertDatabaseHas('budget_allocations', [
            'equipment_id' => null,
            'unit_code_cache' => null,
            'plant_type_cache' => null,
            'allocated_amount' => '12000000.00',
        ]);

        $period = BudgetPeriod::query()->where('project_code', 'MBL')->first();
        $this->assertNotNull($period);
        $this->assertCount(1, $period->allocations);
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
                'allocated_amount' => 8000000,
                'tolerance_pct' => 10,
            ])
            ->assertRedirect(route('budget.index', ['project_code' => 'MBL']));

        $period->refresh();
        $this->assertCount(1, $period->allocations);
        $this->assertSame('8000000.00', (string) $period->allocations->first()->allocated_amount);
    }

    public function test_store_rejects_empty_or_non_numeric_allocated_amount(): void
    {
        $finance = $this->makeFinanceDirector();

        $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'tolerance_pct' => 10,
            ])
            ->assertInvalid(['allocated_amount' => 'Total anggaran wajib diisi.']);

        $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocated_amount' => 'bukan-angka',
                'tolerance_pct' => 10,
            ])
            ->assertInvalid(['allocated_amount' => 'Total anggaran harus berupa angka.']);
    }

    public function test_store_rejects_tolerance_outside_zero_to_hundred(): void
    {
        $finance = $this->makeFinanceDirector();

        $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocated_amount' => 1000000,
                'tolerance_pct' => 101,
            ])
            ->assertInvalid(['tolerance_pct' => 'Toleransi maksimal 100%.']);

        $this->actingAs($finance)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOf('month')->toDateString(),
                'allocated_amount' => 1000000,
                'tolerance_pct' => -1,
            ])
            ->assertInvalid(['tolerance_pct' => 'Toleransi minimal 0%.']);
    }

    public function test_budget_index_does_not_expose_equipment_on_setting_route(): void
    {
        $finance = $this->makeFinanceDirector();

        $this->actingAs($finance)
            ->withoutVite()
            ->get('/budget?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Index', false)
                ->missing('equipment')
            );
    }
}
