<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPriceImport extends Model
{
    protected $fillable = [
        'original_name',
        'stored_path',
        'rows_total',
        'rows_created',
        'rows_updated',
        'rows_unchanged',
        'rows_failed',
        'errors',
        'imported_by',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
