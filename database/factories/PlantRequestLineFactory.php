<?php

namespace Database\Factories;

use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlantRequestLineFactory extends Factory
{
    protected $model = PlantRequestLine::class;

    public function definition(): array
    {
        return [
            'plant_request_id' => PlantRequest::factory(),
            'part_number' => fake()->bothify('PN-####'),
            'material_name' => fake()->words(3, true),
            'uom' => 'EA',
            'qty' => fake()->numberBetween(1, 10),
            'unit_price_est' => '100000.00',
            'price_source' => 'manual',
        ];
    }
}
