<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetLedger;
use App\Models\BudgetPeriod;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAllocationCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_finance_director_creates_allocation_and_ledger_entry_is_posted(): void
    {
        $user = $this->makeFinanceDirector();

        $response = $this->actingAs($user)
            ->withoutVite()
            ->post('/budget', [
                'project_code' => 'MBL',
                'period_month' => now()->startOfMonth()->toDateString(),
                'status' => 'open',
                'allocations' => [
                    [
                        'allocated_amount' => 25000000,
                        'tolerance_pct' => 10,
                        'plant_type_cache' => 'DIGGER',
                        'unit_code_cache' => 'E-042',
                    ],
                ],
            ]);

        $response->assertRedirect(route('budget.index', ['project_code' => 'MBL']));

        $period = BudgetPeriod::query()->where('project_code', 'MBL')->first();
        $this->assertNotNull($period);
        $this->assertCount(1, $period->allocations);

        $allocation = $period->allocations->first();
        $this->assertSame('25000000.00', (string) $allocation->allocated_amount);

        $this->assertDatabaseHas('budget_ledgers', [
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'allocation',
            'amount' => '25000000.00',
            'posted_by' => $user->id,
        ]);

        $this->assertSame(1, BudgetLedger::count());
    }

    private function makeFinanceDirector(): User
    {
        $user = User::factory()->create(['is_active' => true, 'project_code_scope' => null]);
        setPermissionsTeamId('');
        $user->assignRole('finance_director');

        return $user;
    }
}
