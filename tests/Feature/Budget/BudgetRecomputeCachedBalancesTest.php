<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetLedger;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetRecomputeCachedBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_reverse_commitment_zeros_committed_and_restores_full_variance(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
        ], $user);

        $engine->postCommitment($allocation, '1000000.00', 'plant_request', 101, $user);
        $allocation->refresh();
        $this->assertSame('1000000.00', (string) $allocation->committed_amount);
        $this->assertSame('9000000.00', $allocation->variance);

        $engine->reverseCommitment($allocation, 'plant_request', 101, $user, 'Cancelled');
        $allocation->refresh();

        $this->assertSame('0.00', (string) $allocation->committed_amount);
        $this->assertSame('0.00', (string) $allocation->actual_amount);
        $this->assertSame('10000000.00', $allocation->variance);
    }

    public function test_grpo_zeros_committed_records_actual_without_double_counting_usage(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
        ], $user);

        $engine->postCommitment($allocation, '2000000.00', 'plant_request', 202, $user);
        $engine->postActual($allocation, '2000000.00', 5002, $user, 'GRPO receipt');
        $allocation->refresh();

        $this->assertSame('0.00', (string) $allocation->committed_amount);
        $this->assertSame('2000000.00', (string) $allocation->actual_amount);

        $usage = bcadd((string) $allocation->committed_amount, (string) $allocation->actual_amount, 2);
        $this->assertSame('2000000.00', $usage);
        $this->assertSame('8000000.00', $allocation->variance);
    }

    public function test_allocation_revision_does_not_change_committed_or_actual(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
        ], $user);

        $engine->postCommitment($allocation, '1500000.00', 'plant_request', 303, $user);
        $engine->postActual($allocation, '500000.00', 5003, $user);

        $allocation->refresh();
        $committedBefore = (string) $allocation->committed_amount;
        $actualBefore = (string) $allocation->actual_amount;

        $engine->reviseAllocation($allocation, '12000000.00', $user, 'Increase cap');
        $allocation->refresh();

        $this->assertSame($committedBefore, (string) $allocation->committed_amount);
        $this->assertSame($actualBefore, (string) $allocation->actual_amount);
        $this->assertSame('12000000.00', (string) $allocation->allocated_amount);
    }

    public function test_overbudget_ledger_does_not_increase_committed_or_actual(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
        ], $user);

        $engine->postCommitment($allocation, '3000000.00', 'plant_request', 404, $user);
        $allocation->refresh();

        $engine->postOverbudget($allocation, '2500000.00', 9004, $user);
        $allocation->refresh();

        $this->assertSame('3000000.00', (string) $allocation->committed_amount);
        $this->assertSame('0.00', (string) $allocation->actual_amount);
    }

    public function test_reversal_with_null_ref_type_offsets_commitment(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
        ], $user);

        $engine->postCommitment($allocation, '1000000.00', 'plant_request', 505, $user);

        BudgetLedger::create([
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'reversal',
            'amount' => '1000000.00',
            'ref_type' => null,
            'ref_id' => null,
            'posted_by' => $user->id,
            'posted_at' => now(),
            'memo' => 'Manual offset',
        ]);

        $engine->recomputeCachedBalances($allocation);
        $allocation->refresh();

        $this->assertSame('0.00', (string) $allocation->committed_amount);
    }
}
