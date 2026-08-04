<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapSyncLog extends Model
{
    protected $fillable = [
        'operation',
        'correlation_key',
        'ref_type',
        'ref_id',
        'status',
        'attempts',
        'request_payload',
        'response_payload',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
