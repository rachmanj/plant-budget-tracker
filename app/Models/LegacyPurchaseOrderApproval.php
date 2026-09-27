<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyPurchaseOrderApproval extends Model
{
    protected $fillable = [
        'doc_num',
        'sap_doc_entry',
        'level',
        'required_role',
        'approver_name',
        'decision',
        'remarks',
        'acted_at',
        'legacy_source',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }
}
