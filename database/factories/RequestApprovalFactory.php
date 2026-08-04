<?php

namespace Database\Factories;

use App\Models\RequestApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestApprovalFactory extends Factory
{
    protected $model = RequestApproval::class;

    public function definition(): array
    {
        return [
            'approvable_type' => 'App\\Models\\PlantRequest',
            'approvable_id' => 1,
            'step_order' => 1,
            'required_role' => 'project_manager',
            'decision' => 'pending',
        ];
    }
}
