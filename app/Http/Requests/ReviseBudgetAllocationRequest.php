<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviseBudgetAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $allocation = $this->route('allocation');

        return $allocation && $this->user()?->can('revise', $allocation);
    }

    public function rules(): array
    {
        return [
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
