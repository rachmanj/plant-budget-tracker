<?php

namespace App\Support;

class ApprovalChains
{
    public static function for(string $type): array
    {
        return match ($type) {
            'PlantRequest' => [
                ['step_order' => 1, 'required_role' => 'project_manager'],
                ['step_order' => 2, 'required_role' => 'plant_manager'],
            ],
            'TabulationBid' => [
                ['step_order' => 1, 'required_role' => 'procurement_manager'],
            ],
            'OverbudgetRequest' => [
                ['step_order' => 1, 'required_role' => 'finance_director'],
                ['step_order' => 2, 'required_role' => 'operation_director'],
            ],
            'CancellationRequest' => [
                ['step_order' => 1, 'required_role' => 'procurement_manager'],
            ],
            'CannibalRequest' => [
                ['step_order' => 1, 'required_role' => 'plant_manager'],
                ['step_order' => 2, 'required_role' => 'aml_manager'],
                ['step_order' => 3, 'required_role' => 'operation_director'],
                ['step_order' => 4, 'required_role' => 'president_director'],
            ],
            default => [],
        };
    }
}
