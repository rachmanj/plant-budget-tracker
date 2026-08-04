<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_request_id')->constrained()->cascadeOnDelete();
            $table->string('part_number');
            $table->string('material_name');
            $table->string('uom', 10);
            $table->unsignedInteger('qty');
            $table->decimal('unit_price_est', 18, 2)->default(0);
            $table->enum('price_source', ['tabulation_bid', 'sap_price', 'manual', 'none'])->default('none');
            $table->foreignId('interchange_map_id')->nullable()->constrained('interchange_maps');
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();

            $table->index('plant_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_request_lines');
    }
};
