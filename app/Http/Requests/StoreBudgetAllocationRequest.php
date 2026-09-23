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
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'tolerance_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_code.required' => 'Project is required.',
            'period_month.required' => 'Period month is required.',
            'period_month.date' => 'Period month is not valid.',
            'allocated_amount.required' => 'Total budget amount is required.',
            'allocated_amount.numeric' => 'Total budget amount must be a number.',
            'allocated_amount.min' => 'Total budget amount must be at least 0.',
            'tolerance_pct.required' => 'Tolerance is required.',
            'tolerance_pct.numeric' => 'Tolerance must be a number.',
            'tolerance_pct.min' => 'Tolerance must be at least 0%.',
            'tolerance_pct.max' => 'Tolerance may not exceed 100%.',
        ];
    }
}
