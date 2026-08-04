<?php

namespace App\Models\Sap;

use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    protected $connection = 'sap_sql';

    protected $table = 'ITM1';

    public $timestamps = false;

    protected $guarded = ['*'];
}
