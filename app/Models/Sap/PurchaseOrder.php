<?php

namespace App\Models\Sap;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $connection = 'sap_sql';

    protected $table = 'OPOR';

    protected $primaryKey = 'DocEntry';

    public $timestamps = false;

    protected $guarded = ['*'];
}
