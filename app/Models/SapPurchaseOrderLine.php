<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SapPurchaseOrderLine extends Model
{
    protected $fillable = [
        'sap_purchase_order_id',
        'sap_doc_entry',
        'line_num',
        'vis_order',
        'item_code',
        'description',
        'qty',
        'uom',
        'unit_price',
        'item_amount',
        'project_code',
        'unit_no',
        'remark1',
        'remark2',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'item_amount' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(SapPurchaseOrder::class, 'sap_purchase_order_id');
    }
}
