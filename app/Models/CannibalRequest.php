<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CannibalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_no',
        'source_equipment_id',
        'target_equipment_id',
        'dmbd_entry_id',
        'status',
        'reason',
        'requested_by',
    ];

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'cannibal_request_component');
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

    public function onFullyApproved(): void
    {
        $this->update(['status' => 'approved']);
        \App\Jobs\SyncComponentMovementToArkfleet::dispatch($this->id);
    }

    protected static function booted(): void
    {
        static::creating(function (CannibalRequest $request) {
            if (! $request->request_no) {
                $count = static::whereYear('created_at', now()->year)->count() + 1;
                $request->request_no = sprintf('PMB-CAN-%s-%04d', now()->format('Ym'), $count);
            }
        });
    }
}
