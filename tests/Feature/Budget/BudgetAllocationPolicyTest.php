<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAllocationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_non_finance_director_cannot_create_budget(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => 'MBL',
        ]);

        setPermissionsTeamId('MBL');
        $user->assignRole('plant_manager');

        $this->actingAs($user)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOfMonth()->toDateString(),
                'allocations' => [
                    ['allocated_amount' => 1000000],
                ],
            ])
            ->assertForbidden();
    }

    public function test_non_finance_director_cannot_revise_allocation(): void
    {
        $finance = $this->makeFinanceDirector();
        $period = BudgetPeriod::factory()->create([
            'created_by' => $finance->id,
            'status' => 'open',
        ]);

        $allocation = app(BudgetEngine::class)->createAllocation($period, [
            'allocated_amount' => '5000000.00',
        ], $finance);

        $planner = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => 'MBL',
        ]);
        setPermissionsTeamId('MBL');
        $planner->assignRole('planner');

        $this->actingAs($planner)
            ->withoutVite()
            ->patch("/budget/allocations/{$allocation->id}", [
                'allocated_amount' => 9000000,
            ])
            ->assertForbidden();
    }

    public function test_finance_director_cannot_revise_locked_period_allocation(): void
    {
        $finance = $this->makeFinanceDirector();

        $period = BudgetPeriod::factory()->locked()->create([
            'created_by' => $finance->id,
            'period_month' => now()->subMonthsNoOverflow(2)->startOfMonth(),
        ]);

        $allocation = BudgetAllocation::factory()->create([
            'budget_period_id' => $period->id,
            'allocated_amount' => '5000000.00',
            'is_editable' => false,
        ]);

        $this->actingAs($finance)
            ->withoutVite()
            ->patch("/budget/allocations/{$allocation->id}", [
                'allocated_amount' => 9000000,
            ])
            ->assertForbidden();
    }

    public function test_planner_can_view_budget_index(): void
    {
        $planner = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => 'MBL',
        ]);

        setPermissionsTeamId('MBL');
        $planner->assignRole('planner');

        $this->actingAs($planner)
            ->withoutVite()
            ->get('/budget')
            ->assertOk();
    }

    public function test_finance_director_can_revise_current_month_allocation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10'));

        $finance = $this->makeFinanceDirector();
        $period = BudgetPeriod::factory()->create([
            'created_by' => $finance->id,
            'status' => 'open',
            'period_month' => now()->startOfMonth(),
        ]);

        $allocation = app(BudgetEngine::class)->createAllocation($period, [
            'allocated_amount' => '5000000.00',
        ], $finance);

        $this->actingAs($finance)
            ->withoutVite()
            ->patch("/budget/allocations/{$allocation->id}", [
                'allocated_amount' => 7500000,
                'memo' => 'Mid-month revision',
            ])
            ->assertRedirect();

        $allocation->refresh();
        $this->assertSame('7500000.00', (string) $allocation->allocated_amount);

        Carbon::setTestNow();
    }

    private function makeFinanceDirector(): User
    {
        $user = User::factory()->create(['is_active' => true, 'project_code_scope' => null]);
        setPermissionsTeamId('');
        $user->assignRole('finance_director');

        return $user;
    }
}
