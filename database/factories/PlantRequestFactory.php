<?php

namespace Database\Factories;

use App\Models\BudgetAllocation;
use App\Models\PlantRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlantRequestFactory extends Factory
{
    protected $model = PlantRequest::class;

    public function definition(): array
    {
        return [
            'budget_allocation_id' => BudgetAllocation::factory(),
            'equipment_id' => fake()->numberBetween(1, 1000),
            'unit_code_cache' => 'E-'.fake()->numerify('###'),
            'sap_mr_id' => fake()->numberBetween(1, 10000),
            'status' => 'draft',
            'estimated_total' => '1000000.00',
            'requested_by' => User::factory(),
        ];
    }
}
