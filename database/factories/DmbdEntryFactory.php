<?php

namespace Database\Factories;

use App\Models\DmbdEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DmbdEntryFactory extends Factory
{
    protected $model = DmbdEntry::class;

    public function definition(): array
    {
        return [
            'equipment_id' => fake()->numberBetween(1, 1000),
            'unit_code_cache' => 'E-'.fake()->numerify('###'),
            'report_date' => now()->toDateString(),
            'operational_status' => 'rfu',
            'reported_by' => User::factory(),
        ];
    }
}
