<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OverbudgetRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_no',
        'budget_allocation_id',
        'plant_request_id',
        'requested_amount',
        'over_pct',
        'status',
        'justification',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'over_pct' => 'decimal:2',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function plantRequest(): BelongsTo
    {
        return $this->belongsTo(PlantRequest::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(RequestApproval::class, 'approvable');
    }

    public function onFullyApproved(): void
    {
        $this->update(['status' => 'approved']);

        $budgetEngine = app(\App\Services\Budget\BudgetEngine::class);
        $actor = User::find($this->requested_by) ?? User::query()->first();

        if ($actor && $this->allocation) {
            $budgetEngine->postOverbudget(
                $this->allocation,
                (string) $this->requested_amount,
                $this->id,
                $actor,
                'Overbudget approved'
            );
        }

        if ($this->plant_request_id) {
            $plantRequest = PlantRequest::find($this->plant_request_id);
            if ($plantRequest && in_array($plantRequest->status, ['draft'], true)) {
                $plantRequest->update(['status' => 'pending_pm']);
            }
        }
    }

    protected static function booted(): void
    {
        static::creating(function (OverbudgetRequest $request) {
            if (! $request->request_no) {
                $count = static::whereYear('created_at', now()->year)->count() + 1;
                $request->request_no = sprintf('PMB-OB-%s-%04d', now()->format('Ym'), $count);
            }
        });
    }
}
