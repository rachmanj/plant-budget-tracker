<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_code',
        'project_name_cache',
        'period_month',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date:Y-m-d',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->whereDate('period_month', now()->startOfMonth());
    }

    public function scopeRollingWindow(Builder $query, string $projectCode): Builder
    {
        return $query->where('project_code', $projectCode)
            ->whereBetween('period_month', [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->addMonthsNoOverflow(4)->startOfMonth(),
            ]);
    }

    public function isEditableBy(User $user): bool
    {
        if ($this->status === 'locked' || $this->status === 'closed') {
            return false;
        }

        $isCurrentOrFuture = $this->period_month->greaterThanOrEqualTo(now()->startOfMonth());

        if ($user->hasRole('finance_director')) {
            return $isCurrentOrFuture;
        }

        return $this->period_month->isSameMonth(now());
    }
}
