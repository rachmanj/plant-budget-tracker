<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_period_id',
        'equipment_id',
        'unit_code_cache',
        'plant_type_cache',
        'allocated_amount',
        'tolerance_pct',
        'carry_forward_in',
        'committed_amount',
        'actual_amount',
        'is_editable',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'tolerance_pct' => 'decimal:2',
            'carry_forward_in' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'is_editable' => 'boolean',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(BudgetLedger::class);
    }

    public function getToleranceCapAttribute(): string
    {
        $base = bcadd((string) $this->allocated_amount, (string) $this->carry_forward_in, 2);

        return bcmul($base, bcadd('1', bcdiv((string) $this->tolerance_pct, '100', 4), 4), 2);
    }

    public function getVarianceAttribute(): string
    {
        return bcsub(
            bcadd((string) $this->allocated_amount, (string) $this->carry_forward_in, 2),
            bcadd((string) $this->committed_amount, (string) $this->actual_amount, 2),
            2
        );
    }

    public function getUtilizationPctAttribute(): string
    {
        $base = bcadd((string) $this->allocated_amount, (string) $this->carry_forward_in, 2);

        if (bccomp($base, '0', 2) === 0) {
            return '0.00';
        }

        return bcmul(
            bcdiv(
                bcadd((string) $this->committed_amount, (string) $this->actual_amount, 2),
                $base,
                4
            ),
            '100',
            2
        );
    }
}
