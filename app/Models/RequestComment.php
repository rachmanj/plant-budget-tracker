<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestComment extends Model
{
    protected $fillable = [
        'plant_request_id',
        'category',
        'body',
        'author_id',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PlantRequest::class, 'plant_request_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
