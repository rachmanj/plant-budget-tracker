<?php

namespace Tests\Feature\Budget;

use App\Jobs\CarryForwardJob;
use App\Models\BudgetAllocation;
use App\Models\BudgetLedger;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BudgetCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Cache::flush();
    }

    public function test_carry_forward_is_idempotent_and_calculates_correct_amount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15', 'Asia/Makassar'));

        $user = User::factory()->create(['is_active' => true]);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $period = BudgetPeriod::factory()->create([
            'project_code' => 'MBL',
            'period_month' => $lastMonth->toDateString(),
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
            'plant_type_cache' => 'DIGGER',
        ], $user);

        $engine->postCommitment($allocation, '3000000.00', 'plant_request', 1, $user);

        $asOf = now()->copy();
        $engine->carryForward($asOf);

        $nextPeriod = BudgetPeriod::query()
            ->where('project_code', 'MBL')
            ->whereDate('period_month', now()->startOfMonth())
            ->first();

        $this->assertNotNull($nextPeriod);

        $nextAllocation = BudgetAllocation::query()
            ->where('budget_period_id', $nextPeriod->id)
            ->where('equipment_id', $allocation->equipment_id)
            ->first();

        $this->assertNotNull($nextAllocation);
        $this->assertSame('7000000.00', (string) $nextAllocation->carry_forward_in);

        $carryEntries = BudgetLedger::query()
            ->where('entry_type', 'carry_forward')
            ->where('budget_allocation_id', $nextAllocation->id)
            ->count();

        $this->assertSame(1, $carryEntries);

        $engine->carryForward($asOf);

        $this->assertSame(1, BudgetLedger::query()
            ->where('entry_type', 'carry_forward')
            ->where('budget_allocation_id', $nextAllocation->id)
            ->count());

        $period->refresh();
        $this->assertSame('locked', $period->status);

        Carbon::setTestNow();
    }

    public function test_over_spent_allocation_carries_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15', 'Asia/Makassar'));

        $user = User::factory()->create(['is_active' => true]);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $period = BudgetPeriod::factory()->create([
            'project_code' => 'MBL',
            'period_month' => $lastMonth->toDateString(),
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '5000000.00',
        ], $user);

        $engine->postCommitment($allocation, '6000000.00', 'plant_request', 2, $user);

        $engine->carryForward(now());

        $nextPeriod = BudgetPeriod::query()
            ->where('project_code', 'MBL')
            ->whereDate('period_month', now()->startOfMonth())
            ->first();

        $nextAllocation = BudgetAllocation::query()
            ->where('budget_period_id', $nextPeriod->id)
            ->first();

        $this->assertSame('0.00', (string) $nextAllocation->carry_forward_in);
        $this->assertSame(0, BudgetLedger::query()
            ->where('entry_type', 'carry_forward')
            ->where('budget_allocation_id', $nextAllocation->id)
            ->count());

        Carbon::setTestNow();
    }

    public function test_carry_forward_job_runs_idempotently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02', 'Asia/Makassar'));

        $user = User::factory()->create(['is_active' => true]);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $period = BudgetPeriod::factory()->create([
            'project_code' => 'MBL',
            'period_month' => $lastMonth->toDateString(),
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
        ], $user);

        $asOf = now()->copy();

        (new CarryForwardJob($asOf))->handle($engine);
        (new CarryForwardJob($asOf))->handle($engine);

        $nextPeriod = BudgetPeriod::query()
            ->where('project_code', 'MBL')
            ->whereDate('period_month', now()->startOfMonth())
            ->first();

        $nextAllocation = BudgetAllocation::query()
            ->where('budget_period_id', $nextPeriod->id)
            ->where('equipment_id', $allocation->equipment_id)
            ->first();

        $this->assertSame(1, BudgetLedger::query()
            ->where('entry_type', 'carry_forward')
            ->where('budget_allocation_id', $nextAllocation->id)
            ->count());

        Carbon::setTestNow();
    }
}
