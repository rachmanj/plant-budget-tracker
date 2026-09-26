<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SapPurchaseRequestLine extends Model
{
    protected $fillable = [
        'sap_purchase_request_id',
        'sap_doc_entry',
        'line_num',
        'vis_order',
        'item_code',
        'description',
        'qty',
        'uom',
        'unit_price',
        'line_vendor_code',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(SapPurchaseRequest::class, 'sap_purchase_request_id');
    }
}
