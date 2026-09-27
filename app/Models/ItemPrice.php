<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPrice extends Model
{
    protected $fillable = [
        'item_code',
        'vendor_code',
        'uom',
        'price',
        'currency',
        'effective_date',
        'source',
        'note',
        'last_import_id',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function lastImport(): BelongsTo
    {
        return $this->belongsTo(ItemPriceImport::class, 'last_import_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
