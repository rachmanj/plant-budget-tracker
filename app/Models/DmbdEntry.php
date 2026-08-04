<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DmbdEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'unit_code_cache',
        'report_date',
        'operational_status',
        'breakdown_note',
        'reported_by',
        'synced_to_arkfleet',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date:Y-m-d',
            'synced_to_arkfleet' => 'boolean',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function plantRequests(): HasMany
    {
        return $this->hasMany(PlantRequest::class, 'dmbd_entry_id');
    }

    public static function upsertForToday(int $equipmentId, string $unitCode, string $status, ?string $note, int $userId): self
    {
        return static::updateOrCreate(
            ['equipment_id' => $equipmentId, 'report_date' => now()->toDateString()],
            [
                'unit_code_cache' => $unitCode,
                'operational_status' => $status,
                'breakdown_note' => $note,
                'reported_by' => $userId,
                'synced_to_arkfleet' => false,
            ]
        );
    }
}
