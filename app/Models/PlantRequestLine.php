<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantRequestLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_request_id',
        'part_number',
        'material_name',
        'uom',
        'qty',
        'unit_price_est',
        'price_source',
        'interchange_map_id',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_est' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PlantRequest::class, 'plant_request_id');
    }

    public function interchangeMap(): BelongsTo
    {
        return $this->belongsTo(InterchangeMap::class);
    }

    protected static function booted(): void
    {
        static::saving(function (PlantRequestLine $line) {
            $line->line_total = bcmul((string) $line->qty, (string) $line->unit_price_est, 2);
        });
    }
}
