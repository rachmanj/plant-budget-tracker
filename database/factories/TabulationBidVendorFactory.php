<?php

namespace Database\Factories;

use App\Models\TabulationBid;
use App\Models\TabulationBidVendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class TabulationBidVendorFactory extends Factory
{
    protected $model = TabulationBidVendor::class;

    public function definition(): array
    {
        return [
            'tabulation_bid_id' => TabulationBid::factory(),
            'vendor_code' => 'V'.fake()->numerify('###'),
            'vendor_name' => fake()->company(),
            'price' => fake()->randomFloat(2, 100000, 5000000),
            'stock_availability' => 'ready',
            'rank' => 1,
        ];
    }
}
