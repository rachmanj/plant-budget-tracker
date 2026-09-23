<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('budget_allocations')->update([
            'equipment_id' => null,
            'unit_code_cache' => null,
            'plant_type_cache' => null,
        ]);
    }

    public function down(): void
    {
        //
    }
};
