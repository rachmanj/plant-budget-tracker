<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabulationBidAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'tabulation_bid_id',
        'tabulation_bid_vendor_id',
        'justification',
        'awarded_by',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(TabulationBidVendor::class, 'tabulation_bid_vendor_id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(TabulationBid::class, 'tabulation_bid_id');
    }
}
