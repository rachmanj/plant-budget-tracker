<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TabulationBid extends Model
{
    use HasFactory;

    protected $fillable = [
        'bid_no',
        'sap_pr_id',
        'status',
        'created_by',
        'reviewed_by',
        'sap_po_id',
        'sap_sync_failed',
    ];

    protected function casts(): array
    {
        return [
            'sap_sync_failed' => 'boolean',
        ];
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(TabulationBidVendor::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(TabulationBidAward::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(RequestApproval::class, 'approvable');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function onFullyApproved(): void
    {
        $this->update(['status' => 'forwarded_admin']);
    }

    protected static function booted(): void
    {
        static::creating(function (TabulationBid $bid) {
            if (! $bid->bid_no) {
                $count = static::whereYear('created_at', now()->year)->count() + 1;
                $bid->bid_no = sprintf('PMB-BID-%s-%04d', now()->format('Ym'), $count);
            }
        });
    }
}
