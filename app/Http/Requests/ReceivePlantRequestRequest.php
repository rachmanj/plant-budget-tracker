<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePlantRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi berbasis permission + status ditangani di controller
        // agar pesan 403/422 bisa disesuaikan ke Bahasa Indonesia.
        return true;
    }

    public function rules(): array
    {
        return [
            'sap_grpo_no' => ['required', 'string', 'max:50'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'sap_grpo_no.required' => 'GRPO number is required.',
            'sap_grpo_no.string' => 'GRPO number must be text.',
            'sap_grpo_no.max' => 'GRPO number may not exceed 50 characters.',
            'received_at.required' => 'Received date is required.',
            'received_at.date' => 'Received date is not valid.',
            'received_at.before_or_equal' => 'Received date may not be in the future.',
            'note.string' => 'Note must be text.',
            'note.max' => 'Note may not exceed 500 characters.',
        ];
    }
}
