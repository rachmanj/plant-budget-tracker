<?php

namespace App\Models\Sap;

use Illuminate\Database\Eloquent\Model;

class VendorMaster extends Model
{
    protected $connection = 'sap_sql';

    protected $table = 'OCRD';

    protected $primaryKey = 'CardCode';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];
}
