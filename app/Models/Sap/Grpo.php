<?php

namespace App\Models\Sap;

use Illuminate\Database\Eloquent\Model;

class Grpo extends Model
{
    protected $connection = 'sap_sql';

    protected $table = 'OPDN';

    protected $primaryKey = 'DocEntry';

    public $timestamps = false;

    protected $guarded = ['*'];
}
