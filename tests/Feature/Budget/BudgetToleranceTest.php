<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetToleranceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_validate_against_tolerance_uses_allocation_tolerance_pct(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
            'tolerance_pct' => '10.00',
        ], $user);

        $within = $engine->validateAgainstTolerance($allocation, '1000000.00');
        $this->assertTrue($within['within_tolerance']);
        $this->assertSame('11000000.00', $within['cap']);

        $atCap = $engine->validateAgainstTolerance($allocation, '11000000.00');
        $this->assertTrue($atCap['within_tolerance']);
        $this->assertSame('110.00', $atCap['projected_pct']);

        $over = $engine->validateAgainstTolerance($allocation, '11000001.00');
        $this->assertFalse($over['within_tolerance']);
    }

    public function test_configurable_tolerance_pct_is_respected(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
            'tolerance_pct' => '15.00',
        ], $user);

        $this->assertSame('11500000.00', $allocation->tolerance_cap);

        $within15 = $engine->validateAgainstTolerance($allocation, '11500000.00');
        $this->assertTrue($within15['within_tolerance']);

        $over15 = $engine->validateAgainstTolerance($allocation, '11500001.00');
        $this->assertFalse($over15['within_tolerance']);
        $this->assertSame('11500000.00', $over15['cap']);
    }

    public function test_tolerance_includes_carry_forward_in_base(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $period = BudgetPeriod::factory()->create([
            'created_by' => $user->id,
            'status' => 'open',
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
            'tolerance_pct' => '10.00',
        ], $user);

        \App\Models\BudgetLedger::create([
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'carry_forward',
            'amount' => '2000000.00',
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        $engine->recomputeCachedBalances($allocation);
        $allocation->refresh();

        $this->assertSame('13200000.00', $allocation->tolerance_cap);
    }
}
