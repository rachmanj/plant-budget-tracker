<?php

namespace App\Models\Sap;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestLine extends Model
{
    protected $connection = 'sap_sql';

    protected $table = 'PRQ1';

    public $timestamps = false;

    protected $guarded = ['*'];
}
