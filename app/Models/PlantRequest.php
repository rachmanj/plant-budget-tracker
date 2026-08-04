<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PlantRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_no',
        'budget_allocation_id',
        'equipment_id',
        'unit_code_cache',
        'dmbd_entry_id',
        'sap_mr_id',
        'sap_pr_no',
        'status',
        'estimated_total',
        'budget_utilization_pct',
        'requested_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_total' => 'decimal:2',
            'budget_utilization_pct' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PlantRequestLine::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function dmbdEntry(): BelongsTo
    {
        return $this->belongsTo(DmbdEntry::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(RequestApproval::class, 'approvable');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RequestComment::class);
    }

    public function getProjectCodeAttribute(): ?string
    {
        return $this->allocation?->period?->project_code;
    }

    public function onFullyApproved(): void
    {
        $this->update(['status' => 'approved']);
    }

    protected static function booted(): void
    {
        static::creating(function (PlantRequest $request) {
            if (! $request->request_no) {
                $count = static::whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count() + 1;
                $request->request_no = sprintf('PMB-REQ-%s-%04d', now()->format('Ym'), $count);
            }
        });
    }
}
