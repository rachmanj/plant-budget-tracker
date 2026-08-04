<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetAllocation;
use App\Models\BudgetLedger;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\Budget\BudgetEngine;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BudgetLedgerImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_ledger_entry_cannot_be_modified_after_posting(): void
    {
        $user = $this->makeFinanceDirector();
        $period = BudgetPeriod::factory()->create(['created_by' => $user->id, 'status' => 'open']);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
            'plant_type_cache' => 'DIGGER',
        ], $user);

        $ledger = BudgetLedger::query()->where('budget_allocation_id', $allocation->id)->first();
        $this->assertNotNull($ledger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('budget_ledgers rows are immutable');

        $ledger->update(['amount' => '999.00']);
    }

    public function test_revision_posts_reversal_and_new_allocation_without_touching_original_ledger(): void
    {
        $user = $this->makeFinanceDirector();
        $period = BudgetPeriod::factory()->create(['created_by' => $user->id, 'status' => 'open']);

        $engine = app(BudgetEngine::class);
        $allocation = $engine->createAllocation($period, [
            'allocated_amount' => '10000000.00',
        ], $user);

        $originalLedger = BudgetLedger::query()->where('budget_allocation_id', $allocation->id)->first();
        $originalAmount = (string) $originalLedger->amount;

        $engine->reviseAllocation($allocation, '15000000.00', $user, 'Increase for critical unit');

        $originalLedger->refresh();
        $this->assertSame($originalAmount, (string) $originalLedger->amount);

        $this->assertDatabaseHas('budget_ledgers', [
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'reversal',
            'amount' => '-10000000.00',
        ]);

        $this->assertDatabaseHas('budget_ledgers', [
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'allocation',
            'amount' => '15000000.00',
        ]);

        $allocation->refresh();
        $this->assertSame('15000000.00', (string) $allocation->allocated_amount);
    }

    private function makeFinanceDirector(): User
    {
        $user = User::factory()->create(['is_active' => true, 'project_code_scope' => null]);
        setPermissionsTeamId('');
        $user->assignRole('finance_director');

        return $user;
    }
}
