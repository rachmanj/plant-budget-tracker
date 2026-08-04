<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabulationBidVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'tabulation_bid_id',
        'vendor_code',
        'vendor_name',
        'price',
        'payment_terms',
        'stock_availability',
        'remarks',
        'rank',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(TabulationBid::class, 'tabulation_bid_id');
    }
}
