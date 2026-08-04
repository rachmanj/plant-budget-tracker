<?php

namespace App\Models\Sap;

use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    protected $connection = 'sap_sql';

    protected $table = 'OPRQ';

    protected $primaryKey = 'DocEntry';

    public $timestamps = false;

    protected $guarded = ['*'];
}
