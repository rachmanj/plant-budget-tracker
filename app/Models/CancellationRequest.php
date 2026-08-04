<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CancellationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_request_id',
        'sap_po_id',
        'po_stage',
        'initiated_by',
        'status',
        'budget_reversal_amount',
        'reason',
        'agreed_by',
        'agreed_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_reversal_amount' => 'decimal:2',
            'agreed_at' => 'datetime',
        ];
    }

    public function plantRequest(): BelongsTo
    {
        return $this->belongsTo(PlantRequest::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(RequestApproval::class, 'approvable');
    }

    public function canBeCancelledByPlant(): bool
    {
        return $this->po_stage !== 'sent';
    }
}
