<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPriceHistory extends Model
{
    protected $fillable = [
        'item_code',
        'vendor_code',
        'old_price',
        'new_price',
        'currency',
        'source',
        'effective_date',
        'import_id',
        'changed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ItemPriceImport::class, 'import_id');
    }
}
