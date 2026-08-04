<?php

namespace Database\Factories;

use App\Models\InterchangeMap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterchangeMapFactory extends Factory
{
    protected $model = InterchangeMap::class;

    public function definition(): array
    {
        return [
            'genuine_part_number' => fake()->bothify('GEN-####'),
            'oem_part_number' => fake()->bothify('OEM-####'),
            'material_name' => fake()->words(3, true),
            'created_by' => User::factory(),
        ];
    }
}
