<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SapPurchaseRequest extends Model
{
    protected $fillable = [
        'sap_doc_entry',
        'doc_num',
        'doc_date',
        'create_date',
        'pr_type',
        'department_code',
        'department_name',
        'requester',
        'mr_no',
        'required_date',
        'remarks',
        'pr_status',
        'closed_status',
        'pr_rev_no',
        'unit_no',
        'hours_meter',
        'line_count',
        'total_amount',
        'project_code',
        'legacy_source',
        'legacy_doc_num',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date',
            'create_date' => 'datetime',
            'required_date' => 'date',
            'hours_meter' => 'decimal:2',
            'line_count' => 'integer',
            'total_amount' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SapPurchaseRequestLine::class);
    }

    public function scopeDocDateBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('doc_date', [$from, $to]);
    }

    public function scopeForProject(Builder $query, string $projectCode): Builder
    {
        return $query->where('project_code', $projectCode);
    }

    public function scopeForDepartment(Builder $query, string $departmentCode): Builder
    {
        return $query->where('department_code', $departmentCode);
    }
}
