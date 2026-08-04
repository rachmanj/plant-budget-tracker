<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterchangeMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'genuine_part_number',
        'oem_part_number',
        'material_name',
        'sap_synced',
        'sap_sync_ref',
        'created_by',
        'technical_signoff_by',
        'technical_signoff_at',
    ];

    protected function casts(): array
    {
        return [
            'sap_synced' => 'boolean',
            'technical_signoff_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PlantRequestLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signoffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_signoff_by');
    }

    public function isReadyForSapSync(): bool
    {
        return $this->technical_signoff_at !== null && ! $this->sap_synced;
    }
}
