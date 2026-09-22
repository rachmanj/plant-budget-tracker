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
            'sap_grpo_no.required' => 'Nomor GRPO wajib diisi.',
            'sap_grpo_no.string' => 'Nomor GRPO harus berupa teks.',
            'sap_grpo_no.max' => 'Nomor GRPO maksimal 50 karakter.',
            'received_at.required' => 'Tanggal terima wajib diisi.',
            'received_at.date' => 'Tanggal terima tidak valid.',
            'received_at.before_or_equal' => 'Tanggal terima tidak boleh di masa depan.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
