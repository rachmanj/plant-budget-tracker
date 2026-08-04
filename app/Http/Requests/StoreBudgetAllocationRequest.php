<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\BudgetAllocation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'project_code' => ['required', 'string', 'max:20'],
            'period_month' => ['required', 'date'],
            'status' => ['sometimes', 'in:draft,open,locked,closed'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.equipment_id' => ['nullable', 'integer'],
            'allocations.*.unit_code_cache' => ['nullable', 'string', 'max:50'],
            'allocations.*.plant_type_cache' => ['nullable', 'in:DIGGER,HAULER,SUPPORT'],
            'allocations.*.allocated_amount' => ['required', 'numeric', 'min:0'],
            'allocations.*.tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocations.*.memo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
