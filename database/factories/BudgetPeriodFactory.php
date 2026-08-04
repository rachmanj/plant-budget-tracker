<?php

namespace Database\Factories;

use App\Models\BudgetPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetPeriod>
 */
class BudgetPeriodFactory extends Factory
{
    protected $model = BudgetPeriod::class;

    public function definition(): array
    {
        return [
            'project_code' => 'MBL',
            'project_name_cache' => 'Mine Block L',
            'period_month' => now()->startOfMonth()->toDateString(),
            'status' => 'open',
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => 'locked']);
    }

    public function forMonth(string $month): static
    {
        return $this->state(fn () => [
            'period_month' => Carbon::parse($month)->startOfMonth()->toDateString(),
        ]);
    }
}
