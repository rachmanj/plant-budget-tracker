<?php

namespace Database\Factories;

use App\Models\TabulationBid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TabulationBidFactory extends Factory
{
    protected $model = TabulationBid::class;

    public function definition(): array
    {
        return [
            'sap_pr_id' => (string) fake()->numberBetween(1, 10000),
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
