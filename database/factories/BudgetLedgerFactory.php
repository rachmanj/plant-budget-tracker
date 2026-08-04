<?php

namespace Database\Factories;

use App\Models\BudgetAllocation;
use App\Models\BudgetLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLedger>
 */
class BudgetLedgerFactory extends Factory
{
    protected $model = BudgetLedger::class;

    public function definition(): array
    {
        return [
            'budget_allocation_id' => BudgetAllocation::factory(),
            'entry_type' => 'allocation',
            'amount' => fake()->randomFloat(2, 1000000, 100000000),
            'ref_type' => null,
            'ref_id' => null,
            'memo' => fake()->optional()->sentence(),
            'posted_by' => User::factory(),
            'posted_at' => now(),
        ];
    }

    public function allocation(): static
    {
        return $this->state(fn () => ['entry_type' => 'allocation', 'amount' => '10000000.00']);
    }

    public function commitment(): static
    {
        return $this->state(fn () => ['entry_type' => 'commitment', 'amount' => '-5000000.00', 'ref_type' => 'plant_request', 'ref_id' => 1]);
    }

    public function actual(): static
    {
        return $this->state(fn () => ['entry_type' => 'actual', 'amount' => '-5000000.00', 'ref_type' => 'grpo', 'ref_id' => 1]);
    }

    public function carryForward(): static
    {
        return $this->state(fn () => ['entry_type' => 'carry_forward', 'amount' => '1000000.00', 'ref_type' => 'budget_period', 'ref_id' => 1]);
    }

    public function reversal(): static
    {
        return $this->state(fn () => ['entry_type' => 'reversal', 'amount' => '-10000000.00', 'ref_type' => 'allocation', 'ref_id' => 1]);
    }

    public function overbudget(): static
    {
        return $this->state(fn () => ['entry_type' => 'overbudget', 'amount' => '2000000.00', 'ref_type' => 'overbudget_request', 'ref_id' => 1]);
    }
}
