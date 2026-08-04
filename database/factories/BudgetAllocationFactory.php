<?php

namespace Database\Factories;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetAllocation>
 */
class BudgetAllocationFactory extends Factory
{
    protected $model = BudgetAllocation::class;

    public function definition(): array
    {
        return [
            'budget_period_id' => BudgetPeriod::factory(),
            'equipment_id' => fake()->optional()->numberBetween(1, 9999),
            'unit_code_cache' => fake()->optional()->regexify('E-[0-9]{3}'),
            'plant_type_cache' => fake()->randomElement(['DIGGER', 'HAULER', 'SUPPORT']),
            'allocated_amount' => fake()->randomFloat(2, 1000000, 500000000),
            'tolerance_pct' => '10.00',
            'carry_forward_in' => '0.00',
            'committed_amount' => '0.00',
            'actual_amount' => '0.00',
            'is_editable' => true,
        ];
    }
}
