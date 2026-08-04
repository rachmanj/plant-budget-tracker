<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class BudgetLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_allocation_id',
        'entry_type',
        'amount',
        'ref_type',
        'ref_id',
        'memo',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    protected static function booted(): void
    {
        static::saving(function (BudgetLedger $ledger) {
            if ($ledger->exists) {
                throw new RuntimeException('budget_ledgers rows are immutable. Post a reversal entry instead of updating.');
            }
        });

        static::deleting(function () {
            throw new RuntimeException('budget_ledgers rows are immutable. Post a reversal entry instead of deleting.');
        });
    }
}
