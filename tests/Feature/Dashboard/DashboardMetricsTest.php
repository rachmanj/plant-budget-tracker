<?php

namespace Tests\Feature\Dashboard;

use App\Models\BudgetPeriod;
use App\Models\PlantRequest;
use App\Services\Budget\BudgetEngine;
use App\Services\Dashboard\DashboardMetrics;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_budget_metrics_reflect_allocated_used_and_remaining(): void
    {
        $finance = $this->makeFinanceDirector();
        $planner = $this->makeUserWithRole('planner', 'MBL');

        $period = BudgetPeriod::factory()->create([
            'project_code' => 'MBL',
            'created_by' => $finance->id,
            'status' => 'open',
            'period_month' => now()->startOfMonth(),
        ]);

        $engine = app(BudgetEngine::class);

        $allocationA = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
            'equipment_id' => 1,
            'unit_code_cache' => 'E-001',
        ], $finance);

        $allocationB = $engine->createAllocation($period, [
            'allocated_amount' => '5000000.00',
            'equipment_id' => 2,
            'unit_code_cache' => 'E-002',
        ], $finance);

        $engine->postCommitment($allocationA, '4000000.00', 'plant_request', 1001, $planner, 'Commit A');
        $engine->postCommitment($allocationB, '1500000.00', 'plant_request', 2001, $planner, 'Commit B');

        $metrics = app(DashboardMetrics::class)->for($planner, 'MBL');

        $this->assertSame('15000000.00', $metrics['budget']['allocated']);
        $this->assertSame('0.00', $metrics['budget']['carryForward']);
        $this->assertSame('5500000.00', $metrics['budget']['used']);
        $this->assertSame('9500000.00', $metrics['budget']['remaining']);
        $this->assertEqualsWithDelta(36.67, $metrics['budget']['usedPct'], 0.01);
        $this->assertSame(2, $metrics['budget']['allocationCount']);
        $this->assertTrue($metrics['budget']['available']);
        $this->assertTrue($metrics['period']['exists']);
    }

    public function test_budget_metrics_used_and_used_pct_match_allocation_variance_and_utilization_definition(): void
    {
        $finance = $this->makeFinanceDirector();
        $planner = $this->makeUserWithRole('planner', 'MBL');

        $period = BudgetPeriod::factory()->create([
            'project_code' => 'MBL',
            'created_by' => $finance->id,
            'status' => 'open',
            'period_month' => now()->startOfMonth(),
        ]);

        $engine = app(BudgetEngine::class);

        $allocationA = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
            'equipment_id' => 1,
            'unit_code_cache' => 'E-001',
        ], $finance);

        $allocationB = $engine->createAllocation($period, [
            'allocated_amount' => '5000000.00',
            'equipment_id' => 2,
            'unit_code_cache' => 'E-002',
        ], $finance);

        // Allocation A: partial commitment followed by a GRPO actual.
        $engine->postCommitment($allocationA, '4000000.00', 'plant_request', 1001, $planner, 'Commit A');
        $engine->postActual($allocationA, '3800000.00', 5001, $finance, 'GRPO A');

        // Allocation B: outstanding commitment only, no actual yet.
        $engine->postCommitment($allocationB, '1000000.00', 'plant_request', 2001, $planner, 'Commit B');

        $allocationA->refresh();
        $allocationB->refresh();

        // Ground truth: BudgetAllocation::variance / utilization_pct accessors.
        $expectedBase = bcadd(
            bcadd((string) $allocationA->allocated_amount, (string) $allocationA->carry_forward_in, 2),
            bcadd((string) $allocationB->allocated_amount, (string) $allocationB->carry_forward_in, 2),
            2
        );
        $expectedVarianceSum = bcadd((string) $allocationA->variance, (string) $allocationB->variance, 2);
        $expectedUsed = bcsub($expectedBase, $expectedVarianceSum, 2);
        $expectedUsedPct = round(((float) $expectedUsed / (float) $expectedBase) * 100, 2);

        $metrics = app(DashboardMetrics::class)->for($planner, 'MBL');

        $this->assertSame($expectedUsed, $metrics['budget']['used']);
        $this->assertSame($expectedVarianceSum, $metrics['budget']['remaining']);
        $this->assertEqualsWithDelta($expectedUsedPct, $metrics['budget']['usedPct'], 0.01);
    }

    public function test_budget_metrics_include_carry_forward_in_pagu_and_remaining(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, 'MBL', '10000000.00', 1, 'E-001');

        $allocation->update(['carry_forward_in' => '1000000.00']);

        $metrics = app(DashboardMetrics::class)->for($finance, 'MBL');

        $this->assertSame('10000000.00', $metrics['budget']['allocated']);
        $this->assertSame('1000000.00', $metrics['budget']['carryForward']);
        $this->assertSame('0.00', $metrics['budget']['used']);
        $this->assertSame('11000000.00', $metrics['budget']['remaining']);
    }

    public function test_budget_metrics_used_is_not_affected_by_overbudget_ledger_entries(): void
    {
        $finance = $this->makeFinanceDirector();
        $planner = $this->makeUserWithRole('planner', 'MBL');
        $allocation = $this->makeAllocation($finance, 'MBL', '10000000.00', 1, 'E-001');

        $engine = app(BudgetEngine::class);
        $engine->postCommitment($allocation, '4000000.00', 'plant_request', 1001, $planner, 'Commit A');

        $metricsBefore = app(DashboardMetrics::class)->for($finance, 'MBL');

        $engine->postOverbudget($allocation, '2000000.00', 9001, $finance, 'Overbudget approval');

        $metricsAfter = app(DashboardMetrics::class)->for($finance, 'MBL');

        $this->assertSame('4000000.00', $metricsBefore['budget']['used']);
        $this->assertSame($metricsBefore['budget']['used'], $metricsAfter['budget']['used']);
        $this->assertSame($metricsBefore['budget']['allocated'], $metricsAfter['budget']['allocated']);
    }

    public function test_budget_metrics_are_scoped_to_selected_project_only(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance, 'MBL', '10000000.00', 1, 'E-001');
        $this->makeAllocation($finance, 'SML', '9000000.00', 2, 'E-002');

        $metrics = app(DashboardMetrics::class)->for($finance, 'MBL');

        $this->assertSame('10000000.00', $metrics['budget']['allocated']);
        $this->assertSame(1, $metrics['budget']['allocationCount']);
    }

    public function test_budget_metrics_report_missing_period_without_failing(): void
    {
        $finance = $this->makeFinanceDirector();

        $metrics = app(DashboardMetrics::class)->for($finance, 'MBL');

        $this->assertSame('0.00', $metrics['budget']['allocated']);
        $this->assertSame('0.00', $metrics['budget']['carryForward']);
        $this->assertSame('0.00', $metrics['budget']['used']);
        $this->assertTrue($metrics['budget']['available']);
        $this->assertFalse($metrics['period']['exists']);
    }

    public function test_awaiting_my_decision_only_counts_matching_approver_role(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, 'MBL', '10000000.00');
        $planner = $this->makeUserWithRole('planner', 'MBL');
        $projectManager = $this->makeUserWithRole('project_manager', 'MBL');
        $plantManager = $this->makeUserWithRole('plant_manager', 'MBL');
        $financeDirector = $this->makeFinanceDirector();

        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'pending_pm',
        ]);
        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'pending_plant_mgr',
        ]);
        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'rejected',
        ]);

        setPermissionsTeamId('MBL');
        $pmMetrics = app(DashboardMetrics::class)->for($projectManager, 'MBL');
        $this->assertSame(1, $pmMetrics['requests']['awaitingMyDecision']);

        setPermissionsTeamId('MBL');
        $plantMgrMetrics = app(DashboardMetrics::class)->for($plantManager, 'MBL');
        $this->assertSame(1, $plantMgrMetrics['requests']['awaitingMyDecision']);

        setPermissionsTeamId('');
        $financeDirectorMetrics = app(DashboardMetrics::class)->for($financeDirector, 'MBL');
        $this->assertSame(0, $financeDirectorMetrics['requests']['awaitingMyDecision']);

        $this->assertSame(1, $pmMetrics['requests']['draft']);
        $this->assertSame(1, $pmMetrics['requests']['rejected']);
        $this->assertSame(2, $pmMetrics['requests']['waitingApproval']);
    }

    public function test_request_metrics_are_scoped_to_selected_project_only(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocationMbl = $this->makeAllocation($finance, 'MBL', '10000000.00', 1, 'E-001');
        $allocationSml = $this->makeAllocation($finance, 'SML', '9000000.00', 2, 'E-002');
        $planner = $this->makeUserWithRole('planner', 'MBL');

        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocationMbl->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocationSml->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);

        $metrics = app(DashboardMetrics::class)->for($planner, 'MBL');

        $this->assertSame(1, $metrics['requests']['draft']);
    }

    public function test_returns_zeroed_blocks_when_no_project_context(): void
    {
        $itManager = $this->makeFinanceDirector();

        $metrics = app(DashboardMetrics::class)->for($itManager, null);

        $this->assertSame('0.00', $metrics['budget']['allocated']);
        $this->assertSame(0, $metrics['requests']['draft']);
        $this->assertSame(0, $metrics['dmbd']['rfu']);
        $this->assertFalse($metrics['dmbd']['available']);
        $this->assertNull($metrics['dmbd']['unitsActive']);
    }
}
