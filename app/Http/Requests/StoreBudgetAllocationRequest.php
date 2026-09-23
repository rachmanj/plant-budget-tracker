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
            'project_code.required' => 'Proyek wajib dipilih.',
            'period_month.required' => 'Bulan periode wajib diisi.',
            'period_month.date' => 'Bulan periode tidak valid.',
            'allocated_amount.required' => 'Total anggaran wajib diisi.',
            'allocated_amount.numeric' => 'Total anggaran harus berupa angka.',
            'allocated_amount.min' => 'Total anggaran minimal 0.',
            'tolerance_pct.required' => 'Toleransi wajib diisi.',
            'tolerance_pct.numeric' => 'Toleransi harus berupa angka.',
            'tolerance_pct.min' => 'Toleransi minimal 0%.',
            'tolerance_pct.max' => 'Toleransi maksimal 100%.',
        ];
    }
}
