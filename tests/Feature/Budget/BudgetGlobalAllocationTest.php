<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetAllocation;
use App\Models\BudgetLedger;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BudgetGlobalAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_consolidation_migration_merges_allocations_per_period_and_repoints_ledgers(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2]);

        $user = User::factory()->create(['is_active' => true]);
        $now = now();

        $periodA = BudgetPeriod::factory()->create([
            'project_code' => 'PRJ-A',
            'period_month' => $now->copy()->startOfMonth(),
            'created_by' => $user->id,
        ]);

        $periodB = BudgetPeriod::factory()->create([
            'project_code' => 'PRJ-B',
            'period_month' => $now->copy()->startOfMonth(),
            'created_by' => $user->id,
        ]);

        $a1 = $this->insertLegacyAllocation($periodA->id, [
            'allocated_amount' => '100000000.00',
            'equipment_id' => 1,
            'unit_code_cache' => 'U-1',
            'tolerance_pct' => '10.00',
        ]);
        $a2 = $this->insertLegacyAllocation($periodA->id, [
            'allocated_amount' => '200000000.00',
            'equipment_id' => 2,
            'unit_code_cache' => 'U-2',
            'tolerance_pct' => '12.00',
        ]);

        $b1 = $this->insertLegacyAllocation($periodB->id, [
            'allocated_amount' => '95000000.00',
            'equipment_id' => 3,
            'unit_code_cache' => 'U-3',
            'tolerance_pct' => '10.00',
        ]);
        $b2 = $this->insertLegacyAllocation($periodB->id, [
            'allocated_amount' => '100000000.00',
            'equipment_id' => 4,
            'unit_code_cache' => 'U-4',
            'tolerance_pct' => '10.00',
        ]);
        $b3 = $this->insertLegacyAllocation($periodB->id, [
            'allocated_amount' => '100000000.00',
            'equipment_id' => 5,
            'unit_code_cache' => 'U-5',
            'tolerance_pct' => '11.00',
        ]);

        $ledgerIds = [];
        foreach ([$a1, $a2, $b1, $b2, $b3] as $allocationId) {
            $ledgerIds[] = BudgetLedger::create([
                'budget_allocation_id' => $allocationId,
                'entry_type' => 'allocation',
                'amount' => '1.00',
                'posted_by' => $user->id,
                'posted_at' => $now,
            ])->id;
        }

        $migration = require database_path('migrations/2026_09_23_110909_consolidate_budget_allocations_to_global_per_period.php');
        $migration->up();

        $this->assertSame(1, BudgetAllocation::query()->where('budget_period_id', $periodA->id)->count());
        $this->assertSame(1, BudgetAllocation::query()->where('budget_period_id', $periodB->id)->count());

        $mergedA = BudgetAllocation::query()->where('budget_period_id', $periodA->id)->first();
        $mergedB = BudgetAllocation::query()->where('budget_period_id', $periodB->id)->first();

        $this->assertSame('300000000.00', (string) $mergedA->allocated_amount);
        $this->assertSame('295000000.00', (string) $mergedB->allocated_amount);
        $this->assertNull($mergedA->equipment_id);
        $this->assertNull($mergedA->unit_code_cache);
        $this->assertNull($mergedA->plant_type_cache);
        $this->assertSame('12.00', (string) $mergedA->tolerance_pct);
        $this->assertSame('11.00', (string) $mergedB->tolerance_pct);

        foreach ($ledgerIds as $ledgerId) {
            $this->assertTrue(
                in_array(
                    BudgetLedger::query()->find($ledgerId)->budget_allocation_id,
                    [$mergedA->id, $mergedB->id],
                    true
                )
            );
        }

        $this->assertSame(2, BudgetLedger::query()->where('budget_allocation_id', $mergedA->id)->count());
        $this->assertSame(3, BudgetLedger::query()->where('budget_allocation_id', $mergedB->id)->count());
    }

    public function test_allocate_on_empty_period_creates_exactly_one_global_row(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $engine->allocate($period, [
            ['allocated_amount' => '25000000.00', 'tolerance_pct' => '10.00'],
        ], $user);

        $period->refresh();
        $this->assertCount(1, $period->allocations);

        $allocation = $period->allocations->first();
        $this->assertNull($allocation->equipment_id);
        $this->assertNull($allocation->unit_code_cache);
        $this->assertNull($allocation->plant_type_cache);
        $this->assertSame('25000000.00', (string) $allocation->allocated_amount);
        $this->assertSame(1, BudgetLedger::query()->where('entry_type', 'allocation')->count());
    }

    public function test_second_allocate_for_same_period_revises_via_ledger_reversal_and_new_allocation(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $engine->allocate($period, [
            ['allocated_amount' => '10000000.00', 'tolerance_pct' => '10.00'],
        ], $user);

        $engine->allocate($period, [
            ['allocated_amount' => '15000000.00', 'tolerance_pct' => '10.00'],
        ], $user);

        $period->refresh();
        $this->assertCount(1, $period->allocations);
        $this->assertSame('15000000.00', (string) $period->allocations->first()->allocated_amount);

        $allocationId = $period->allocations->first()->id;

        $this->assertSame(1, BudgetLedger::query()
            ->where('budget_allocation_id', $allocationId)
            ->where('entry_type', 'reversal')
            ->count());

        $this->assertSame(2, BudgetLedger::query()
            ->where('budget_allocation_id', $allocationId)
            ->where('entry_type', 'allocation')
            ->count());

        $reversal = BudgetLedger::query()
            ->where('budget_allocation_id', $allocationId)
            ->where('entry_type', 'reversal')
            ->first();

        $this->assertSame('-10000000.00', (string) $reversal->amount);
    }

    private function insertLegacyAllocation(int $periodId, array $overrides): int
    {
        $row = array_merge([
            'budget_period_id' => $periodId,
            'equipment_id' => null,
            'unit_code_cache' => null,
            'plant_type_cache' => null,
            'allocated_amount' => '0.00',
            'tolerance_pct' => '10.00',
            'carry_forward_in' => '0.00',
            'committed_amount' => '0.00',
            'actual_amount' => '0.00',
            'is_editable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('budget_allocations')->insertGetId($row);
    }
}
