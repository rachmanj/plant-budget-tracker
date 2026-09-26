<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SapPurchaseOrder extends Model
{
    protected $fillable = [
        'sap_doc_entry',
        'doc_num',
        'doc_date',
        'create_date',
        'delivery_date',
        'po_eta',
        'pr_no',
        'vendor_code',
        'vendor_name',
        'project_code',
        'dept_code',
        'dept_name',
        'currency',
        'total_amount',
        'vat_amount',
        'disc_amount',
        'delivery_status',
        'budget_type',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date',
            'create_date' => 'datetime',
            'delivery_date' => 'datetime',
            'po_eta' => 'datetime',
            'total_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'disc_amount' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SapPurchaseOrderLine::class);
    }

    public function scopeDocDateBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('doc_date', [$from, $to]);
    }

    public function scopeForProject(Builder $query, string $projectCode): Builder
    {
        return $query->where('project_code', $projectCode);
    }

    public function scopeForDepartment(Builder $query, string $deptCode): Builder
    {
        return $query->where('dept_code', $deptCode);
    }
}
