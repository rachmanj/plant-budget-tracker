<?php

namespace App\Models\Sap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    protected $connection = 'sap_sql';

    protected $table = 'OPRQ';

    protected $primaryKey = 'DocEntry';

    public $timestamps = false;

    protected $guarded = ['*'];

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class, 'DocEntry', 'DocEntry');
    }
}
